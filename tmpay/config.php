<?php

declare(strict_types=1);

/**
 * ค่าตั้งร้านค้า TMPAY และฐานข้อมูล
 * โหมดทดสอบ: merchant_id = TEST
 */
return [
    'tmpay' => [
        'merchant_id' => 'TEST', // รหัสร้านค้า 10 หลัก — ใช้ TEST เมื่อทดลองไม่ใช้บัตรจริง
        'backend_url' => 'https://www.tmpay.net/TPG/backend.php', // จุดรับรหัสบัตรของ TMPAY (GET)
        // ค่าที่ส่งในพารามิเตอร์ channel ตามเอกสาร
        'channels' => [
            'truemoney' => 'True Money Card',
            'razer_gold_pin' => 'Razer Gold PIN',
        ],
        'default_channel' => 'truemoney',
        // URL ที่ TMPAY จะยิงผลกลับมา (ต้องเป็น public URL จริง ถึงจะได้รับ callback)
        'resp_url' => 'http://localhost/tmpay/callback.php',
        'timeout' => 15, // วินาทีที่รอ TMPAY ตอบว่าได้รับรายการแล้ว
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
