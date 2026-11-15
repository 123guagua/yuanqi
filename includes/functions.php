<?php
declare(strict_types=1);

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('页面已过期，请刷新后重试。');
    }
}

function is_admin(): bool {
    return !empty($_SESSION['is_admin']);
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: admin.php');
        exit;
    }
}

function proxy_uri(array $proxy): string {
    return 'socks5://' . rawurlencode((string)$proxy['username']) . ':' .
        rawurlencode((string)$proxy['password']) . '@' .
        $proxy['host'] . ':' . $proxy['port'];
}

function parse_proxy_text(string $content, int $maxRows): array {
    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
    $valid = [];
    $errors = [];
    $seen = [];
    $processed = 0;

    foreach ($lines as $index => $rawLine) {
        $lineNo = $index + 1;
        $line = trim($rawLine);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $processed++;
        if ($processed > $maxRows) {
            $errors[] = "超过单次最多 {$maxRows} 条记录的限制。";
            break;
        }

        $parts = explode(':', $line);
        if (count($parts) !== 4) {
            $errors[] = "第 {$lineNo} 行：格式错误，应为 IP:端口:用户名:密码。";
            continue;
        }

        [$host, $portText, $username, $password] = $parts;
        $port = filter_var($portText, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535]
        ]);

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $errors[] = "第 {$lineNo} 行：不是有效的 IPv4 地址。";
            continue;
        }
        if ($port === false) {
            $errors[] = "第 {$lineNo} 行：端口必须为 1–65535 的整数。";
            continue;
        }
        if ($username === '' || $password === '') {
            $errors[] = "第 {$lineNo} 行：用户名和密码不能为空。";
            continue;
        }
        if (strlen($username) > 191 || strlen($password) > 255) {
            $errors[] = "第 {$lineNo} 行：用户名或密码过长。";
            continue;
        }

        $key = $host . ':' . $port . ':' . $username;
        if (isset($seen[$key])) {
            $errors[] = "第 {$lineNo} 行：发现重复代理，已跳过。";
            continue;
        }
        $seen[$key] = true;
        $valid[] = [
            'host' => $host,
            'port' => (int)$port,
            'username' => $username,
            'password' => $password,
        ];
    }

    return ['valid' => $valid, 'errors' => $errors];
}
