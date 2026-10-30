<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);
sendSecurityHeaders();

header('Content-Type: text/plain; charset=utf-8');
$autoload = __DIR__ . '/../vendor/autoload.php';
$push = pushConfig();
if (!file_exists($autoload) || empty($push['public_key']) || empty($push['private_key'])) {
    http_response_code(503);
    exit("Push belum dikonfigurasi. Jalankan composer install dan isi VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY di .env.\n");
}

require_once $autoload;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

$payload = json_encode(['title' => websiteSettings()['site_name'], 'body' => 'Ada pelanggan yang memasuki jadwal servis AC.', 'url' => baseUrl('pengingat/')]);
$webPush = new WebPush(['VAPID' => ['subject' => $push['subject'], 'publicKey' => $push['public_key'], 'privateKey' => $push['private_key']]]);
$subscriptions = $pdo->query('SELECT id, subscription_json FROM push_subscriptions')->fetchAll();
foreach ($subscriptions as $row) {
    $webPush->queueNotification(Subscription::create(json_decode($row['subscription_json'], true)), $payload);
}
foreach ($webPush->flush() as $report) {
    if ($report->isSuccess()) {
        echo "Notifikasi terkirim\n";
    } else {
        error_log('AC Care push notification failed: ' . $report->getReason());
        echo "Sebagian notifikasi gagal dikirim.\n";
    }
}