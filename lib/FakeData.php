<?php

function random_data_for_template($slug)
{
    if ($slug === 'invoice-simple') {
        return [
            'number' => 'inv-' . random_int(1000, 9999),
            'client' => 'Atelier Delta SRL',
            'supplier' => 'DoG Studio',
            'service' => 'Web documentation package',
            'amount' => random_int(1500, 4200) . ' RON',
            'notes' => 'Generated with realistic random demo values.',
        ];
    }

    if ($slug === 'catalog-card') {
        return [
            'title' => 'Portable document scanner',
            'sku' => 'DOG-' . random_int(100, 999),
            'summary' => 'Compact scanner for invoices, forms and student paperwork.',
            'price' => random_int(190, 690) . ' EUR',
            'supplier' => 'NorthBridge Supplies',
        ];
    }

    return [
        'name' => 'Ana Popescu',
        'role' => 'Junior web developer',
        'summary' => 'Builds accessible interfaces and documents data flows for small academic projects.',
        'email' => 'ana.popescu@example.com',
        'phone' => '+40 721 555 120',
        'city' => 'Iasi',
        'skills' => 'HTML, CSS, PHP, SQLite, Fetch API',
        'portfolio' => 'https://portfolio.example.test',
    ];
}
