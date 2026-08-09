# FreshRSS Extensions

Personal FreshRSS extensions:

- [n8n Label Webhook](xExtension-N8nWebhook/README.md) sends labeled entries to an n8n webhook.
- [Translate](xExtension-Translate/README.md) translates individual articles through LibreTranslate.

## Installation

Copy an extension directory to `FreshRSS/extensions/` and enable it under **Configuration → Extensions**.

## Deployment

Requirements:

- SSH access to the FreshRSS host
- `rsync` installed locally and remotely
- FreshRSS installed at `/opt/freshrss`

Deploy one extension:

```sh
./deploy.sh root@192.168.178.123 xExtension-Translate
```

The script requires both the SSH target and extension directory; it has no implicit default extension.
