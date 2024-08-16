<?php
// tests/DatabaseTest.php

use Chronolog\DateTimeStatement;
use Chronolog\LogBook;
use Chronolog\LogBookShelf;
use Chronolog\Scriber\FileScriber;
use Chronolog\Scriber\Renderer\StringRenderer;
use Chronolog\Severity;
use PHPUnit\Event\Code\Test;
use PHPUnit\Framework\TestCase;
use WebStone\Cache\Cache;
use WebStone\Cache\IO\File;
use WebStone\PDO\Database;
use WebStone\PDO\Driver;
use WebStone\PDO\Exceptions\EDatabaseError;
use WebStone\PDO\ModelAbstract;
use WebStone\PDO\RecordsetAbstract;
use WebStone\PDO\RecordsetItem;
use WebStone\Stdlib\Classes\AutoInitialized;

class DatabaseTest extends TestCase
{
    protected TestDatabase $db;

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

        $this->db = new TestDatabase(['logging' => true]);
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

    public function testTable()
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

        $this->db->addConnection('mysql', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('mysql');

        if ($forge = $this->db->getSchemaBuilder()) {
            $table = 'test' . rand(100, 999);
            $forge
                ->addColumn([
                    $forge->columnPrimaryKey(11)->name('test_id'),
                    $forge->columnString(32)->name('field_1')->notNull()->defaultValue('undefined'),
                    $forge->columnInt(4)->name('field_2')->notNull()->defaultValue(0, FALSE),
                    $forge->columnString(128)->name('field_3')->notNull()->defaultValue('undefined'),
                    $forge->columnTimestamp()->name('field_4')->notNull()->defaultValue('CURRENT_TIMESTAMP', false),
                    $forge->columnInt(11)->name('field_5')->notNull()->defaultValue(0, FALSE),
                ])
                ->addIndex([
                    $forge->index('field_2'),
                    $forge->index('field_3'),
                    $forge->index('field_5'),
                ])
                ->createTable($table);

            // query builder
            if ($query = $this->db->getQueryBuilder()) {
                $batch = [];
                for ($i = 0; $i < 10; $i++) {
                    $key = $i + 100;
                    $batch[] = [
                        'test_id' => $key,
                        'field_1' => "str-$key",
                        'field_2' => $key,
                        'field_3' => '1',
                        'field_4' => date('Y-m-d H:i:s'),
                        'field_5' => 0,
                    ];
                }
                $query->setAsBatch($batch)->insert($table);

                // direct load sql
                $count = count($this->db->loadSql("SELECT * FROM `{$table}`")->fetchAll()); // first call
                $this->assertEquals(10, $count);

                $count = count($this->db->loadSql("SELECT * FROM `{$table}`")->fetchAll()); // second call, check cache
                $this->assertEquals(10, $count);

                // query builder
                $query->select('*')->from($table)->where('test_id', 105)->limit(1);
                $row = $query->row();

                $this->assertIsArray($row);
                $this->assertEquals($batch[5], $row);

                $this->db
                    ->loadSql("DROP TABLE `{$table}`")
                    ->execute();
            }
        }
    }

