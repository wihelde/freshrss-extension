import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const translateScript = readFileSync(new URL('../../xExtension-Translate/static/translate.js', import.meta.url), 'utf8');
const stopVideosScript = readFileSync(new URL('../../xExtension-StopVideos/static/stopVideos.js', import.meta.url), 'utf8');

function article(id = 'article', state = 'current active') {
  return `<article id="${id}" class="flux ${state}">
    <div class="flux_header">
      <div class="item titleAuthorSummaryDate"><a class="title">Hello story</a></div>
      <div class="item website"></div>
    </div>
    <div class="flux_content"><div class="content">
      <header><h1 class="title"><a href="/story">Hello story<span class="marker">*</span></a></h1></header>
      <div class="text"><p>Hello <strong>world</strong> ü</p><a href="/original">Original link</a></div>
    </div></div>
  </article>`;
}

async function openArticles(page) {
  await page.route('https://freshrss.test/', route => route.fulfill({
    contentType: 'text/html',
    body: `${article()}<input aria-label="Search">`,
  }));
  await page.goto('https://freshrss.test/');
  await page.evaluate(() => {
    window.context = {
      csrf: 'test-csrf',
      extensions: { translate: { endpoint: '/translate', shortcut: 'ü' } },
    };
  });
  await page.addScriptTag({ content: translateScript });
}

test('translation sends the CSRF token and restores the exact original article and titles', async ({ page }) => {
  await openArticles(page);
  const text = page.locator('.text');
  const title = page.locator('h1.title a');
  const original = await text.innerHTML();
  const originalTitle = await title.innerHTML();
  let requestBody;
  await page.route('**/translate', async route => {
    requestBody = new URLSearchParams(route.request().postData());
    expect(route.request().method()).toBe('POST');
    await route.fulfill({ json: {
      html: '<h1 data-translate-title>Hallo Geschichte</h1><div data-translate-body><p>Hallo <strong>Welt</strong> ü</p><a href="/original">Original link</a></div>',
    } });
  });

  await page.getByRole('button', { name: 'Artikel ins Deutsche übersetzen' }).click();
  await expect(text).toContainText('Hallo Welt ü');
  await expect(title).toContainText('Hallo Geschichte');
  await expect(title.locator('.marker')).toHaveText('*');
  await expect(page.locator('.flux_header a.title')).toHaveText('Hallo Geschichte');
  expect(requestBody.get('_csrf')).toBe('test-csrf');
  expect(requestBody.get('html')).toContain(original);

  await page.getByRole('button', { name: 'Originalartikel anzeigen' }).click();
  expect(await text.innerHTML()).toBe(original);
  expect(await title.innerHTML()).toBe(originalTitle);
  await expect(page.locator('.flux_header a.title')).toHaveText('Hello story');
});

for (const failure of [
  { name: 'server failure', status: 502, json: { error: 'LibreTranslate ist nicht erreichbar.' } },
  { name: 'invalid translated HTML', status: 200, json: { html: '<p>Missing article structure</p>' } },
]) {
  test(`${failure.name} preserves the original and allows retry`, async ({ page }) => {
    await openArticles(page);
    const text = page.locator('.text');
    const original = await text.innerHTML();
    const button = page.getByRole('button', { name: 'Artikel ins Deutsche übersetzen' });
    let attempts = 0;
    await page.route('**/translate', route => {
      attempts++;
      return route.fulfill(attempts === 1 ? { status: failure.status, json: failure.json } : {
        json: { html: '<h1 data-translate-title>Hallo</h1><div data-translate-body>Erfolg</div>' },
      });
    });

    await button.click();
    await expect(page.getByRole('alert')).toBeVisible();
    await expect(button).toBeEnabled();
    expect(await text.innerHTML()).toBe(original);
    await button.click();
    await expect(text).toHaveText('Erfolg');
    await expect(page.getByRole('alert')).toHaveCount(0);
    expect(attempts).toBe(2);
  });
}

test('the shortcut ignores typing and new articles get one translation button', async ({ page }) => {
  await openArticles(page);
  let requests = 0;
  await page.route('**/translate', route => {
    requests++;
    return route.fulfill({ json: {
      html: '<h1 data-translate-title>Hallo</h1><div data-translate-body>Übersetzt</div>',
    } });
  });
  await page.evaluate(() => {
    const fetch = window.fetch.bind(window);
    window.translationRequests = 0;
    window.fetch = (...args) => { window.translationRequests++; return fetch(...args); };
  });
  await page.getByRole('textbox', { name: 'Search' }).fill('ü');
  await page.getByRole('textbox', { name: 'Search' }).dispatchEvent('keydown', { key: 'ü' });
  expect(await page.evaluate(() => window.translationRequests)).toBe(0);
  await page.getByRole('textbox', { name: 'Search' }).blur();
  await page.locator('body').dispatchEvent('keydown', { key: 'ü' });
  await expect(page.locator('#article .text')).toHaveText('Übersetzt');
  expect(requests).toBe(1);

  await page.evaluate(html => {
    document.body.insertAdjacentHTML('beforeend', html);
    document.body.dispatchEvent(new Event('freshrss:load-more'));
    document.body.dispatchEvent(new Event('freshrss:load-more'));
  }, article('new-article', ''));
  await expect(page.locator('#new-article .translate-button')).toHaveCount(1);
  await expect(page.locator('#article .translate-button')).toHaveCount(1);
});

test('switching articles stops old media and leaves current media untouched', async ({ page }) => {
  await page.route('https://freshrss.test/**', route => route.fulfill({ body: '' }));
  await page.goto('https://freshrss.test/');
  await page.setContent(`<div class="flux"><video id="old-video"></video><audio id="old-audio"></audio>
    <iframe id="old-frame" src="/video"></iframe></div>
    <div class="flux current"><video id="current-video"></video><audio id="current-audio"></audio>
    <iframe id="current-frame" src="/video"></iframe></div>`);
  await page.evaluate(() => {
    window.stopped = [];
    document.querySelectorAll('video, audio').forEach(media => {
      media.pause = () => window.stopped.push(media.id);
    });
    document.querySelectorAll('iframe').forEach(frame => {
      const src = frame.src;
      Object.defineProperty(frame, 'src', {
        get: () => src,
        set: value => { if (value === src) window.stopped.push(frame.id); },
      });
    });
  });
  await page.addScriptTag({ content: stopVideosScript });
  await page.evaluate(() => document.dispatchEvent(new Event('freshrss:openArticle')));
  expect(await page.evaluate(() => window.stopped)).toEqual(['old-video', 'old-audio', 'old-frame']);
});
