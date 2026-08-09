<?php

declare(strict_types=1);

final class HtmlTranslator {
	private const SKIPPED_ELEMENTS = ['a', 'img', 'table', 'ul', 'ol', 'li', 'pre', 'code', 'script', 'style'];

	/** @param callable(string):string $translate */
	public function __construct(private readonly mixed $translate) {}

	public function translate(string $html): string {
		$dom = new DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		$loaded = $dom->loadHTML(
			'<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="translate-root">' . $html . '</div></body></html>',
			LIBXML_HTML_NODEFDTD,
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$loaded) {
			throw new InvalidArgumentException('Article HTML could not be parsed');
		}

		$root = $dom->getElementById('translate-root');
		if (!$root instanceof DOMElement) {
			throw new RuntimeException('Article root is missing');
		}
		$this->translateNode($root);

		$result = '';
		foreach ($root->childNodes as $child) {
			$result .= $dom->saveHTML($child);
		}
		return $result;
	}

	private function translateNode(DOMNode $node): void {
		if ($node instanceof DOMElement && in_array(strtolower($node->tagName), self::SKIPPED_ELEMENTS, true)) {
			return;
		}
		if ($node instanceof DOMText && trim($node->nodeValue ?? '') !== '') {
			$node->nodeValue = ($this->translate)($node->nodeValue ?? '');
			return;
		}
		foreach ($node->childNodes as $child) {
			$this->translateNode($child);
		}
	}
}
