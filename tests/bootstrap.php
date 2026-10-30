<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/stubs/opencart.php';
require __DIR__ . '/stubs/cart.php';

$mock_host = (string)parse_url((string)getenv('FRENET_MOCK_URL'), PHP_URL_HOST);
$mock_port = (int)parse_url((string)getenv('FRENET_MOCK_URL'), PHP_URL_PORT);

$mock_server = proc_open(
	[PHP_BINARY, '-S', $mock_host . ':' . $mock_port, __DIR__ . '/mock/router.php'],
	[['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
	$pipes
);

if (!is_resource($mock_server)) {
	throw new RuntimeException('Could not start the Frenet mock on port ' . $mock_port);
}

register_shutdown_function(static function () use ($mock_server): void {
	proc_terminate($mock_server);
	proc_close($mock_server);
});

$deadline = microtime(true) + 5;

while (!@fsockopen($mock_host, $mock_port)) {
	if (microtime(true) > $deadline) {
		throw new RuntimeException('The Frenet mock did not answer on port ' . $mock_port);
	}

	usleep(50000);
}
