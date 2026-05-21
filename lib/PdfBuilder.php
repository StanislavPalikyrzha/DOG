<?php
declare(strict_types=1);

final class PdfBuilder
{
    public static function build(string $title, string $html, string $path): void
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</section>'], ["\n", "\n", "\n", "\n\n", "\n", "\n"], $html)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lines = preg_split('/\R/', $text) ?: [];
        $wrapped = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($line === '') {
                $wrapped[] = '';
                continue;
            }
            foreach (self::wrap($line, 90) as $chunk) {
                $wrapped[] = $chunk;
            }
        }

        $content = "BT\n/F1 12 Tf\n50 790 Td\n16 TL\n";
        $content .= '(' . self::escapeText($title) . ") Tj\nT*\n";
        foreach ($wrapped as $line) {
            $content .= '(' . self::escapeText($line) . ") Tj\nT*\n";
        }
        $content .= "ET";

        $objects = [];
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj';
        $objects[] = '4 0 obj << /Length ' . strlen($content) . " >> stream\n" . $content . "\nendstream endobj";
        $objects[] = '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
