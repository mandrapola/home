#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 2 ]; then
  echo "Usage: $0 /path/to/home-aidvor_*.ipk root@ROUTER_IP [password]"
  echo "Example: $0 openwrt-package/dist/home-aidvor_0.1.0-1_mips_24kc.ipk root@192.168.0.1 25077300"
  exit 1
fi

IPK_PATH="$1"
ROUTER="$2"
PASSWORD="${3:-}"

if [ ! -f "$IPK_PATH" ]; then
  echo "Error: IPK not found: $IPK_PATH"
  exit 1
fi

REMOTE_IPK="/tmp/$(basename "$IPK_PATH")"

if [ -n "$PASSWORD" ]; then
  sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no "$IPK_PATH" "$ROUTER:$REMOTE_IPK"
  sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no "$ROUTER" \
    "opkg install '$REMOTE_IPK' || opkg install --force-reinstall '$REMOTE_IPK'; /etc/init.d/home-aidvor restart; /etc/init.d/home-aidvor status"
else
  scp "$IPK_PATH" "$ROUTER:$REMOTE_IPK"
  ssh "$ROUTER" \
    "opkg install '$REMOTE_IPK' || opkg install --force-reinstall '$REMOTE_IPK'; /etc/init.d/home-aidvor restart; /etc/init.d/home-aidvor status"
fi

echo "Installed: $REMOTE_IPK on $ROUTER"
