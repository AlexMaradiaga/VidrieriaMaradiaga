<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Exceptions;

use RuntimeException;

final class ProductNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El producto no existe o fue eliminado.');
    }
}
