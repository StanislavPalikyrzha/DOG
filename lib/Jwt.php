<?php

function jwt_base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_base64url_decode($data)
{
    $padding = strlen($data) % 4;

    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_create_token($user)
{
    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT',
    ];

    $payload = [
        'sub' => (int) $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'iat' => time(),
        'exp' => time() + JWT_TTL_SECONDS,
    ];

    $header_part = jwt_base64url_encode(json_encode($header));
    $payload_part = jwt_base64url_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $header_part . '.' . $payload_part, JWT_SECRET, true);
    $signature_part = jwt_base64url_encode($signature);

    return $header_part . '.' . $payload_part . '.' . $signature_part;
}

function jwt_decode_token($token)
{
    $parts = explode('.', (string) $token);

    if (count($parts) !== 3) {
        return null;
    }

    $header_json = jwt_base64url_decode($parts[0]);
    $payload_json = jwt_base64url_decode($parts[1]);
    $signature = jwt_base64url_decode($parts[2]);

    if ($header_json === false || $payload_json === false || $signature === false) {
        return null;
    }

    $header = json_decode($header_json, true);
    $payload = json_decode($payload_json, true);

    if (!is_array($header) || !is_array($payload)) {
        return null;
    }

    if (($header['alg'] ?? '') !== 'HS256') {
        return null;
    }

    $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET, true);

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    if (($payload['exp'] ?? 0) < time()) {
        return null;
    }

    return $payload;
}

function request_bearer_token()
{
    $header = '';

    if (!empty($_GET['token'])) {
        return trim((string) $_GET['token']);
    }

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $header = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $header = $headers['authorization'];
        }
    }

    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return null;
    }

    return trim($matches[1]);
}
