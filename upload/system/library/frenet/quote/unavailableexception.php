<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * The Frenet API refused the quote or did not answer it after every attempt; the code holds the HTTP status, if any.
 */
class UnavailableException extends Exception {
}
