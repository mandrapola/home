# OpenWrt package: home-aidvor

Этот каталог содержит каркас opkg-пакета `home-aidvor`.

## Структура

- `home-aidvor/Makefile` — пакет OpenWrt
- `home-aidvor/postinst` — reference post-install script
- `home-aidvor/files/...` — payload, который попадет в ipk
- `scripts/sync-home-aidvor-package.sh` — синхронизирует payload из `home-openwrt/`
- `scripts/build-home-aidvor-ipk.sh` — сборка пакета через OpenWrt SDK

## Быстрая сборка через SDK

```bash
# из корня репозитория
./openwrt-package/scripts/build-home-aidvor-ipk.sh /path/to/openwrt-sdk V=s
```

Пример:

```bash
./openwrt-package/scripts/build-home-aidvor-ipk.sh ~/sdk/openwrt-sdk-23.05.5-ath79-generic_gcc-12.3.0_musl.Linux-x86_64 V=s
```

Скрипт:
1. синхронизирует файлы из `home-openwrt/` в `openwrt-package/home-aidvor/files/opt/home-openwrt/`
2. копирует пакет в SDK: `package/home-aidvor`
3. запускает `make package/home-aidvor/clean && compile`
4. копирует последний `.ipk` в `openwrt-package/dist/`

## Ручная сборка (без скрипта)

```bash
./openwrt-package/scripts/sync-home-aidvor-package.sh
cp -r openwrt-package/home-aidvor /path/to/openwrt-sdk/package/home-aidvor
cd /path/to/openwrt-sdk
make defconfig
make package/home-aidvor/compile V=s
```

## Установка на роутер

```bash
scp /path/to/openwrt-sdk/bin/packages/*/*/home-aidvor_*.ipk root@192.168.0.1:/tmp/
ssh root@192.168.0.1 'opkg install /tmp/home-aidvor_*.ipk'
```

Или через скрипт:

```bash
./openwrt-package/scripts/install-home-aidvor-ipk.sh \
  openwrt-package/dist/home-aidvor_*.ipk \
  root@192.168.0.1 \
  25077300
```

## Обновление пакета при изменении кода

Достаточно повторить:

```bash
./openwrt-package/scripts/build-home-aidvor-ipk.sh /path/to/openwrt-sdk V=s
```

Полный цикл (сборка + установка):

```bash
./openwrt-package/scripts/build-home-aidvor-ipk.sh /path/to/openwrt-sdk V=s
./openwrt-package/scripts/install-home-aidvor-ipk.sh openwrt-package/dist/home-aidvor_*.ipk root@192.168.0.1 25077300
```
