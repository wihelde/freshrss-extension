# FreshRSS Translate

Translates the visible text of individual FreshRSS articles from English to German through a local LibreTranslate server. Article HTML and its original version remain in browser memory only; nothing is written to the FreshRSS database.

## Installation

Copy `xExtension-Translate` to `FreshRSS/extensions/`, enable **Translate** in **Configuration → Extensions**, then configure the LibreTranslate URL.

The extension shortcut defaults to <kbd>Ü</kbd> and can be changed or disabled on the same configuration page.

The browser posts the article HTML and FreshRSS CSRF token to the registered extension controller. `handler.php` parses the fragment, translates eligible text nodes via `POST /translate`, and returns `{ "html": "..." }`. Links, images, tables, lists, code, attributes, classes, and IDs are left untouched.

LibreTranslate must be reachable from the FreshRSS PHP process and support this request:

```json
{"q":"Text","source":"en","target":"de","format":"html"}
```

## Check

```sh
php -d zend.assertions=1 -d assert.exception=1 tests/HtmlTranslatorTest.php
```
