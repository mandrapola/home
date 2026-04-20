#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SRC_DIR="$ROOT_DIR/home-openwrt"
PKG_FILES_DIR="$ROOT_DIR/openwrt-package/home-aidvor/files"

mkdir -p "$PKG_FILES_DIR/etc/init.d" \
         "$PKG_FILES_DIR/etc/config" \
         "$PKG_FILES_DIR/usr/lib/lua/luci/controller" \
         "$PKG_FILES_DIR/usr/lib/lua/luci/model/cbi" \
         "$PKG_FILES_DIR/opt/home-openwrt"

cp "$SRC_DIR/scripts/openwrt/home-aidvor.init" "$PKG_FILES_DIR/etc/init.d/home-aidvor"
cp "$SRC_DIR/scripts/openwrt/home-aidvor.uci" "$PKG_FILES_DIR/etc/config/home-aidvor"
cp "$SRC_DIR/scripts/openwrt/luci/controller/home_aidvor.lua" "$PKG_FILES_DIR/usr/lib/lua/luci/controller/home_aidvor.lua"
cp "$SRC_DIR/scripts/openwrt/luci/model/cbi/home_aidvor.lua" "$PKG_FILES_DIR/usr/lib/lua/luci/model/cbi/home_aidvor.lua"

rm -rf "$PKG_FILES_DIR/opt/home-openwrt/src" \
       "$PKG_FILES_DIR/opt/home-openwrt/config" \
       "$PKG_FILES_DIR/opt/home-openwrt/scripts"
mkdir -p "$PKG_FILES_DIR/opt/home-openwrt/src" \
         "$PKG_FILES_DIR/opt/home-openwrt/config" \
         "$PKG_FILES_DIR/opt/home-openwrt/scripts"

cp -r "$SRC_DIR/src/." "$PKG_FILES_DIR/opt/home-openwrt/src/"
cp -r "$SRC_DIR/config/." "$PKG_FILES_DIR/opt/home-openwrt/config/"
cp -r "$SRC_DIR/scripts/." "$PKG_FILES_DIR/opt/home-openwrt/scripts/"
cp "$SRC_DIR/package.json" "$PKG_FILES_DIR/opt/home-openwrt/package.json"
cp "$SRC_DIR/README.md" "$PKG_FILES_DIR/opt/home-openwrt/README.md"

chmod +x "$PKG_FILES_DIR/etc/init.d/home-aidvor"

echo "Synced package payload from $SRC_DIR to $PKG_FILES_DIR"
