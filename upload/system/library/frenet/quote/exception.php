<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * The quote could not be built or the Frenet API refused it; checkout carries on without Frenet rates.
 */
class Exception extends \RuntimeException {
}
