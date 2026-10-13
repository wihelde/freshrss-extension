<?php

declare(strict_types=1);

header('Content-Type: application/json');
$payload = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $payload !== [
	'q' => 'Hello ü & <world>', 'source' => 'en', 'target' => 'de', 'format' => 'html',
]) {
	http_response_code(422);
	echo json_encode(['error' => 'Unexpected translation request']);
	return;
}

switch ($_SERVER['REQUEST_URI']) {
	case '/success/translate':
		echo json_encode(['translatedText' => 'Hallo &amp; &lt;Welt&gt; ü']);
		break;
	case '/http-error/translate':
		http_response_code(503);
		echo json_encode(['error' => 'Unavailable']);
		break;
	case '/invalid-json/translate':
		echo '{';
		break;
	case '/missing-text/translate':
		echo json_encode(['error' => 'No translation']);
		break;
	case '/invalid-text/translate':
		echo json_encode(['translatedText' => 123]);
		break;
	default:
		http_response_code(404);
}
