<?php
declare(strict_types=1);

final class FakeData
{
    public static function forTemplate(string $slug): array
    {
        return match ($slug) {
            'invoice-simple' => [
                'number' => 'inv-' . random_int(1000, 9999),
                'client' => 'Atelier Delta SRL',
                'supplier' => 'DoG Studio',
                'service' => 'Web documentation package',
                'amount' => random_int(1500, 4200) . ' RON',
                'notes' => 'Generated with realistic random demo values.',
            ],
            'catalog-card' => [
                'title' => 'Portable document scanner',
                'sku' => 'DOG-' . random_int(100, 999),
                'summary' => 'Compact scanner for invoices, forms and student paperwork.',
                'price' => random_int(190, 690) . ' EUR',
                'supplier' => 'NorthBridge Supplies',
            ],
            default => [
                'name' => 'Ana Popescu',
                'role' => 'Junior web developer',
