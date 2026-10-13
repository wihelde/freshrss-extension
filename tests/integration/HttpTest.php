<?php

declare(strict_types=1);

/** Start a PHP server bound only to loopback and return its process, address and log. */
function startServer(array $arguments): array {
	$socket = stream_socket_server('tcp://127.0.0.1:0');
	assert($socket !== false);
	$address = stream_socket_get_name($socket, false);
	fclose($socket);
	$log = tmpfile();
	$process = proc_open([PHP_BINARY, '-S', $address, ...$arguments], [
		0 => ['pipe', 'r'], 1 => $log, 2 => $log,
	], $pipes);
	assert(is_resource($process));
	fclose($pipes[0]);
	for ($attempt = 0; $attempt < 100; $attempt++) {
		$connection = @stream_socket_client('tcp://' . $address, timeout: 0.1);
		if ($connection !== false) {
			fclose($connection);
			return [$process, $address, $log];
		}
		usleep(10_000);
	}
	proc_terminate($process);
	proc_close($process);
	fclose($log);
	throw new RuntimeException('Local integration server did not start');
}

/** Send a real HTTP request while retaining the session cookies on this handle. */
function request(CurlHandle $client, string $url, ?array $data = null, bool $follow = false): array {
	curl_setopt($client, CURLOPT_URL, $url);
	curl_setopt($client, CURLOPT_FOLLOWLOCATION, $follow);
	if ($data === null) {
		curl_setopt($client, CURLOPT_HTTPGET, true);
	} else {
		curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($data));
	}
	$body = curl_exec($client);
	assert(is_string($body), curl_error($client));
	return [curl_getinfo($client, CURLINFO_RESPONSE_CODE), $body];
}

/** Read the actual JSON context embedded in the FreshRSS page. */
function context(string $html): array {
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$document->loadHTML($html);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	$node = $document->getElementById('jsonVars');
	assert($node !== null, 'FreshRSS page did not include its global context');
	return json_decode($node->textContent, true, flags: JSON_THROW_ON_ERROR);
}

$fresh = $argv[1];
$servers = [];
try {
	$servers[] = startServer([__DIR__ . '/../../xExtension-Translate/tests/fixtures/libretranslate.php']);
	$servers[] = startServer(['-t', $fresh . '/p/i', __DIR__ . '/router.php']);
	$base = 'http://' . $servers[1][1];
	$configFile = $fresh . '/data/config.php';
	$config = require $configFile;
	$config['base_url'] = $base;
	file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
	$userConfigFile = $fresh . '/data/users/test/config.php';
	$config = require $userConfigFile;
	$config['extensions']['Translate']['libretranslate_url'] = 'http://' . $servers[0][1] . '/success';
	file_put_contents($userConfigFile, '<?php return ' . var_export($config, true) . ';');
	$client = curl_init();
	assert($client !== false);
	curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 5]);
	$url = $base . '/';
	[$status, $loginPage] = request($client, $url . '?c=auth&a=login');
	assert($status === 200, "Login page returned {$status}");
	$loginContext = context($loginPage);
	assert($loginContext['context']['anonymous'] === true);
	$html = '<p>Hello ü &amp; &lt;world&gt;</p>';
	[$status] = request($client, $url . '?c=translate&a=translate', [
		'_csrf' => $loginContext['context']['csrf'], 'html' => $html,
	], follow: true);
	assert($status === 403 || $status === 404, 'Unauthenticated users must not translate articles');

	// Use FreshRSS's actual nonce/challenge login protocol, without fabricating sessions.
	foreach (['wrong-password', 'test-only-password'] as $password) {
		[, $loginPage] = request($client, $url . '?c=auth&a=login');
		$loginContext = context($loginPage);
		[, $response] = request($client, $url . '?c=javascript&a=nonce&user=test');
		$nonce = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
		$hash = crypt($password, $nonce['salt1']);
		$challenge = password_hash($hash . $nonce['nonce'], PASSWORD_BCRYPT, ['cost' => 4]);
		[$status] = request($client, $url . '?c=auth&a=login', [
			'_csrf' => $loginContext['context']['csrf'], 'username' => 'test', 'challenge' => $challenge,
		]);
		assert($password === 'wrong-password' ? $status === 403 : $status === 302,
			"Unexpected login result for {$password}: {$status}");
	}
	[$status, $home] = request($client, $url);
	assert($status === 200);
	$vars = context($home);
	assert($vars['context']['anonymous'] === false, 'Real login did not create an authenticated session');
	assert(isset($vars['extensions']['translate']['endpoint']), 'Translation hook did not reach the real page');
	assert(str_contains($home, 'translate.js') && str_contains($home, 'stopVideos.js'));
	$endpoint = $vars['extensions']['translate']['endpoint'];
	$endpoint = $base . '/' . ltrim($endpoint, './');
	foreach ([[], ['_csrf' => 'invalid-token']] as $csrf) {
		[$status] = request($client, $endpoint, $csrf + ['html' => $html], follow: true);
		assert($status === 403, 'FreshRSS must reject missing or invalid CSRF tokens');
	}
	[$status, $response] = request($client, $endpoint, ['_csrf' => $vars['context']['csrf'], 'html' => $html]);
	assert($status === 200, "Authenticated translation returned {$status}: {$response}");
	assert(json_decode($response, true, flags: JSON_THROW_ON_ERROR) === ['html' => '<p>Hallo &amp; &lt;Welt&gt; ü</p>']);
	echo "FreshRSS real login, controller dispatch, CSRF rejection and translation passed\n";
} finally {
	foreach ($servers as [$process, $address, $log]) {
		proc_terminate($process);
		proc_close($process);
		fclose($log);
	}
}
