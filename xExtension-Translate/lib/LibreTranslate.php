<?php

declare(strict_types=1);

final class LibreTranslate {
	public function __construct(private readonly string $baseUrl) {}

	public function translate(string $text): string {
		$curl = curl_init(rtrim($this->baseUrl, '/') . '/translate');
		if ($curl === false) {
			throw new RuntimeException('Could not initialize cURL');
		}

		$payload = json_encode([
			'q' => $text,
			'source' => 'en',
			'target' => 'de',
			'format' => 'html',
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 30,
		]);

		$response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);

		if (!is_string($response) || $status < 200 || $status >= 300) {
			throw new RuntimeException($error !== '' ? $error : "LibreTranslate returned HTTP {$status}");
		}

		$data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($data) || !is_string($data['translatedText'] ?? null)) {
			throw new RuntimeException('LibreTranslate returned an invalid response');
		}
		return html_entity_decode($data['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}
