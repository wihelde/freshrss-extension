<?php

declare(strict_types=1);

final class TranslateExtension extends Minz_Extension {
	private const DEFAULT_URL = 'http://192.168.178.115:5000';
	private const DEFAULT_SHORTCUT = 'ü';

	#[\Override]
	public function init(): void {
		parent::init();
		$this->registerController('translate');

		if (!FreshRSS_Context::hasUserConf()) {
			return;
		}

		if ($this->getUserConfigurationString('libretranslate_url') === null) {
			$this->setUserConfigurationValue('libretranslate_url', self::DEFAULT_URL);
		}
		if ($this->getUserConfigurationString('shortcut') === null) {
			$this->setUserConfigurationValue('shortcut', self::DEFAULT_SHORTCUT);
		}

		$this->registerHook(Minz_HookType::JsVars, [$this, 'jsVars']);
		Minz_View::appendScript($this->getFileUrl('translate.js'));
		Minz_View::appendStyle($this->getFileUrl('translate.css'));
	}

	/** @param array<string,mixed> $vars
	 * @return array<string,mixed>
	 */
	public function jsVars(array $vars): array {
		$vars['translate'] = [
			'endpoint' => html_entity_decode(Minz_Url::display(['c' => 'translate', 'a' => 'translate'])),
			'shortcut' => $this->shortcut(),
		];
		return $vars;
	}

	#[\Override]
	public function handleConfigureAction(): void {
		if (!Minz_Request::isPost()) {
			return;
		}

		$url = rtrim(trim(Minz_Request::paramString('libretranslate_url', plaintext: true)), '/');
		if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
			throw new Minz_ConfigurationParamException('LibreTranslate URL must be a valid HTTP(S) URL');
		}
		$shortcut = trim(Minz_Request::paramString('shortcut', plaintext: true));
		if (strlen($shortcut) > 20) {
			throw new Minz_ConfigurationParamException('Shortcut must name a single keyboard key');
		}
		$this->setUserConfigurationValue('libretranslate_url', $url);
		$this->setUserConfigurationValue('shortcut', $shortcut);
	}

	public function libreTranslateUrl(): string {
		return $this->getUserConfigurationString('libretranslate_url') ?? self::DEFAULT_URL;
	}

	public function shortcut(): string {
		return $this->getUserConfigurationString('shortcut') ?? self::DEFAULT_SHORTCUT;
	}
}
