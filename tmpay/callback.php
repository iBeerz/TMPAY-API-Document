<?php

declare(strict_types=1);

/**
 * ฝั่งรับผลการทำรายการจาก TMPAY (resp_url)
 * TMPAY เรียกไฟล์นี้แบบ GET: transaction_id, password, real_amount, status
 * ต้อง echo ข้อความที่มีคำว่า SUCCEED หรือ ERROR เพื่อให้ TMPAY รู้ว่าปลายทางได้รับแล้ว
 */

header('Content-Type: text/plain; charset=UTF-8');

$config = require __DIR__ . '/config.php';

// ตอบกลับตามสเปก TMPAY — อย่าส่ง HTML
function fail(string $reason): never
{
    echo 'ERROR|' . $reason;
    exit;
}

function ok(string $detail): never
{
    echo 'SUCCEED|' . $detail;
    exit;
}

try {
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fail('DATABASE_UNAVAILABLE');
}

// ค่าที่ TMPAY ส่งกลับมาหลังตรวจบัตรกับทรูมันนี่เสร็จ (ใช้เวลาประมาณ 1–5 นาที)
$transactionId = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['transaction_id'] ?? ''));
$password = preg_replace('/\D+/', '', (string) ($_GET['password'] ?? ''));
$realAmount = (float) ($_GET['real_amount'] ?? 0);
$status = (int) ($_GET['status'] ?? 0); // 1 สำเร็จ, 3 ใช้แล้ว, 4 รหัสผิด, 5 เป็นบัตรทรูมูฟ

if (strlen($transactionId) !== 10 || strlen($password) !== 14) {
    fail('INVALID_PAYLOAD');
}

// ล็อกแถวรายการไว้กัน callback ยิงซ้ำแล้วเติมเครดิตสองรอบ
$find = $pdo->prepare(
    'SELECT id, user_id, status, transaction_id
     FROM tmpay_transactions
     WHERE password = ?
     LIMIT 1
     FOR UPDATE'
);

$pdo->beginTransaction();

try {
    $find->execute([$password]);
    $txn = $find->fetch();

    if (!$txn) {
        $pdo->rollBack();
        fail('CARD_DOESNT_EXIST'); // ไม่มีรายการที่ร้านค้าส่งไปก่อนหน้านี้
    }

    if ($txn['transaction_id'] && $txn['transaction_id'] !== $transactionId) {
        $pdo->rollBack();
        fail('TRANSACTION_MISMATCH');
    }

    // เคยเติมสำเร็จแล้ว — ตอบ SUCCEED แต่ไม่บวกเครดิตซ้ำ
    if ($txn['status'] === 'success') {
        $pdo->commit();
        ok('ALREADY_TOPPED_UP');
    }

    // map สถานะจาก TMPAY ไปสถานะในร้านค้า
    $tmpayMap = [
        1 => 'success',
        3 => 'failed',
        4 => 'failed',
        5 => 'failed',
    ];
    $localStatus = $tmpayMap[$status] ?? 'failed';
    $message = 'TMPAY_STATUS_' . $status;

    $update = $pdo->prepare(
        'UPDATE tmpay_transactions
         SET transaction_id = ?, real_amount = ?, tmpay_status = ?, status = ?, message = ?
         WHERE id = ?'
    );
    $update->execute([
        $transactionId,
        $realAmount,
        $status,
        $localStatus,
        $message,
        $txn['id'],
    ]);

    // status = 1 เท่านั้นที่ถือว่าเติมเงินสำเร็จ แล้วค่อยบวกเครดิตผู้ใช้
    if ($status === 1) {
        if ($realAmount <= 0) {
            $pdo->rollBack();
            fail('INVALID_AMOUNT');
        }
        $credit = $pdo->prepare('UPDATE users SET credit = credit + ? WHERE id = ?');
        $credit->execute([$realAmount, $txn['user_id']]);
        $pdo->commit();
        ok('TOPPED_UP_THB_' . number_format($realAmount, 2, '.', '') . '_TO_' . $txn['user_id']);
    }

    $pdo->commit();
    fail('CARD_DOESNT_EXIST'); // บัตรไม่ผ่าน (ใช้แล้ว / รหัสผิด / ทรูมูฟ)
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail('INTERNAL_ERROR');
}
