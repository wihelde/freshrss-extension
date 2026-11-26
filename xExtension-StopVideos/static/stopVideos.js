// Skipping an article changes .current without firing freshrss:openArticle.
new MutationObserver(mutations => {
	const previousArticles = new Set();
	for (const { target, oldValue } of mutations) {
		if (target.matches('.flux:not(.current)') && oldValue?.split(/\s+/).includes('current')) {
			previousArticles.add(target);
		}
	}

	for (const article of previousArticles) {
		article.querySelectorAll('video, audio').forEach(media => media.pause());
		article.querySelectorAll('iframe[src]').forEach(frame => {
			// Detach the player immediately, even when a hidden iframe would defer reloading.
			const { parentNode, nextSibling } = frame;
			frame.remove();
			parentNode.insertBefore(frame, nextSibling);
		});
	}
}).observe(document, {
	subtree: true,
	attributeFilter: ['class'],
	attributeOldValue: true,
});
