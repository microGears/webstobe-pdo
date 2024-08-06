<?php
// tests/DatabaseTest.php

use Chronolog\DateTimeStatement;
use Chronolog\LogBook;
use Chronolog\LogBookShelf;
use Chronolog\Scriber\FileScriber;
use Chronolog\Scriber\Renderer\StringRenderer;
use Chronolog\Severity;
use PHPUnit\Framework\TestCase;
use WebStone\Cache\Cache;
use WebStone\Cache\IO\File;
use WebStone\PDO\Database;
use WebStone\PDO\Driver;
use WebStone\PDO\Exceptions\EDatabaseError;
use WebStone\Stdlib\Classes\AutoInitialized;

class DatabaseTest extends TestCase
{
    protected DatabaseExtra $db;

    protected function setUp(): void
    {
        $config = [
            'class'   => LogBook::class,
            'enabled' => true,
            'track'   => 'test',
            'scribes' => [
                [
                    'class'             => FileScriber::class,
                    'severity'          => Severity::Debug,
                    'renderer'          => [
                        'class'           => StringRenderer::class,
                        'pattern'         => "%datetime%~%severity_name% %message% %assets%",
                        'format'          => 'Y-m-d\TH:i:s.vP',
                        'allow_multiline' => true,
                        'include_traces'  => true,
                        'base_path'       => __DIR__,
                        // 'row_max_length' => 128,
                        // 'row_oversize_replacement' => '...',
                    ],
                    'path'              => dirname(__DIR__, 1) . '/runtime/logs/',
                    'basename'          => 'test',
                    'size_threshold'    => 1024 * 1000,
                    'max_files'         => 7,
                    'write_immediately' => false,
                    'collaborative'     => true,
                ],
            ],
        ];

        if (($log = AutoInitialized::turnInto($config)) instanceof LogBook) {
            LogBookShelf::put($log, true);
        }

        $this->db = new DatabaseExtra(['logging' => true]);
    }

