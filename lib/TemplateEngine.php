<?php
declare(strict_types=1);

final class TemplateEngine
{
    public static function render(string $templateHtml, array $data): string
    {
        $safe = [];
        foreach ($data as $key => $value) {
            $safe[(string) $key] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $templateHtml = preg_replace_callback(
            '/{{if:([a-zA-Z0-9_]+)}}(.*?){{\\/if:\\1}}/s',
            static function (array $matches) use ($safe): string {
                $key = $matches[1];
                $content = $matches[2];
                return !empty($safe[$key]) ? $content : '';
