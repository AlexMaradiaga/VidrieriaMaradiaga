<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Illuminate\Database\Connectors\SqlServerConnector as BaseSqlServerConnector;
use PDO;

class SqlServerConnector extends BaseSqlServerConnector
{
    public function connect(array $config): PDO
    {
        $connection = parent::connect($config);

        // Laravel binds dates as year-month-day. Apply on every new PDO,
        // including reconnects, without changing the shared SQL login language.
        $connection->exec('SET DATEFORMAT ymd');

        return $connection;
    }
}
