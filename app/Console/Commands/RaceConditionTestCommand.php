<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class RaceConditionTestCommand extends Command
{
    protected $signature = 'test:race-condition
        {--base-url=http://127.0.0.1:8000 : Base URL API yang akan dites}
        {--requests=50 : Jumlah request order yang dikirim}
        {--stock=10 : Stok awal produk test}
        {--quantity=1 : Quantity per order}
        {--concurrency=10 : Jumlah request paralel}';

    protected $description = 'Menguji API order agar stok tidak menjadi negatif saat banyak request masuk bersamaan';

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $totalRequests = (int) $this->option('requests');
        $initialStock = (int) $this->option('stock');
        $quantity = (int) $this->option('quantity');
        $concurrency = (int) $this->option('concurrency');

        if ($totalRequests <= 0 || $initialStock < 0 || $quantity <= 0 || $concurrency <= 0) {
            $this->error('Input tidak valid.');
            return 1;
        }

        $product = Product::updateOrCreate(
            ['name' => 'Race Condition Test Product'],
            [
                'price' => 100000,
                'sale_price' => 25000,
                'stock' => $initialStock,
            ]
        );

        $this->cleanupTestOrders($product->id);
        $product->update(['stock' => $initialStock]);

        $client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 60,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);

        $statusCounts = [];
        $failedRequests = 0;
        $failedReasons = [];

        $this->info('Running race condition test...');
        $this->line("Base URL       : {$baseUrl}");
        $this->line("Product ID     : {$product->id}");
        $this->line("Initial stock  : {$initialStock}");
        $this->line("Total requests : {$totalRequests}");
        $this->line("Concurrency    : {$concurrency}");
        $this->newLine();

        $requests = function () use ($client, $totalRequests, $product, $quantity) {
            for ($i = 0; $i < $totalRequests; $i++) {
                yield function () use ($client, $product, $quantity) {
                    return $client->postAsync('/api/orders', [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'items' => [
                                [
                                    'product_id' => $product->id,
                                    'quantity' => $quantity,
                                ],
                            ],
                        ],
                    ]);
                };
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,

            'fulfilled' => function (ResponseInterface $response) use (&$statusCounts) {
                $statusCode = $response->getStatusCode();
                $statusCounts[$statusCode] = ($statusCounts[$statusCode] ?? 0) + 1;
            },

            'rejected' => function (Throwable $reason) use (&$failedRequests, &$failedReasons) {
                $failedRequests++;

                $message = $reason->getMessage();
                $failedReasons[$message] = ($failedReasons[$message] ?? 0) + 1;
            },
        ]);

        $pool->promise()->wait();

        $product->refresh();

        $createdOrderItems = OrderItem::query()
            ->where('product_id', $product->id)
            ->count();

        $successCount = $statusCounts[201] ?? 0;
        $conflictCount = $statusCounts[409] ?? 0;
        $expectedSuccess = min($totalRequests, intdiv($initialStock, $quantity));
        $expectedFinalStock = $initialStock - ($expectedSuccess * $quantity);

        $this->info('Test summary');
        $this->line("201 Created        : {$successCount}");
        $this->line("409 Stock Conflict : {$conflictCount}");
        $this->line("Failed requests    : {$failedRequests}");
        $this->line("Created order item : {$createdOrderItems}");
        $this->line("Final stock        : {$product->stock}");
        $this->line('Status counts      : ' . json_encode($statusCounts));

        if (! empty($failedReasons)) {
            $this->newLine();
            $this->warn('Failed request reasons:');

            foreach ($failedReasons as $reason => $count) {
                $this->line("- {$count}x {$reason}");
            }
        }

        $this->newLine();

        /*
         * Validasi utama tetap dari database.
         * Response HTTP bisa timeout di local server, tapi stok dan order item tidak boleh salah.
         */
        $passed =
            $createdOrderItems === $expectedSuccess &&
            $product->stock === $expectedFinalStock &&
            $product->stock >= 0;

        if (! $passed) {
            $this->error('FAILED: stok atau jumlah order sukses tidak sesuai ekspektasi.');
            return 1;
        }

        if ($failedRequests > 0) {
            $this->warn('PASSED WITH WARNING: stok aman, tapi ada request yang gagal di sisi client/server lokal.');
            $this->warn('Coba jalankan dengan server deployment atau kurangi concurrency untuk hasil HTTP yang lebih bersih.');
            return 0;
        }

        $this->info('PASSED: API berhasil mencegah stok menjadi negatif saat request bersamaan.');

        return 0;
    }

    private function cleanupTestOrders(int $productId): void
    {
        DB::transaction(function () use ($productId) {
            $orderIds = OrderItem::query()
                ->where('product_id', $productId)
                ->pluck('order_id')
                ->unique();

            OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->delete();

            Order::query()
                ->whereIn('id', $orderIds)
                ->delete();
        });
    }
}