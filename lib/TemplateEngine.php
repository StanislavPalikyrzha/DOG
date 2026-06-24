<?php

function template_render($template_html, $data)
{
    $safe = [];

    foreach ($data as $key => $value) {
        $safe[(string) $key] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $template_html = preg_replace_callback(
        '/{{if:([a-zA-Z0-9_]+)}}(.*?){{\\/if:\\1}}/s',
        function ($matches) use ($safe) {
            return empty($safe[$matches[1]]) ? '' : $matches[2];
        },
        $template_html
    );

    $template_html = preg_replace_callback(
        '/{{(uppercase|lowercase):([a-zA-Z0-9_]+)}}/',
        function ($matches) use ($safe) {
            $value = isset($safe[$matches[2]]) ? $safe[$matches[2]] : '';

            return $matches[1] === 'uppercase' ? strtoupper($value) : strtolower($value);
        },
        $template_html
    );

    $template_html = str_replace('{{today}}', date('d.m.Y'), $template_html);
    $template_html = str_replace('{{now}}', date('d.m.Y H:i'), $template_html);

    return preg_replace_callback(
        '/{{([a-zA-Z0-9_]+)}}/',
        function ($matches) use ($safe) {
            return isset($safe[$matches[1]]) ? $safe[$matches[1]] : '';
        },
        $template_html
    );
}

function wrap_document($title, $template_css, $body)
{
    return '<article class="generated-document"><style>.generated-document{font-family:Georgia,serif;max-width:900px;margin:0 auto;background:#fff;border:1px solid #d7ccbd;padding:2rem;color:#231f1a}ul{padding-left:1.25rem}p,li,small{line-height:1.5}h1,h2,h3{margin:0 0 .7rem}' .
        $template_css . '</style><header><small>DoG generated document</small><h1>' .
        htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
        '</h1></header>' . $body . '</article>';
}
