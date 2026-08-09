<?php

declare(strict_types=1);

final class FreshExtension_translate_Controller extends Minz_ActionController {
	public function translateAction(): void {
		$extension = Minz_ExtensionManager::findExtension('Translate');
		if (!$extension instanceof TranslateExtension) {
			Minz_Error::error(404);
			return;
		}

		require_once $extension->getPath() . '/handler.php';
		TranslateHandler::run($extension);
	}
}
