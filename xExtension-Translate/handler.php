<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/LibreTranslate.php';
require_once __DIR__ . '/lib/HtmlTranslator.php';

final class TranslateHandler {
	public static function run(TranslateExtension $extension): never {
		header('Content-Type: application/json; charset=UTF-8');

		try {
			if (!FreshRSS_Auth::hasAccess() || !Minz_Request::isPost()) {
				throw new RuntimeException('Access denied');
			}

			$html = is_string($_POST['html'] ?? null) ? $_POST['html'] : '';
			if ($html === '' || strlen($html) > 1_000_000) {
				throw new InvalidArgumentException('Article HTML is empty or too large');
			}

			$client = new LibreTranslate($extension->libreTranslateUrl());
			$translator = new HtmlTranslator([$client, 'translate']);
			echo json_encode(['html' => $translator->translate($html)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		} catch (InvalidArgumentException $e) {
			http_response_code(400);
			echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
		} catch (Throwable $e) {
			Minz_Log::warning('Translate extension: ' . $e->getMessage());
			http_response_code(502);
			echo json_encode(['error' => 'LibreTranslate ist nicht erreichbar.'], JSON_UNESCAPED_UNICODE);
		}
		exit;
	}
}
