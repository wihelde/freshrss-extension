<?php

declare(strict_types=1);

final class N8nWebhookExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		parent::init();
		$this->registerHook(Minz_HookType::ActionExecute, [$this, 'onAction']);
	}

	public function onAction(Minz_ActionController $controller): bool {
		if (Minz_Request::controllerName() !== 'tag'
				|| Minz_Request::actionName() !== 'tagEntry'
				|| !Minz_Request::isPost()
				|| !Minz_Request::paramBoolean('checked')) {
			return true;
		}

		$entryId = Minz_Request::paramString('id_entry');
		$tagDao = FreshRSS_Factory::createTagDao();
		$tagId = Minz_Request::paramInt('id_tag');
		$tag = $tagId === 0
			? Minz_Request::paramString('name_tag')
			: ($tagDao->searchById($tagId)?->name() ?? '');

		$configFile = __DIR__ . '/config.php';
		if (!is_file($configFile)) {
			Minz_Log::warning('n8n webhook: config.php is missing');
			return true;
		}

		$config = require $configFile;
		if (!is_array($config) || !is_array($config['trigger_labels'] ?? null)) {
			Minz_Log::warning('n8n webhook: invalid trigger_labels');
			return true;
		}

		if ($entryId === '' || !in_array($tag, $config['trigger_labels'], true)) {
			return true;
		}

		$entry = FreshRSS_Factory::createEntryDao()->searchById($entryId);
		if ($entry === null) {
			return true;
		}

		$labels = array_column(array_filter(
			$tagDao->getTagsForEntry($entryId),
			static fn(array $item): bool => $item['checked'],
		), 'name');
		$labels[] = $tag;

		$this->send([
			'title' => $entry->title(),
			'url' => $entry->link(true),
			'labels' => array_values(array_unique($labels)),
		], $config);

		return true;
	}

	/**
	 * @param array{title:string,url:string,labels:list<string>} $payload
	 * @param array<string,mixed> $config
	 */
	private function send(array $payload, array $config): void {
		$url = is_string($config['url'] ?? null) ? $config['url'] : '';
		$token = is_string($config['token'] ?? null) ? $config['token'] : '';
		if (!str_starts_with($url, 'https://') || $token === '') {
			Minz_Log::warning('n8n webhook: invalid URL or token');
			return;
		}

		$curl = curl_init($url);
		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
			CURLOPT_HTTPHEADER => [
				'Authorization: ' . $token,
				'Content-Type: application/json',
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 3,
		]);
		curl_exec($curl);
		if (curl_errno($curl) !== 0 || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) >= 400) {
			Minz_Log::warning('n8n webhook failed: ' . curl_error($curl));
		}
	}
}
