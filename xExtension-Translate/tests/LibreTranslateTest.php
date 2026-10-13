<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/LibreTranslate.php';

// Exercise real HTTP and JSON handling against a local fixture, never a live service.
$socket = stream_socket_server('tcp://127.0.0.1:0');
assert($socket !== false);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = tmpfile();
$server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/libretranslate.php'], [
	0 => ['pipe', 'r'], 1 => $log, 2 => $log,
], $pipes);
assert(is_resource($server));
fclose($pipes[0]);

try {
	$ready = false;
	for ($attempt = 0; $attempt < 100; $attempt++) {
		$connection = @stream_socket_client('tcp://' . $address, timeout: 0.1);
		if ($connection !== false) {
			fclose($connection);
			$ready = true;
			break;
		}
		usleep(10_000);
	}
	assert($ready, 'Local LibreTranslate fixture did not start');
	$baseUrl = 'http://' . $address;
	$client = new LibreTranslate($baseUrl . '/success/');
	assert($client->translate('Hello ü & <world>') === 'Hallo & <Welt> ü');

	foreach ([
		'http-error' => [RuntimeException::class, 'LibreTranslate returned HTTP 503'],
		'invalid-json' => [JsonException::class, 'Syntax error'],
		'missing-text' => [RuntimeException::class, 'LibreTranslate returned an invalid response'],
		'invalid-text' => [RuntimeException::class, 'LibreTranslate returned an invalid response'],
	] as $path => [$type, $message]) {
		$client = new LibreTranslate($baseUrl . '/' . $path);
		try {
			$client->translate('Hello ü & <world>');
			throw new LogicException("Invalid response was accepted: {$path}");
		} catch (RuntimeException | JsonException $error) {
			assert($error instanceof $type, "Wrong error type for {$path}");
			assert($error->getMessage() === $message);
		}
	}
} finally {
	proc_terminate($server);
	proc_close($server);
	fclose($log);
}
