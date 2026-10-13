<?php

declare(strict_types=1);

// FreshRSS and HTTP are test boundaries. No live configuration or network is used.
class Minz_Extension {
	public function init(): void {}
}
class Minz_ActionController {}
class Minz_Request {
	public static array $params = [];
	public static function controllerName(): string { return self::$params['controller']; }
	public static function actionName(): string { return self::$params['action']; }
	public static function isPost(): bool { return self::$params['post']; }
	public static function paramBoolean(string $name): bool { return self::$params[$name]; }
	public static function paramString(string $name): string { return self::$params[$name]; }
	public static function paramInt(string $name): int { return self::$params[$name]; }
}
class Minz_Log {
	public static array $warnings = [];
	public static function warning(string $message): void { self::$warnings[] = $message; }
}
class FreshRSS_Factory {
	public static bool $entryExists = true;
	public static function createTagDao(): object {
		return new class {
			public function searchById(int $id): object {
				return new class {
					public function name(): string { return 'trigger'; }
				};
			}
			public function getTagsForEntry(string $id): array {
				return [
					['name' => 'other', 'checked' => true],
					['name' => 'trigger', 'checked' => true],
					['name' => 'unchecked', 'checked' => false],
				];
			}
		};
	}
	public static function createEntryDao(): object {
		return new class {
			public function searchById(string $id): ?object {
				return FreshRSS_Factory::$entryExists ? new class {
					public function title(): string { return 'Hello ü'; }
					public function link(bool $absolute): string { return 'https://example.test/story'; }
				} : null;
			}
		};
	}
}

foreach (['CURLOPT_POST', 'CURLOPT_POSTFIELDS', 'CURLOPT_HTTPHEADER', 'CURLOPT_RETURNTRANSFER',
	'CURLOPT_TIMEOUT', 'CURLINFO_RESPONSE_CODE'] as $value => $name) {
	if (!defined($name)) {
		define($name, $value);
	}
}
class TestCurl {
	public static array $requests = [];
	public static int $status = 200;
	public array $options = [];
	public function __construct(public readonly string $url) {}
}
if (function_exists('curl_init')) {
	throw new LogicException('Run the webhook test through tests/php.sh to replace its HTTP transport');
} else {
	function curl_init(string $url): TestCurl { return new TestCurl($url); }
	function curl_setopt_array(TestCurl $curl, array $options): bool { $curl->options = $options; return true; }
	function curl_exec(TestCurl $curl): string { TestCurl::$requests[] = $curl; return ''; }
	function curl_errno(TestCurl $curl): int { return 0; }
	function curl_getinfo(TestCurl $curl, int $option): int { return TestCurl::$status; }
	function curl_error(TestCurl $curl): string { return ''; }
}

$directory = sys_get_temp_dir() . '/freshrss-webhook-' . bin2hex(random_bytes(8));
mkdir($directory);
copy(__DIR__ . '/../extension.php', $directory . '/extension.php');
$config = ['url' => 'https://example.test/webhook', 'token' => 'test-token', 'trigger_labels' => ['trigger']];
file_put_contents($directory . '/config.php', '<?php return ' . var_export($config, true) . ';');

try {
	require $directory . '/extension.php';
	$extension = new N8nWebhookExtension();
	$controller = new Minz_ActionController();
	$defaults = ['controller' => 'tag', 'action' => 'tagEntry', 'post' => true, 'checked' => true,
		'id_entry' => '42', 'id_tag' => 0, 'name_tag' => 'trigger'];

	foreach ([['controller' => 'index'], ['action' => 'remove'], ['post' => false],
		['checked' => false], ['name_tag' => 'unconfigured'], ['id_entry' => '']] as $changes) {
		Minz_Request::$params = array_replace($defaults, $changes);
		assert($extension->onAction($controller));
		assert(TestCurl::$requests === [], 'Unrelated actions must not send a webhook');
	}

	foreach ([0, 7] as $tagId) {
		Minz_Request::$params = array_replace($defaults, ['id_tag' => $tagId]);
		TestCurl::$requests = [];
		assert($extension->onAction($controller));
		assert(count(TestCurl::$requests) === 1);
		$request = TestCurl::$requests[0];
		assert($request->url === $config['url']);
		assert($request->options[CURLOPT_POST] === true);
		assert($request->options[CURLOPT_HTTPHEADER] === ['Authorization: test-token', 'Content-Type: application/json']);
		assert(json_decode($request->options[CURLOPT_POSTFIELDS], true, flags: JSON_THROW_ON_ERROR) === [
			'title' => 'Hello ü', 'url' => 'https://example.test/story', 'labels' => ['other', 'trigger'],
		]);
	}

	TestCurl::$requests = [];
	FreshRSS_Factory::$entryExists = false;
	assert($extension->onAction($controller));
	assert(TestCurl::$requests === [], 'Missing entries must not send a webhook');
	FreshRSS_Factory::$entryExists = true;
	TestCurl::$status = 503;
	assert($extension->onAction($controller));
	assert(str_starts_with(Minz_Log::$warnings[0], 'n8n webhook failed:'));

	unlink($directory . '/config.php');
	TestCurl::$requests = [];
	assert($extension->onAction($controller));
	assert(TestCurl::$requests === [], 'Missing configuration must not send a webhook');
	assert(end(Minz_Log::$warnings) === 'n8n webhook: config.php is missing');
} finally {
	if (is_file($directory . '/config.php')) {
		unlink($directory . '/config.php');
	}
	unlink($directory . '/extension.php');
	rmdir($directory);
}
