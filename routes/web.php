<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $baseUrl = config('app.url');

    return response()->json([
        'message' => 'Fomotoko Fullstack Engineer Assessment',
        'repository' => 'https://github.com/Al-gast/fomotoko-fullstack-engineer-assessment',
        'public_api_url' => $baseUrl,
        'tasks' => [
            'task_1' => [
                'name' => 'Online Store API',
                'description' => 'JSON API with race condition handling for flash sale orders.',
                'endpoints' => [
                    'GET ' . $baseUrl . '/api/products',
                    'POST ' . $baseUrl . '/api/products',
                    'GET ' . $baseUrl . '/api/products/{product}',
                    'GET ' . $baseUrl . '/api/orders',
                    'POST ' . $baseUrl . '/api/orders',
                    'GET ' . $baseUrl . '/api/orders/{order}',
                ],
                'race_condition_test' => 'php artisan test:race-condition --base-url=' . $baseUrl . ' --requests=50 --stock=10 --quantity=1 --concurrency=5',
            ],
            'task_2' => [
                'name' => 'Hidden Item CLI Program',
                'description' => 'Command-line solver for the hidden item grid problem. Clone the repository and run the command locally.',
                'commands' => [
                    'php artisan hidden-item:solve',
                    'php artisan hidden-item:solve --up=1 --right=2 --down=1',
                ],
            ],
        ],
    ]);
});