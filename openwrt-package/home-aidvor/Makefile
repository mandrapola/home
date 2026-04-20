include $(TOPDIR)/rules.mk

PKG_NAME:=home-aidvor
PKG_VERSION:=0.1.0
PKG_RELEASE:=1
PKG_LICENSE:=MIT
PKG_MAINTAINER:=Home Aidvor

include $(INCLUDE_DIR)/package.mk

define Package/home-aidvor
  SECTION:=net
  CATEGORY:=Network
  SUBMENU:=Web Servers/Proxies
  TITLE:=Home Aidvor Gateway Service
  DEPENDS:=+node +ca-bundle +bash
endef

define Package/home-aidvor/description
 Home Aidvor edge gateway service for OpenWrt.
 Includes init service, UCI config and optional LuCI page.
endef

define Package/home-aidvor/conffiles
/etc/config/home-aidvor
endef

define Build/Compile
	# Pure JS service, nothing to compile.
endef

define Package/home-aidvor/install
	$(CP) ./files/* $(1)/
	chmod 0755 $(1)/etc/init.d/home-aidvor
endef

define Package/home-aidvor/postinst
#!/bin/sh
set -e

if [ -n "$$IPKG_INSTROOT" ]; then
  exit 0
fi

[ -x /etc/init.d/home-aidvor ] && /etc/init.d/home-aidvor enable >/dev/null 2>&1 || true
[ -x /etc/init.d/home-aidvor ] && /etc/init.d/home-aidvor restart >/dev/null 2>&1 || true
[ -x /etc/init.d/uhttpd ] && /etc/init.d/uhttpd reload >/dev/null 2>&1 || true

exit 0
endef

define Package/home-aidvor/prerm
#!/bin/sh
if [ -n "$$IPKG_INSTROOT" ]; then
  exit 0
fi
[ -x /etc/init.d/home-aidvor ] && /etc/init.d/home-aidvor stop >/dev/null 2>&1 || true
exit 0
endef

$(eval $(call BuildPackage,home-aidvor))
