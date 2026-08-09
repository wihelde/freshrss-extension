#!/bin/sh
set -eu

[ "$#" -eq 2 ] || { echo "Usage: $0 <user@host> <extension>" >&2; exit 2; }

target=$1
extension=$2
[ -f "$extension/metadata.json" ] || { echo "Unknown extension: $extension" >&2; exit 2; }

rsync -av \
	"$extension/" \
	"$target:/opt/freshrss/extensions/$extension/"
