<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/vendor/autoload.php';
use Minishlink\WebPush\VAPID;

if (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF')) {
    $opensslConfig = PHP_BINDIR . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
    if (is_file($opensslConfig)) {
        putenv('OPENSSL_CONF=' . $opensslConfig);
    }
}

$keys = VAPID::createVapidKeys();

if (in_array('--save', $argv, true)) {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile) || !is_writable($envFile)) {
        fwrite(STDERR, "File .env tidak ditemukan atau tidak dapat ditulis.\n");
        exit(1);
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    $values = [
        'VAPID_PUBLIC_KEY' => $keys['publicKey'],
        'VAPID_PRIVATE_KEY' => $keys['privateKey'],
    ];
    $updated = array_fill_keys(array_keys($values), false);

    foreach ($lines as &$line) {
        foreach ($values as $name => $value) {
            if (str_starts_with($line, $name . '=')) {
                $line = $name . '=' . $value;
                $updated[$name] = true;
            }
        }
    }
    unset($line);

    foreach ($values as $name => $value) {
        if (!$updated[$name]) {
            $lines[] = $name . '=' . $value;
        }
    }

    if (file_put_contents($envFile, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
        fwrite(STDERR, "Gagal menyimpan VAPID keys ke .env.\n");
        exit(1);
    }

    echo "VAPID keys tersimpan di .env. Jangan bagikan VAPID_PRIVATE_KEY.\n";
    exit(0);
}

print_r($keys);