    public function testAddConnection()
    {
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->addConnection('other', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->setDefault('default');

        $this->assertInstanceOf(Driver::class, $this->db->getDriver());
    }

    public function testAddConnectionWithExistingKey()
    {
        $this->expectException(EDatabaseError::class);
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test2', 'user', 'password');
    }

    public function testAddConnectionWithUnsupportedDriver()
    {
        $this->expectException(EDatabaseError::class);
        $this->db->addConnection('default', 'unsupported:host=localhost;dbname=test', 'user', 'password');
    }

    public function testSelectConnection()
    {
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->assertTrue($this->db->selectConnection('default'));
    }

    public function testSelectNonExistingConnection()
    {
        $this->expectException(EDatabaseError::class);
        $this->db->selectConnection('non_existing');
    }

    public function testSelectSameConnection()
    {
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('default');

        $this->assertFalse($this->db->selectConnection('default'));
    }

    public function testGetCache()
    {
        $this->assertInstanceOf(Cache::class, $this->db->getCache());
    }

    public function testGetCacheKey()
    {
        $key = $this->db->buildCacheID('test', ['param1' => 'value1']);
        $this->assertEquals(md5('test' . serialize(['param1' => 'value1'])), $key);
    }

    public function testGetDriver()
    {
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('default');
        $this->assertInstanceOf(Driver::class, $this->db->getDriver());
    }

    public function testGetDriverWhenNotSet()
    {
        $this->expectException(EDatabaseError::class);
        $this->db->getDriver();
    }

    public function testGetDriverName()
    {
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('default');
        $this->assertEquals('mysql', $this->db->getDriverName());
    }

    public function testGetLastInsertId()
    {
        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('default');
        $this->assertEquals(0, $this->db->getLastInsertID());
    }

    public function testGetLastQuery()
    {
        $this->assertEquals(['', [], 0], $this->db->getLastQuery());
    }

    public function testGetSql()
    {
        $sql    = 'SELECT * FROM users WHERE id = :id';
        $params = [':id' => 1];
        $this->assertEquals('SELECT * FROM users WHERE id = 1', $this->db->buildSql($sql, $params));
    }

    public function testExecuteSql()
    {
        $cache = new Cache([
            'driver'  => [
                'class'    => File::class,
                'lifetime' => 60,
                'path'     => dirname(__DIR__, 1) . '/runtime/db_cache/',
            ],
            'enabled' => true,
        ]);
        $this->db->setCache($cache);

        $this->db->addConnection('default', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('default');

        $this->db
            ->loadSql("
            CREATE TABLE IF NOT EXISTS `test` (
                `test_id` INT(11) NOT NULL AUTO_INCREMENT,
                `field_1` VARCHAR(8) NOT NULL COLLATE 'utf8_general_ci',
                `field_2` INT(4) NOT NULL,
                `field_3` VARCHAR(128) NOT NULL DEFAULT '1' COLLATE 'utf8_general_ci',
                `field_4` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `field_5` INT(11) NOT NULL DEFAULT '0' COMMENT 'Comment for field_5',
                PRIMARY KEY (`test_id`) USING BTREE,
                INDEX `ifield_2` (`field_2`) USING BTREE,
                INDEX `ifield_3` (`field_3`) USING BTREE,
                INDEX `ifield_5` (`field_5`) USING BTREE
            )")
            ->execute();

        $this->db
            ->loadSql("
            INSERT INTO `test` (`field_1`, `field_2`, `field_3`, `field_4`, `field_5`) VALUES
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0),
                ('str', 1, '1', '2024-01-01 00:01:00', 0);        
            ")
            ->execute();

        $count = count($this->db->loadSql("SELECT * FROM test")->fetchAll()); // first call
        $this->assertEquals(10,$count);

        $count = count($this->db->loadSql("SELECT * FROM test")->fetchAll()); // second call, check cache
        $this->assertEquals(10,$count);

        $this->db
            ->loadSql('DROP TABLE `test`')
            ->execute();
    }
    public function testIsCacheable()
    {
        $this->assertTrue($this->db->isCacheable('SELECT * FROM table_name'));
        $this->assertFalse($this->db->isCacheable('INSERT INTO table_name (name) VALUES ("test")'));
    }

    public function testGetLogging()
    {
        $this->db->setLogging(true);
        $this->assertTrue($this->db->getLogging());
    }

    public function testSetLogging()
    {
        $this->db->setLogging(false);
        $this->assertFalse($this->db->getLogging());
    }

    public function testIsSupported()
    {
        $this->assertTrue($this->db->isSupported('mysql'));
        $this->assertFalse($this->db->isSupported('unsupported'));
    }

    public function testSetCache()
    {
        $cache = new Cache([
            'driver'  => [
                'class'    => File::class,
                'lifetime' => 60,
                'path'     => dirname(__DIR__, 1) . '/runtime/db_cache/',
            ],
            'enabled' => true,
        ]);
        $this->db->setCache($cache);
        $this->assertSame($cache, $this->db->getCache());

        $this->db->addConnection('default', 'mysql:host=localhost', 'user', 'password');
        $this->db->selectConnection('default');

        $this->db->loadSql('SHOW DATABASES');
        $this->db->execute();

        /** Note! Directive(SHOW DATABASES) are not recorded in the logs, because the request of the type `SHOW` is cached */
        $this->db->loadSql('SHOW DATABASES');
        $this->db->execute();        
    }
}

class DatabaseExtra extends Database
{
    protected string $logID = 'test';
    protected bool $logging = true;

    public function getLogID(): string
    {
        return $this->logID;
    }

    public function setLogID(string $logID): self
    {
        $this->logID = $logID;
        return $this;
    }
    public function logMsg(string $message, int | Severity $severity = Severity::Debug, array $assets = [], null | DateTimeStatement $datetime = null): bool
    {
        if (LogBookShelf::has($id = $this->getLogID())) {
            $logger = LogBookShelf::get($id);
            return $logger->log($severity, $message, $assets, $datetime);
        }

        return false;
    }

    /**
     * @return boolean
     */
    public function getLogging()
    {
        return $this->logging;
    }

    /**
     * @param $logging
     *
     * @return $this
     */
    public function setLogging($logging)
    {
        $this->logging = $logging;

        return $this;
    }
    protected function afterQuery()
    {
        if ($this->logging == true) {
            $index = $this->queryCounter;
            $logMsg = [
                'id'            => $index,
                'sql'           => $this->buildSql(),
                'rows_affected' => $this->queryRowsAffected,
            ];

            $this->logMsg('Database execute: ' . join(',', $logMsg), Severity::Debug);
        }
    }

    protected function beforeQuery()
    {
        parent::beforeQuery();
        if ($this->logging == true) {
            /** do something */
        }
    }
}
