<?php

declare(strict_types=1);

use MomoKit\Sandbox\App;
use MomoKit\Sandbox\Database;
use MomoKit\Sandbox\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$databasePath = getenv('DB_PATH') ?: dirname(__DIR__).'/storage/sandbox.sqlite';
$pdo = Database::connect($databasePath);

$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$scheme = $forwardedProto !== ''
    ? strtolower(trim(explode(',', $forwardedProto)[0]))
    : ((($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http'));
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
$baseUrl = rtrim(getenv('APP_URL') ?: $scheme.'://'.$host, '/');

$headers = [];

foreach ($_SERVER as $name => $value) {
    if (str_starts_with($name, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = (string) $value;
    }
}

if (isset($_SERVER['CONTENT_TYPE'])) {
    $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
}

$request = new Request(
    method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
    path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
    headers: $headers,
    query: array_map('strval', $_GET),
    cookies: array_map('strval', $_COOKIE),
    body: (string) file_get_contents('php://input'),
);

$app = new App($pdo, $baseUrl, getenv('SANDBOX_ALLOW_PRIVATE_WEBHOOKS') === '1');
$app->handle($request)->send();
