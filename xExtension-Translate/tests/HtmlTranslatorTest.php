<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/HtmlTranslator.php';

$translator = new HtmlTranslator(static fn(string $text): string => str_replace('Hello', 'Hallo', $text));
$html = '<p id="intro" class="article" title="Hello">Hello ü <strong>Hello</strong> <a href="/hello">Hello</a></p>'
	. '<ul><li>Hello</li></ul><ol><li>Hello</li></ol><pre>Hello</pre><code>Hello</code>'
	. '<table><tr><td>Hello</td></tr></table><script>const text = "Hello";</script><style>.Hello { color: red; }</style>'
	. '<img src="/hello.png" alt="Hello">';
$actual = $translator->translate($html);

assert(str_contains($actual, '<p id="intro" class="article" title="Hello">Hallo ü <strong>Hallo</strong> <a href="/hello">Hello</a></p>'));
foreach (['<ul><li>Hello</li></ul>', '<ol><li>Hello</li></ol>', '<pre>Hello</pre>', '<code>Hello</code>',
	'<table><tr><td>Hello</td></tr></table>', '<script>const text = "Hello";</script>',
	'<style>.Hello { color: red; }</style>', '<img src="/hello.png" alt="Hello">'] as $skipped) {
	assert(str_contains($actual, $skipped), "Protected HTML changed: {$skipped}");
}

$calls = [];
$translator = new HtmlTranslator(static function (string $text) use (&$calls): string {
	$calls[] = $text;
	return 'Grüße & <Text>';
});
assert($translator->translate(" \n<p>Hello</p> \n") === " \n<p>Grüße &amp; &lt;Text&gt;</p> \n");
assert($calls === ['Hello'], 'Whitespace-only nodes must not be sent for translation');
assert($translator->translate('') === '');

$translator = new HtmlTranslator(static fn(string $text): string => throw new RuntimeException('Service unavailable'));
try {
	$translator->translate('<p>Hello</p>');
	throw new LogicException('Translation failures must reach the caller');
} catch (RuntimeException $error) {
	assert($error->getMessage() === 'Service unavailable');
}
