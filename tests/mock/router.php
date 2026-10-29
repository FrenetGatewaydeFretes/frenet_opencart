<?php
declare(strict_types=1);

$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

if (preg_match('#^/mock/status/(\d{3})$#', $path, $match)) {
	http_response_code((int)$match[1]);
	echo json_encode(['status' => (int)$match[1]]);

	return;
}

if (preg_match('#^/mock/delay/(\d+)$#', $path, $match)) {
	sleep((int)$match[1]);
	echo json_encode(['delayed' => (int)$match[1]]);

	return;
}

if ($path === '/mock/echo') {
	echo json_encode([
		'method'  => $method,
		'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
		'body'    => file_get_contents('php://input')
	]);

	return;
}

if ($path === '/shipping/quote') {
	if ($method !== 'POST') {
		http_response_code(405);
		echo json_encode(['Message' => 'Method not allowed']);
	} elseif (empty($_SERVER['HTTP_TOKEN'])) {
		http_response_code(401);
		echo json_encode(['Message' => 'Authorization has been denied for this request.']);
	} else {
		readfile(__DIR__ . '/fixtures/quote.json');
	}

	return;
}

http_response_code(404);
echo json_encode(['Message' => 'Not found: ' . $method . ' ' . $path]);
