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
