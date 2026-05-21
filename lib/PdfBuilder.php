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
