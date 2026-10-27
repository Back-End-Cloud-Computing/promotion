<?php

namespace App\Domain\Coupons\Exceptions;

use RuntimeException;

/** Payload fora do contrato v1: não adianta reentregar, vai para a DLQ. */
class InvalidOrderConfirmedException extends RuntimeException {}
