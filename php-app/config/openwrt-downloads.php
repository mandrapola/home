<?php

return [
    'base_url' => env('OPENWRT_DOWNLOAD_BASE_URL', '/downloads/openwrt'),
    'package_name' => env('OPENWRT_PACKAGE_NAME', 'home-aidvor'),
    'version' => env('OPENWRT_PACKAGE_VERSION', '24.10.5'),

    // Architectures that we expose in UI.
    'architectures' => [
        'mips_24kc',
        'arm_cortex-a7',
        'aarch64_cortex-a53',
        'x86_64',
    ],
];
