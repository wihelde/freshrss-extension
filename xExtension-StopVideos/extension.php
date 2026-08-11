<?php

declare(strict_types=1);

final class StopVideosExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		parent::init();
		Minz_View::appendScript($this->getFileUrl('stopVideos.js'));
	}
}
