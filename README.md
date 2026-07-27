# FreshRSS n8n Webhook

FreshRSS extension that sends an entry to an n8n webhook when a configured label is added.

## How it works

1. A configured label is added to an entry in FreshRSS.
2. The extension sends the title, URL, and all labels to n8n.
3. n8n processes the data in the configured workflow.

## Webhook payload

```json
{
  "title": "Entry title",
  "url": "https://example.com/entry",
  "labels": ["label"]
}
```

The request uses `POST`, `Content-Type: application/json`, and the configured token in the `Authorization` header.

## Configuration

Create the private configuration file:

```bash
cp xExtension-N8nWebhook/config.php.example xExtension-N8nWebhook/config.php
```

Set the webhook URL, token, and trigger labels in `config.php`:

```php
<?php

return [
	'url' => 'https://n8n.example/webhook/freshrss-label',
	'token' => 'WEBHOOK_TOKEN',
	'trigger_labels' => ['label-a', 'label-b'],
];
```

Only labels listed under `trigger_labels` trigger the webhook. The payload still includes all labels attached to the entry.

`config.php` is ignored by Git.

## Deployment

Requirements:

- SSH access to the FreshRSS container
- `rsync` installed locally and in the container
- FreshRSS installed at `/opt/freshrss`

Deploy:

```bash
./deploy.sh <ip-address>
```

The script copies the extension to:

```text
/opt/freshrss/extensions/xExtension-N8nWebhook/
```

Then enable the extension in FreshRSS under **Configuration → Extensions**.

## n8n workflow

The webhook data can be processed in any way n8n supports, such as storing it in a database or spreadsheet, sending a notification, or starting another workflow.
