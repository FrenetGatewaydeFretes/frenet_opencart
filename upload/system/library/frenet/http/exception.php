<?php
declare(strict_types=1);

namespace Frenet\Http;

/**
 * Transport failure (DNS, connection, TLS or timeout) where no HTTP answer was received.
 */
class Exception extends \RuntimeException {
}
