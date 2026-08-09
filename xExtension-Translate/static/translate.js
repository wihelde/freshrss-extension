(function () {
	'use strict';

	const originals = new WeakMap();
	let shortcut = '';

	function setButton(button, icon, label, busy) {
		button.textContent = icon;
		button.title = label + (shortcut ? ' (Tastatur: ' + shortcut + ')' : '');
		button.setAttribute('aria-label', label);
		button.toggleAttribute('aria-busy', Boolean(busy));
	}

	function addButtons(root) {
		root.querySelectorAll('.flux').forEach(function (flux) {
			if (flux.querySelector('.translate-controls')) return;
			const content = flux.querySelector('.flux_content .content');
			if (!content) return;
			const text = content.querySelector(':scope > .text');
			const title = content.querySelector(':scope > header h1.title a');
			const header = flux.querySelector(':scope > .flux_header') || content.querySelector('.article-header-topline');
			if (!text || !title || !header) return;

			const actions = header.querySelectorAll('.item.manage');
			const host = header.querySelector('.item.website') || actions[actions.length - 1];
			if (!host) return;

			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'item-element translate-controls translate-button';
			setButton(button, '🌐', 'Artikel ins Deutsche übersetzen');
			host.prepend(button);
			const titles = [title, flux.querySelector(':scope > .flux_header .item.titleAuthorSummaryDate > a.title')].filter(Boolean);
			button.addEventListener('click', function (event) {
				event.stopPropagation();
				toggle(text, titles, button);
			});
		});
	}

	function setTitle(element, value) {
		const textNode = Array.from(element.childNodes).find(function (node) { return node.nodeType === Node.TEXT_NODE; });
		if (textNode) textNode.nodeValue = value;
		else element.prepend(value);
	}

	async function toggle(text, titles, button) {
		const state = originals.get(text);
		if (state && state.translated) {
			text.innerHTML = state.html;
			state.titles.forEach(function (title) { title.element.innerHTML = title.html; });
			state.translated = false;
			setButton(button, '🌐', 'Artikel ins Deutsche übersetzen');
			return;
		}

		button.disabled = true;
		setButton(button, '⏳', 'Artikel wird ins Deutsche übersetzt', true);
		text.parentElement.querySelector(':scope > .translate-error')?.remove();
		const original = state || {
			html: text.innerHTML,
			title: titles[0].textContent.trim(),
			titles: titles.map(function (title) { return { element: title, html: title.innerHTML }; }),
		};
		const payload = document.createElement('div');
		payload.innerHTML = '<h1 data-translate-title></h1><div data-translate-body></div>';
		payload.querySelector('[data-translate-title]').textContent = original.title;
		payload.querySelector('[data-translate-body]').innerHTML = original.html;

		try {
			const body = new URLSearchParams({ html: payload.innerHTML, _csrf: window.context.csrf });
			const response = await fetch(window.context.extensions.translate.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
				body: body.toString(),
			});
			const data = await response.json();
			if (!response.ok || typeof data.html !== 'string') throw new Error(data.error || 'Übersetzung fehlgeschlagen.');

			const translated = document.createElement('template');
			translated.innerHTML = data.html;
			const translatedTitle = translated.content.querySelector('[data-translate-title]');
			const translatedBody = translated.content.querySelector('[data-translate-body]');
			if (!translatedTitle || !translatedBody) throw new Error('Ungültige Übersetzung empfangen.');

			text.innerHTML = translatedBody.innerHTML;
			titles.forEach(function (title) { setTitle(title, translatedTitle.textContent.trim()); });
			original.translated = true;
			originals.set(text, original);
			setButton(button, '🇬🇧', 'Originalartikel anzeigen');
		} catch (error) {
			const message = document.createElement('p');
			message.className = 'translate-error';
			message.setAttribute('role', 'alert');
			message.textContent = error instanceof Error ? error.message : 'Übersetzung fehlgeschlagen.';
			text.before(message);
			setButton(button, '🌐', 'Artikel ins Deutsche übersetzen');
		} finally {
			button.disabled = false;
		}
	}

	function init() {
		shortcut = (window.context.extensions.translate.shortcut || '').toUpperCase();
		addButtons(document);
		document.body.addEventListener('freshrss:load-more', function () { addButtons(document); });

		if (shortcut) {
			document.addEventListener('keydown', function (event) {
				let key = (event.key.trim() || event.code || 'Space').toUpperCase();
				if (key === 'SPACEBAR') key = 'SPACE';
				else if (key === 'DEL') key = 'DELETE';
				else if (key === 'ESC') key = 'ESCAPE';
				if (event.ctrlKey || event.metaKey || (event.altKey && event.shiftKey) ||
						event.target.closest('input, select, textarea') || key !== shortcut) return;

				const button = document.querySelector('.flux.current.active .translate-button');
				if (button) {
					button.click();
					event.preventDefault();
				}
			});
		}
	}

	if (window.context?.extensions) init();
	else document.addEventListener('freshrss:globalContextLoaded', init, { once: true });
}());
