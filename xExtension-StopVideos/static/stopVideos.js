document.addEventListener('freshrss:openArticle', () => {
	document.querySelectorAll('.flux:not(.current) video, .flux:not(.current) audio')
		.forEach(media => media.pause());
	document.querySelectorAll('.flux:not(.current) iframe[src]')
		.forEach(frame => { frame.src = frame.src; });
});
