<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\DB2SchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function in_array;

/**
 * @covers \Doctrine\DBAL\Schema\DB2SchemaManager
 */
final class DB2SchemaManagerTest extends TestCase
{
    /** @var Connection|MockObject */
    private $conn;

    /** @var DB2SchemaManager */
    private $manager;

    protected function setUp() : void
    {
        $eventManager  = new EventManager();
        $driverMock    = $this->createMock(Driver::class);
        $platform      = $this->createMock(DB2Platform::class);
        $this->conn    = $this
            ->getMockBuilder(Connection::class)
            ->onlyMethods(['fetchAll', 'quote'])
            ->setConstructorArgs([['platform' => $platform], $driverMock, new Configuration(), $eventManager])
            ->getMock();
        $this->manager = new DB2SchemaManager($this->conn);
    }

    /**
     * @see https://github.com/doctrine/dbal/issues/2701
     *
     * @group DBAL-2701
     */
    public function testListTableNamesFiltersAssetNamesCorrectly() : void
    {
        $this->conn->getConfiguration()->setFilterSchemaAssetsExpression('/^(?!T_)/');
        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]));

        self::assertSame(
            [
                'FOO',
                'BAR',
            ],
            $this->manager->listTableNames()
        );
    }

    /**
     * @group DBAL-2701
     */
    public function testAssetFilteringSetsACallable() : void
    {
        $filterExpression = '/^(?!T_)/';
        $this->conn->getConfiguration()->setFilterSchemaAssetsExpression($filterExpression);
        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]));

        self::assertSame(
            [
                'FOO',
                'BAR',
            ],
            $this->manager->listTableNames()
        );

        $callable = $this->conn->getConfiguration()->getSchemaAssetsFilter();
        self::assertIsCallable($callable);

        // BC check: Test that regexp expression is still preserved & accessible.
        $this->assertEquals($filterExpression, $this->conn->getConfiguration()->getFilterSchemaAssetsExpression());
    }

    public function testListTableNamesFiltersAssetNamesCorrectlyWithCallable() : void
    {
        $accepted = ['T_FOO', 'T_BAR'];
        $this->conn->getConfiguration()->setSchemaAssetsFilter(static function ($assetName) use ($accepted) {
            return in_array($assetName, $accepted);
        });
        $this->conn->expects($this->any())->method('quote');
        $this->conn->expects($this->once())->method('fetchAll')->will($this->returnValue([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]));

        self::assertSame(
            [
                'T_FOO',
                'T_BAR',
            ],
            $this->manager->listTableNames()
        );

        $this->assertNull($this->conn->getConfiguration()->getFilterSchemaAssetsExpression());
    }

    public function testSettingNullExpressionWillResetCallable() : void
    {
        $accepted = ['T_FOO', 'T_BAR'];
        $this->conn->getConfiguration()->setSchemaAssetsFilter(static function ($assetName) use ($accepted) {
            return in_array($assetName, $accepted);
        });
        $this->conn->expects($this->any())->method('quote');
        $this->conn->expects($this->atLeastOnce())->method('fetchAll')->will($this->returnValue([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]));

        self::assertSame(
            [
                'T_FOO',
                'T_BAR',
            ],
            $this->manager->listTableNames()
        );

        $this->conn->getConfiguration()->setFilterSchemaAssetsExpression(null);

        self::assertSame(
            [
                'FOO',
                'T_FOO',
                'BAR',
                'T_BAR',
            ],
            $this->manager->listTableNames()
        );

        $this->assertNull($this->conn->getConfiguration()->getSchemaAssetsFilter());
    }

    public function testSettingNullAsCallableClearsExpression() : void
    {
        $filterExpression = '/^(?!T_)/';
        $this->conn->getConfiguration()->setFilterSchemaAssetsExpression($filterExpression);

        $this->conn->expects($this->exactly(2))->method('fetchAll')->will($this->returnValue([
            ['name' => 'FOO'],
            ['name' => 'T_FOO'],
            ['name' => 'BAR'],
            ['name' => 'T_BAR'],
        ]));

        self::assertSame(
            [
                'FOO',
                'BAR',
            ],
            $this->manager->listTableNames()
        );

        $this->conn->getConfiguration()->setSchemaAssetsFilter(null);

        self::assertSame(
            [
                'FOO',
                'T_FOO',
                'BAR',
                'T_BAR',
            ],
            $this->manager->listTableNames()
        );

        $this->assertNull($this->conn->getConfiguration()->getFilterSchemaAssetsExpression());
    }
}
