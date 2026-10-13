# FreshRSS Extensions

Personal FreshRSS extensions:

- [n8n Label Webhook](xExtension-N8nWebhook/README.md) sends labeled entries to an n8n webhook.
- [Translate](xExtension-Translate/README.md) translates individual articles through LibreTranslate.
- [Stop Videos](xExtension-StopVideos/README.md) stops media in articles that are no longer current.

## Installation

Copy an extension directory to `FreshRSS/extensions/` and enable it under **Configuration → Extensions**.

## Deployment

Requirements:

- SSH access to the FreshRSS host
- `rsync` installed locally and remotely
- FreshRSS installed at `/opt/freshrss`

Example to deploy one extension:

```sh
./deploy.sh root@xxx.xxx.xxx.xxx xExtension-Translate
```

The script requires both the SSH target and extension directory.

## Tests

Requirements: PHP 8.3+ with DOM, cURL, mbstring and SQLite, `curl` and `tar`, Node.js 22+, and Chromium for Playwright.

From the repository root:

```sh
sh tests/php.sh
sh tests/integration.sh
npm ci
npx playwright install chromium
npm test
```

On Linux, use `npx playwright install --with-deps chromium` to install the browser's system libraries too.

Branch and merge request pipelines run PHP syntax and behavior checks on PHP 8.3 and 8.5, plus five Chromium tests, on the project's `home-linux` Proxmox runner. They cover:

- Webhook trigger selection, payload, authorization, missing entries/configuration, and HTTP failure logging.
- HTML translation, Unicode, escaping, and preservation of links, attributes, lists, tables, code, and scripts.
- LibreTranslate HTTP requests and rejection of HTTP errors, malformed JSON, and invalid responses.
- Translation and exact restoration of original article HTML/titles, retry after errors, shortcuts, and newly loaded articles.
- Stopping media in old articles while leaving the current article's media untouched.

PHP warnings and deprecations fail the behavior tests. The integration suite downloads the pinned FreshRSS release into a disposable directory, creates a local SQLite database and test user, and checks real extension/controller/hook registration, wrong and correct password login, missing/invalid CSRF rejection, and authenticated translation against a loopback LibreTranslate fixture. The test installation and servers are removed afterwards. Tests never read the private webhook configuration, contact live services, or deploy anything.

## Dependency updates

`renovate.json` manages the Playwright test dependency, the GitLab CI images and the FreshRSS release used by integration tests. The extensions have no npm or Composer runtime dependencies. Production FreshRSS and LibreTranslate server updates must be managed where those services are deployed.

- Updates wait at least **21 days** after publication. Releases without a timestamp remain blocked. For Docker Hub images, Renovate uses the image tag's last-pushed timestamp, including digest updates.
- Only stable versions are considered. npm versions and CI image digests are pinned.
- `local>wihel_de/renovate` provides manual merging, up to ten open merge requests, rebases after target-branch changes, confidential Renovate issues, and the Europe/Berlin timezone.
- Updates, including major versions, do not need Dependency Dashboard approval. The dashboard is an overview, not a release gate. The PHP 8.3 compatibility job remains on 8.3; the current PHP job and Node.js job can receive major upgrades.
- The **Playwright tests** group covers `@playwright/test`, `playwright`, and `playwright-core`, all present in the package/lockfile. These packages share the same release and browser protocol; the npm manager updates their lockfile versions together. `separateMajorMinor: false` keeps coupled updates in one merge request.
- PHP and Node.js are independent CI runtimes and are not grouped with Playwright or each other. The PHP 8.3 baseline remains separate from the current runtime to keep testing the oldest supported minor version.
- The npm and GitLab CI managers detect the package, lockfile, image tags, and digests. `npx playwright install` uses the version from `npm ci`, including its Chromium revision, so it needs no custom manager. One regex manager detects `freshrss_version` in `tests/integration.sh` using GitHub release timestamps. Extension metadata versions describe our own releases and are not third-party dependencies. There are no Dockerfiles or Composer packages.

Invite `wihel-renovate-bot` as Maintainer. The central `wihel_de/renovate` service manages project discovery, tokens, execution and webhooks; its next regular run discovers this project. The test runner assignment and CI are configured separately for this repository.

GitLab must require successful pipelines before merging and reject skipped pipelines (`only_allow_merge_if_pipeline_succeeds: true`, `allow_merge_on_skipped_pipeline: false`). These project settings are separate from `renovate.json`. No automatic merging or additional lockfile maintenance is enabled here.
