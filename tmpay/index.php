<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/config.php';

function db(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
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
    return $pdo;
}

function current_user(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, username, credit FROM users ORDER BY id ASC LIMIT 1');
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('ไม่พบผู้ใช้ในตาราง users — นำเข้า database.sql ก่อน');
    }
    return $user;
}

function flash(): ?array
{
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

function send_tmpay(array $tmpay, string $password): string
{
    $url = $tmpay['backend_url'] . '?' . http_build_query([
        'merchant_id' => $tmpay['merchant_id'],
        'password' => $password,
        'resp_url' => $tmpay['resp_url'],
        'channel' => $tmpay['channel'],
    ]);

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_TIMEOUT => (int) $tmpay['timeout'],
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false) {
            throw new RuntimeException('เชื่อมต่อ TMPAY ไม่ได้: ' . $error);
        }
        return trim((string) $body);
    }

    $context = stream_context_create([
        'http' => ['timeout' => (int) $tmpay['timeout']],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('เชื่อมต่อ TMPAY ไม่ได้ (file_get_contents)');
    }
    return trim($body);
}

$pdo = db($config);
$user = current_user($pdo);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = preg_replace('/\D+/', '', (string) ($_POST['password'] ?? ''));

    if (strlen($password) !== 14) {
        $error = 'รหัสบัตรต้องเป็นตัวเลข 14 หลัก';
    } else {
        try {
            $exists = $pdo->prepare('SELECT id, status FROM tmpay_transactions WHERE password = ? LIMIT 1');
            $exists->execute([$password]);
            $row = $exists->fetch();
            if ($row && in_array($row['status'], ['success', 'awaiting_result', 'pending'], true)) {
                $error = 'รหัสบัตรนี้ถูกส่งเข้าระบบแล้ว';
            } else {
                if ($row) {
                    $pdo->prepare('DELETE FROM tmpay_transactions WHERE id = ?')->execute([$row['id']]);
                }

                $insert = $pdo->prepare(
                    'INSERT INTO tmpay_transactions (user_id, password, channel, status)
                     VALUES (?, ?, ?, ?)'
                );
                $insert->execute([$user['id'], $password, $config['tmpay']['channel'], 'pending']);

                $response = send_tmpay($config['tmpay'], $password);
                $parts = explode('|', $response, 2);
                $code = strtoupper(trim($parts[0] ?? ''));
                $detail = trim($parts[1] ?? '');

                if ($code === 'SUCCEED') {
                    $pdo->prepare(
                        "UPDATE tmpay_transactions
                         SET transaction_id = ?, status = 'awaiting_result', message = ?
                         WHERE password = ?"
                    )->execute([$detail, $response, $password]);

                    $_SESSION['flash'] = [
                        'type' => 'ok',
                        'text' => 'รับรายการสำเร็จ รหัสอ้างอิง ' . $detail . ' รอผลจาก TMPAY ประมาณ 1–5 นาที',
                    ];
                    header('Location: index.php');
                    exit;
                }

                $pdo->prepare(
                    "UPDATE tmpay_transactions SET status = 'failed', message = ? WHERE password = ?"
                )->execute([$response, $password]);
                $error = 'TMPAY ไม่รับรายการ: ' . $response;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$flash = flash();
$history = $pdo->prepare(
    'SELECT password, transaction_id, real_amount, status, tmpay_status, message, created_at
     FROM tmpay_transactions
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 20'
);
$history->execute([$user['id']]);
$txns = $history->fetchAll();

$statusLabel = [
    'pending' => 'รอส่ง',
    'awaiting_result' => 'รอผล',
    'success' => 'สำเร็จ',
    'failed' => 'ไม่สำเร็จ',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เติมเงิน TrueMoney — TMPAY</title>
    <style>
        :root {
            --bg: #0f1419;
            --card: #1a2332;
            --line: #2a3a4f;
            --text: #e8eef6;
            --muted: #8aa0b8;
            --accent: #3dd68c;
            --danger: #ff6b7a;
            --warn: #f5c542;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Sarabun", sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #1c3a2a, var(--bg));
            color: var(--text);
            min-height: 100vh;
        }
        .wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
        }
        h1 { margin: 0 0 8px; font-size: 1.4rem; }
        p { color: var(--muted); }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        label { display: block; margin-bottom: 8px; color: var(--muted); }
        input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #0f1824;
            color: var(--text);
            font-size: 1.1rem;
            letter-spacing: 0.12em;
        }
        button {
            margin-top: 14px;
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 10px;
            background: var(--accent);
            color: #062013;
            font-weight: 700;
            cursor: pointer;
        }
        .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 14px; }
        .ok { background: #163526; color: var(--accent); }
        .err { background: #3a1a22; color: var(--danger); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 10px 6px; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-weight: 600; }
        .pill { padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; }
        .success { background: #163526; color: var(--accent); }
        .awaiting_result, .pending { background: #3a3214; color: var(--warn); }
        .failed { background: #3a1a22; color: var(--danger); }
        code { color: #9ad4ff; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="row">
            <div>
                <h1>เติมเงินบัตรทรูมันนี่</h1>
                <p>ผู้ใช้ <strong><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></strong></p>
            </div>
            <div>
                <p>เครดิตคงเหลือ</p>
                <h1><?= number_format((float) $user['credit'], 2) ?> บาท</h1>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>">
                <?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="index.php" autocomplete="off">
            <label for="password">รหัสบัตรเงินสด 14 หลัก</label>
            <input id="password" name="password" type="text" maxlength="14" pattern="\d{14}" required placeholder="55555555555551">
            <button type="submit">ส่งรายการไป TMPAY</button>
        </form>
        <p>ทดสอบด้วย <code>merchant_id=TEST</code> เช่น <code>55555555555551</code> = 50 บาท</p>
        <p>callback: <code><?= htmlspecialchars($config['tmpay']['resp_url'], ENT_QUOTES, 'UTF-8') ?></code></p>
    </div>

    <div class="card">
        <h1>ประวัติรายการ</h1>
        <?php if (!$txns): ?>
            <p>ยังไม่มีรายการ</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>เวลา</th>
                    <th>รหัสบัตร</th>
                    <th>Txn</th>
                    <th>จำนวน</th>
                    <th>สถานะ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($txns as $txn): ?>
                    <tr>
                        <td><?= htmlspecialchars($txn['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($txn['password'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $txn['transaction_id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $txn['real_amount'] !== null ? number_format((float) $txn['real_amount'], 2) : '-' ?></td>
                        <td>
                            <span class="pill <?= htmlspecialchars($txn['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($statusLabel[$txn['status']] ?? $txn['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
