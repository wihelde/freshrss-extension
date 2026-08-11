import assert from 'node:assert/strict';

let onOpenArticle;
let pauses = 0;
let reloads = 0;
const frame = {
	get src() { return 'https://example.com/video'; },
	set src(value) {
		assert.equal(value, 'https://example.com/video');
		reloads++;
	},
};

globalThis.document = {
	addEventListener(name, listener) {
		assert.equal(name, 'freshrss:openArticle');
		onOpenArticle = listener;
	},
	querySelectorAll(selector) {
		return selector.includes('iframe') ? [frame] : [{ pause: () => pauses++ }];
	},
};

await import('../static/stopVideos.js');
onOpenArticle();

assert.equal(pauses, 1);
assert.equal(reloads, 1);
