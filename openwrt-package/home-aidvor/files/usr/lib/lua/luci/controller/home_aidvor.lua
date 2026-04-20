module("luci.controller.home_aidvor", package.seeall)

function index()
  if not nixio.fs.access("/etc/config/home-aidvor") then
    return
  end

  entry({"admin", "services", "home-aidvor"}, cbi("home_aidvor"), _("Home Aidvor"), 60).dependent = true
end
