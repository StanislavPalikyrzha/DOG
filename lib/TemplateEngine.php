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
            },
            $templateHtml
        ) ?? $templateHtml;

        $templateHtml = preg_replace_callback(
            '/{{(uppercase|lowercase):([a-zA-Z0-9_]+)}}/',
            static function (array $matches) use ($safe): string {
                $value = $safe[$matches[2]] ?? '';
                return $matches[1] === 'uppercase' ? strtoupper($value) : strtolower($value);
            },
            $templateHtml
        ) ?? $templateHtml;

        $templateHtml = str_replace('{{today}}', date('d.m.Y'), $templateHtml);
        $templateHtml = str_replace('{{now}}', date('d.m.Y H:i'), $templateHtml);

        return preg_replace_callback(
            '/{{([a-zA-Z0-9_]+)}}/',
            static function (array $matches) use ($safe): string {
                return $safe[$matches[1]] ?? '';
            },
            $templateHtml
        ) ?? $templateHtml;
    }

    public static function wrapDocument(string $title, string $templateCss, string $body): string
    {
        return '<article class="generated-document"><style>.generated-document{font-family:Georgia,serif;max-width:900px;margin:0 auto;background:#fff;border:1px solid #d7ccbd;padding:2rem;color:#231f1a}ul{padding-left:1.25rem}p,li,small{line-height:1.5}h1,h2,h3{margin:0 0 .7rem}' .
            $templateCss . '</style><header><small>DoG generated document</small><h1>' .
            htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
            '</h1></header>' . $body . '</article>';
    }
}


