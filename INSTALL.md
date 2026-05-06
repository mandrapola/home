# INSTALL: home-openwrt on user router

This guide is for end users who install `home-aidvor` on their own OpenWrt router.

## 1) Check router architecture

SSH to router and run:

```sh
ubus call system board
opkg print-architecture
```

Use the architecture with the highest priority from `opkg print-architecture` (for example `mips_24kc`, `arm_cortex-a7`, `aarch64_cortex-a53`, etc.).

## 2) Download `.ipk`

Download package from site block **"Скачать .ipk"**:

- `https://home.aidvor.ru/home-arduino/openwrt-proxy`

Pick the file for your architecture.

## 3) Upload package to router

From your PC:

```sh
scp home-aidvor_<version>_<arch>.ipk root@192.168.0.1:/tmp/
```

## 4) Install package on router

On router:

```sh
opkg update
opkg install /tmp/home-aidvor_<version>_<arch>.ipk
```

## 5) Start and enable service

```sh
/etc/init.d/home-aidvor enable
/etc/init.d/home-aidvor start
```

## 6) Health check

```sh
wget -qO- http://127.0.0.1:3000/api/system/status
```

Expected: JSON with `"ok":true`.

## 7) Pair gateway with cloud

1. Open LuCI: `Services -> Home Aidvor`.
2. Run gateway authorization.
3. Confirm pairing code in user account on cloud.
4. Wait status `approved`.

## Troubleshooting

- If `opkg` says wrong architecture: download another build.
- If cloud is unreachable: verify `cloud_base_url` and DNS on router.
- If controller cannot post data: verify controller host/port point to gateway LAN IP and gateway port.
