<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/HtmlTranslator.php';

$translator = new HtmlTranslator(static fn(string $text): string => str_replace('Hello', 'Hallo', $text));
$html = '<p id="intro">Hello ü <strong>Hello</strong> <a href="/hello">Hello</a></p><ul><li>Hello</li></ul><pre>Hello</pre>';
$actual = $translator->translate($html);

assert(str_contains($actual, '<p id="intro">Hallo ü <strong>Hallo</strong> <a href="/hello">Hello</a></p>'));
assert(str_contains($actual, '<ul><li>Hello</li></ul>'));
assert(str_contains($actual, '<pre>Hello</pre>'));
