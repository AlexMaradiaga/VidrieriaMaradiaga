<?php

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Database\SqlServerConnector;
use PDO;
use PHPUnit\Framework\TestCase;

class SqlServerConnectorTest extends TestCase
{
    public function test_each_new_pdo_is_configured_before_it_is_returned(): void
    {
        $first = $this->createMock(PDO::class);
        $second = $this->createMock(PDO::class);
        foreach ([$first, $second] as $pdo) {
            $pdo->expects($this->once())->method('exec')
                ->with('SET DATEFORMAT ymd')->willReturn(0);
        }

        $connector = $this->getMockBuilder(SqlServerConnector::class)
            ->onlyMethods(['createConnection', 'getDsn'])->getMock();
        $connector->method('getDsn')->willReturn('sqlsrv:Server=test');
        $connector->expects($this->exactly(2))->method('createConnection')
            ->willReturnOnConsecutiveCalls($first, $second);

        $this->assertSame($first, $connector->connect([]));
        $this->assertSame($second, $connector->connect([]));
    }
}
