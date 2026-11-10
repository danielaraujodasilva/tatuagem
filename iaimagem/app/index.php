<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = '/iaimagem/app';
$suffix = substr($path, strlen($base));
if ($suffix === '' || $suffix === false) $suffix = '/';
$query = $_SERVER['QUERY_STRING'] ?? '';
if ($query !== '') $suffix .= '?' . $query;
$url = 'http://127.0.0.1:8766' . $suffix;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  $raw = file_get_contents('php://input');
  curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'application/json')]);
}
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/json';
curl_close($ch);
http_response_code($code);
header('Content-Type: ' . $type);
echo $data === false ? json_encode(['error'=>'AI service unavailable']) : $data;
?>