    public function testModel()
    {
        $this->db->addConnection('mysql', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('mysql');

        // new model
        $table = 'test_model' . rand(100, 999);
        $model = new class($this->db, $table) extends ModelAbstract {
            public function __construct(Database $db, string $table = 'test_model', string $primary_key = 'test_id')
            {
                parent::__construct($table, $primary_key);
                $this->setDb($db);

                if ($forge = $this->getDb()->getSchemaBuilder()) {
                    $forge
                        ->addColumn([
                            $forge->columnPrimaryKey(11)->name('test_id'),
                            $forge->columnString(32)->name('field_1')->notNull()->defaultValue('undefined'),
                            $forge->columnInt(4)->name('field_2')->notNull()->defaultValue(0, FALSE),
                            $forge->columnString(128)->name('field_3')->notNull()->defaultValue('undefined'),
                            $forge->columnTimestamp()->name('field_4')->notNull()->defaultValue('CURRENT_TIMESTAMP', false),
                            $forge->columnInt(11)->name('field_5')->notNull()->defaultValue(0, FALSE),
                        ])
                        ->addIndex([
                            $forge->index('field_2'),
                            $forge->index('field_3'),
                            $forge->index('field_5'),
                        ])
                        ->createTable($this->getTable());
                }
            }

            public function beforeLoad(array &$data = null): bool
            {
                // update field_4
                $data['field_4'] = date('Y-m-d 00:00:00');
                return parent::beforeLoad($data);
            }

        };

        // insert
        if ($model->load(['test_id' => 1, 'field_1' => 'Simple test'])) {
            $model->insert();
        }
        $this->assertIsArray($model->find(1));
        $this->assertIsArray($model->findByCondition(['field_1' => 'Simple test'], true));

        // update
        $model->field_1 = 'Simple test updated';
        if ($updatede = $model->update() > 0) {
            // check count of updated rows
            $this->assertEquals(1, $updatede, 'Updated rows count is not equal to 1');
        }
        // clear cache
        $model->__flush();

        $this->assertIsArray($model->findByCondition(['field_1' => 'Simple test updated'], true));

        // select(via query builder)
        if ($query = $this->db->getQueryBuilder()) {
            $query
                ->select()
                ->from($model->getTable())
                ->like('field_1', 'test')
                ->limit(1);

            $this->assertIsArray($query->row());
        }

        // remove table
        $this->db
            ->loadSql("DROP TABLE `{$table}`")
            ->execute();
    }

    public function testRecordset()
    {
        $this->db->addConnection('mysql', 'mysql:host=localhost;dbname=test', 'user', 'password');
        $this->db->selectConnection('mysql');

        $table = 'test_recordset';
        if ($forge = $this->db->getSchemaBuilder()) {
            $forge
                ->addColumn([
                    $forge->columnPrimaryKey(11)->name('test_id'),
                    $forge->columnString(32)->name('field_1')->notNull()->defaultValue('undefined'),
                    $forge->columnInt(4)->name('field_2')->notNull()->defaultValue(0, FALSE),
                    $forge->columnString(128)->name('field_3')->notNull()->defaultValue('undefined'),
                    $forge->columnTimestamp()->name('field_4')->notNull()->defaultValue('CURRENT_TIMESTAMP', false),
                    $forge->columnInt(11)->name('field_5')->notNull()->defaultValue(0, FALSE),
                ])
                ->addIndex([
                    $forge->index('field_2'),
                    $forge->index('field_3'),
                    $forge->index('field_5'),
                ])
                ->createTable($table);
        }

        if ($query = $this->db->getQueryBuilder()) {
            // insert batch
            $batch = [];
            for ($i = 0; $i < 100; $i++) {
                $batch[] = [
                    'field_1' => "str-$i",
                    'field_2' => $i,
                    'field_3' => '1',
                    'field_4' => date('Y-m-d H:i:s'),
                    'field_5' => 0,
                ];
            }
            $query->setAsBatch($batch)->insert($table);
        }

        // new recordset
        $recordset = new class($this->db) extends RecordsetAbstract {
            public function __construct(Database $db)
            {
                $this->setDb($db);
            }

            public function getQuery(): string
            {
                $sql = '';
                if ($query = $this->getDb()->getQueryBuilder()) {
                    $sql = $query
                        ->prepare(true)
                        ->select('*')
                        ->from('test_recordset')
                        // some conditions via ->where(...)
                        ->limit($this->getPageSize(), $this->getPageSize() * ($this->getPageIndex() - 1))
                        ->rows();
                }
                return $sql;
            }
        };

        // fetch rows
        $recordset
            ->setPageIndex(1)
            ->setPageSize(10)
            ->fetchRows([PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE,TestRecord::class]);

        while($record = $recordset->fetchRow()) {
            $this->assertInstanceOf(TestRecord::class, $record);
            $this->assertIsNumeric($record->field_2);
            $this->assertIsNumeric($record->field_5);
        }

        // remove table
        $this->db
            ->loadSql("DROP TABLE `{$table}`")
            ->execute();
    }
}

class TestDatabase extends Database
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
            $index = $this->query_counter;
            $logMsg = [
                'id'            => $index,
                'sql'           => $this->buildSql(),
                'rows_affected' => $this->query_rows_affected,
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

class TestRecord extends RecordsetItem{

}