#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 1 ]; then
  echo "Usage: $0 /path/to/openwrt-sdk [make-target]"
  echo "Example: $0 ~/sdk/openwrt-sdk-23.05.5-ath79-generic_gcc-12.3.0_musl.Linux-x86_64 V=s"
  exit 1
fi

SDK_DIR="$(cd "$1" && pwd)"
MAKE_TARGET="${2:-V=s}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PKG_SRC_DIR="$ROOT_DIR/openwrt-package/home-aidvor"
PKG_DST_DIR="$SDK_DIR/package/home-aidvor"
DIST_DIR="$ROOT_DIR/openwrt-package/dist"

if [ ! -f "$SDK_DIR/rules.mk" ] || [ ! -d "$SDK_DIR/include" ]; then
  echo "Error: '$SDK_DIR' не похож на OpenWrt SDK (нет rules.mk/include)."
  exit 1
fi

BOARD="$(grep -E '^CONFIG_TARGET_BOARD=' "$SDK_DIR/.config" 2>/dev/null | cut -d= -f2 | tr -d '\"' || true)"
SUBTARGET="$(grep -E '^CONFIG_TARGET_SUBTARGET=' "$SDK_DIR/.config" 2>/dev/null | cut -d= -f2 | tr -d '\"' || true)"
ARCH_PACKAGES="$(grep -E '^CONFIG_TARGET_ARCH_PACKAGES=' "$SDK_DIR/.config" 2>/dev/null | cut -d= -f2 | tr -d '\"' || true)"

echo "SDK: $SDK_DIR"
if [ -n "$BOARD" ] || [ -n "$SUBTARGET" ] || [ -n "$ARCH_PACKAGES" ]; then
  echo "Target: ${BOARD:-unknown}/${SUBTARGET:-unknown} (${ARCH_PACKAGES:-unknown})"
fi

"$ROOT_DIR/openwrt-package/scripts/sync-home-aidvor-package.sh"

rm -rf "$PKG_DST_DIR"
mkdir -p "$PKG_DST_DIR"
cp -r "$PKG_SRC_DIR/." "$PKG_DST_DIR/"

cd "$SDK_DIR"
make defconfig
make package/home-aidvor/clean
make package/home-aidvor/compile "$MAKE_TARGET"

echo
echo "Build done. IPK files:"
IPK_PATHS="$(find "$SDK_DIR/bin/packages" -type f -name 'home-aidvor_*.ipk' -print || true)"
if [ -z "$IPK_PATHS" ]; then
  echo "home-aidvor_*.ipk not found under $SDK_DIR/bin/packages"
  exit 2
fi
echo "$IPK_PATHS"

mkdir -p "$DIST_DIR"
LATEST_IPK="$(echo "$IPK_PATHS" | tail -n 1)"
cp "$LATEST_IPK" "$DIST_DIR/"
echo
echo "Copied latest IPK to: $DIST_DIR/$(basename "$LATEST_IPK")"
