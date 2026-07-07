<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HiddenItemCommand extends Command
{
    protected $signature = 'hidden-item:solve
        {--up= : Jumlah langkah ke utara}
        {--right= : Jumlah langkah ke timur}
        {--down= : Jumlah langkah ke selatan}';

    protected $description = 'Mencari kemungkinan lokasi hidden item dari pola gerak north, east, south';

    public function handle(): int
    {
        $grid = $this->getGrid();
        $start = $this->findStart($grid);

        if (! $start) {
            $this->error('Posisi awal X tidak ditemukan.');
            return 1;
        }

        $hasStepInput = $this->option('up') !== null
            || $this->option('right') !== null
            || $this->option('down') !== null;

        $locations = $hasStepInput
            ? $this->solveWithExactSteps($grid, $start)
            : $this->solveAllPossibleSteps($grid, $start);

        if (empty($locations)) {
            $this->warn('Tidak ada kemungkinan lokasi item.');
            return 0;
        }

        $this->info('Probable item locations:');
        $this->table(
            ['Row', 'Column'],
            array_map(fn ($point) => [$point['row'] + 1, $point['col'] + 1], $locations)
        );

        $this->newLine();
        $this->info('Grid with probable item locations:');
        $this->printGrid($grid, $locations);

        return 0;
    }

    private function getGrid(): array
    {
        return [
            str_split('########'),
            str_split('#......#'),
            str_split('#.###..#'),
            str_split('#...#.##'),
            str_split('#X#....#'),
            str_split('########'),
        ];
    }

    private function findStart(array $grid): ?array
    {
        foreach ($grid as $row => $columns) {
            foreach ($columns as $col => $cell) {
                if ($cell === 'X') {
                    return ['row' => $row, 'col' => $col];
                }
            }
        }

        return null;
    }

    private function solveWithExactSteps(array $grid, array $start): array
    {
        foreach (['up', 'right', 'down'] as $option) {
            if ($this->option($option) === null || ! is_numeric($this->option($option))) {
                $this->error('Isi --up, --right, dan --down dengan angka.');
                return [];
            }
        }

        $up = (int) $this->option('up');
        $right = (int) $this->option('right');
        $down = (int) $this->option('down');

        if ($up < 0 || $right < 0 || $down < 0) {
            $this->error('Jumlah langkah tidak boleh negatif.');
            return [];
        }

        $point = $this->move($grid, $start, $up, $right, $down);

        return $point ? [$point] : [];
    }

    private function solveAllPossibleSteps(array $grid, array $start): array
    {
        $locations = [];
        $maxSteps = max(count($grid), count($grid[0]));

        // A, B, C tidak diberikan di soal, jadi semua kombinasi valid dicoba.
        for ($up = 1; $up <= $maxSteps; $up++) {
            for ($right = 1; $right <= $maxSteps; $right++) {
                for ($down = 1; $down <= $maxSteps; $down++) {
                    $point = $this->move($grid, $start, $up, $right, $down);

                    if ($point) {
                        $key = $point['row'] . ',' . $point['col'];
                        $locations[$key] = $point;
                    }
                }
            }
        }

        return array_values($locations);
    }

    private function move(array $grid, array $start, int $up, int $right, int $down): ?array
    {
        $row = $start['row'];
        $col = $start['col'];

        $moves = [
            [-1, 0, $up],
            [0, 1, $right],
            [1, 0, $down],
        ];

        foreach ($moves as [$rowDirection, $colDirection, $steps]) {
            for ($step = 0; $step < $steps; $step++) {
                $row += $rowDirection;
                $col += $colDirection;

                if (! $this->isWalkable($grid, $row, $col)) {
                    return null;
                }
            }
        }

        // Lokasi item harus berada di clear path.
        return $grid[$row][$col] === '.'
            ? ['row' => $row, 'col' => $col]
            : null;
    }

    private function isWalkable(array $grid, int $row, int $col): bool
    {
        return isset($grid[$row][$col]) && $grid[$row][$col] !== '#';
    }

    private function printGrid(array $grid, array $locations): void
    {
        $marks = [];

        foreach ($locations as $point) {
            $marks[$point['row'] . ',' . $point['col']] = true;
        }

        foreach ($grid as $row => $columns) {
            foreach ($columns as $col => $cell) {
                $key = $row . ',' . $col;

                echo isset($marks[$key]) && $cell === '.'
                    ? '$'
                    : $cell;
            }

            echo PHP_EOL;
        }
    }
}