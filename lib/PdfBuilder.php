<?php

function pdf_build($title, $html, $path)
{
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $text = str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</section>'], ["\n", "\n", "\n", "\n\n", "\n", "\n"], $html);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $lines = preg_split('/\R/', $text) ?: [];
    $wrapped = [];

    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/\s+/', ' ', $line));

        if ($line === '') {
            $wrapped[] = '';
            continue;
        }

        foreach (pdf_wrap_lines($line, 90) as $chunk) {
            $wrapped[] = $chunk;
        }
    }

    $content = "BT\n/F1 12 Tf\n50 790 Td\n16 TL\n";
    $content .= '(' . pdf_escape_text($title) . ") Tj\nT*\n";

    foreach ($wrapped as $line) {
        $content .= '(' . pdf_escape_text($line) . ") Tj\nT*\n";
    }

    $content .= "ET";

    $objects = [
        '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
        '2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj',
        '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
        '4 0 obj << /Length ' . strlen($content) . " >> stream\n" . $content . "\nendstream endobj",
        '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xref_offset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

    file_put_contents($path, $pdf);
}

function pdf_wrap_lines($text, $width)
{
    return explode("\n", wordwrap($text, $width, "\n", true));
}

function pdf_escape_text($text)
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}
