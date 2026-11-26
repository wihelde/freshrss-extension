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
		article.querySelectorAll('iframe[src]').forEach(frame => { frame.src = frame.src; });
	}
}).observe(document, {
	subtree: true,
	attributeFilter: ['class'],
	attributeOldValue: true,
});
