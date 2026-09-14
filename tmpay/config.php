<?php
declare(strict_types=1);

/**
 * TMPAY merchant + database settings.
 * โหมดทดสอบ: merchant_id = TEST และใช้รหัสบัตรใน TMPAY.md
 */
return [
    'tmpay' => [
        'merchant_id' => 'TEST',
        'backend_url' => 'https://www.tmpay.net/TPG/backend.php',
        'channel' => 'truemoney',
        // ต้องเป็น URL ที่ TMPAY เรียกได้ (ทดสอบจริงต้องเป็น public URL)
        'resp_url' => 'http://localhost/tmpay/callback.php',
        'timeout' => 15,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'tmpay_shop',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
