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
	$token = (string)($_SERVER['HTTP_TOKEN'] ?? '');
	$request_log = getenv('FRENET_MOCK_LOG');

	if ($request_log && $method === 'POST') {
		file_put_contents($request_log, json_encode([
			'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
			'body'    => json_decode((string)file_get_contents('php://input'), true)
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
	}

	$fixtures = [
		'single-service' => 'quote-single.json',
		'empty'          => 'quote-empty.json',
		'with-errors'    => 'quote-errors.json'
	];

	if ($method !== 'POST') {
		http_response_code(405);
		echo json_encode(['Message' => 'Method not allowed']);
	} elseif ($token === '') {
		http_response_code(401);
		echo json_encode(['Message' => 'Authorization has been denied for this request.']);
	} elseif (preg_match('#^status-(\d{3})$#', $token, $match)) {
		http_response_code((int)$match[1]);
		echo json_encode(['Message' => 'Mock answering HTTP ' . $match[1]]);
	} elseif ($token === 'flaky' && flakyFails()) {
		http_response_code(503);
		echo json_encode(['Message' => 'Mock failing every other quote']);
	} else {
		readfile(__DIR__ . '/fixtures/' . ($fixtures[$token] ?? 'quote.json'));
	}

	return;
}

/**
 * Fails the odd quotes and answers the even ones, so a retry is what makes the quote work.
 *
 * @return bool
 */
function flakyFails(): bool {
	$counter = sys_get_temp_dir() . '/frenet-mock-flaky';
	$count = (int)@file_get_contents($counter) + 1;

	file_put_contents($counter, (string)$count);

	return $count % 2 === 1;
}

http_response_code(404);
echo json_encode(['Message' => 'Not found: ' . $method . ' ' . $path]);
