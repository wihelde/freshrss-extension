#!/bin/sh
set -eu

[ "$#" -eq 1 ] || { echo "Usage: $0 <ip-address>" >&2; exit 2; }

rsync -av \
	xExtension-N8nWebhook/ \
	"root@$1:/opt/freshrss/extensions/xExtension-N8nWebhook/"
