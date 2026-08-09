# FreshRSS n8n Webhook

Sends an entry to an n8n webhook when a configured label is added.

## How it works

1. A configured label is added to an entry.
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

From the repository root, create the private configuration file:

```sh
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

Only configured trigger labels send a webhook. The payload still includes all labels attached to the entry. `config.php` is ignored by Git.
