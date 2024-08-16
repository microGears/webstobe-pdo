<?php

declare(strict_types=1);
/**
 * This file is part of WebStone\PDO.
 *
 * (C) 2009-2024 Maxim Kirichenko <kirichenko.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WebStone\PDO\Builder;

use WebStone\PDO\Exceptions\EDatabaseError;

abstract class SchemaBuilder extends BuilderAbstract implements SchemaBuilderInterface
{
    protected array $columns = [];
    protected array $keys    = [];

    protected static array $adapters = [
        'mysql' => \WebStone\PDO\Builder\Adapter\MySQL\Schema::class,
    ];

    public static function create(string $driver_type, array $config = []): self
    {
        if ($driver  = static::getAdapter($driver_type)) {
            $builder = new $driver($config);
            return $builder;
        }

        throw new EDatabaseError("Adapter type `{$driver_type}` does not exist");
    }

    public function addColumn($column): mixed
    {
        return $this->createColumn($column);
    }

    public function addIndex($key): mixed
    {
        return $this->createIndex($key);
    }

    public function columnBigInt(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_BIGINT, $length);
    }

    public function columnBigPk(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_BIGPK, $length);
    }

    public function columnBinary(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_BINARY, $length);
    }

    public function columnBoolean(): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_BOOLEAN);
    }

    public function columnDate(): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_DATE);
    }

    public function columnDateTime(): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_DATETIME);
    }

    public function columnDecimal(mixed $precision = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_DECIMAL, $precision);
    }

    public function columnDouble(mixed $precision = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_DOUBLE, $precision);
    }

    public function columnFloat(mixed $precision = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_FLOAT, $precision);
    }

    public function columnInt(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_INTEGER, $length);
    }

    public function columnJson(): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_JSON);
    }

    public function columnLongText(): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_LONG_TEXT);
    }

    public function columnMediumText(): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_MEDIUM_TEXT);
    }

    public function columnMoney(mixed $precision = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_MONEY, $precision);
    }

    public function columnPrimaryKey(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_PK, $length);
    }

    public function columnSmallint(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_SMALLINT, min(5, $length));
    }

    public function columnString(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_STRING, $length);
    }

    public function columnText(): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_TEXT);
    }

    public function columnTime(mixed $precision = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_TIME, $precision);
    }

    public function columnTimestamp(mixed $precision = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_TIMESTAMP, $precision);
    }

    public function columnTinyint(?int $length = null): ColumnBuilderInterface
    {
        return $this->createColumnBuilder(ColumnBuilder::TYPE_TINYINT, min(3, $length));
    }

    public function createColumn(mixed $column, ?string $table_name = null): mixed
    {
        if (is_string($column)) {
            if (strpos($column, ' ') === false) {
                throw new EDatabaseError('Column information is required for that operation.');
            }
            $this->columns[] = $column;
        } elseif ($column instanceof ColumnBuilderInterface) {
            $this->columns[] = $column;
        } elseif (is_array($column)) {
            $this->columns = array_merge($this->columns, $column);
        }

        if (empty($table_name)) {
            return $this;
        }

        $sql = $this->composeAlterTableColumns('ADD', $table_name, $this->columns);

        return $this->execute($sql);
    }

    public function createDatabase(string $db_name, bool $if_not_exists = true, ?string $char_set = null, ?string $collate = null): mixed
    {
        $sql = $this->composeCreateDatabase($db_name, $if_not_exists, $char_set, $collate);

        return $this->execute($sql);
    }

    public function createIndex(mixed $key, ?string $table_name = null): mixed
    {
        if (is_string($key) || $key instanceof IndexBuilderInterface) {
            $this->keys[] = $key;
        } elseif (is_array($key)) {
            $this->keys = array_merge($this->keys, $key);
        }

        if (empty($table_name)) {
            return $this;
        }

        $sql = $this->composeAlterTableKeys('ADD', $table_name, $this->keys);

        return $this->execute($sql);
    }

    public function createTable(string $table_name, bool $if_not_exists = true): mixed
    {
        if (empty($table_name)) {
            throw new EDatabaseError('A table name is required for that operation.');
        }

        if (count($this->columns) == 0) {
            throw new EDatabaseError('Field information is required.');
        }

        $sql = $this->composeCreateTable($table_name, $this->columns, $this->keys, $if_not_exists);

        return $this->execute($sql);
    }

    public function describeTable($table_name): mixed
    {
        $sql = $this->composeDescribeTable($table_name);

        return $this->realize($sql, [], 'fetchAll');
    }

    public function dropColumn(mixed $column, string $table_name): mixed
    {
        if (is_string($column)) {
            $this->columns[] = $column;
        } elseif (is_array($column)) {
            $this->columns = array_merge($this->columns, $column);
        }

        $sql = $this->composeAlterTableColumns('DROP', $table_name, $this->columns);

        return $this->execute($sql);
    }

    public function dropDatabase(string $db_name, bool $if_not_exists = true): mixed
    {
        $sql = $this->composeDropDatabase($db_name, $if_not_exists);

        return $this->execute($sql);
    }

    public function dropIndex(string $index_name, string $table_name): mixed
    {
        $sql = $this->composeDropIndex($index_name, $table_name);

        return $this->execute($sql);
    }

    public function dropPrimaryKey(string $index_name, string $table_name): mixed
    {
        $sql = $this->composeDropIndex($index_name, $table_name, true);

        return $this->execute($sql);
    }

    public function dropTable(string $table_name, bool $if_not_exists = true): mixed
    {
        $sql = $this->composeDropTable($table_name, $if_not_exists);

        return $this->execute($sql);
    }

    public function dropTableIfExists($table_name): mixed
    {
        return $this->dropTable($table_name, true);
    }

    public function existsTable(string $table_name): bool
    {
        $sql = $this->composeExistsTable($table_name);

        return $this->execute($sql) > 0;
    }

    public function index(mixed $column, ?string $index_name = null): IndexBuilderInterface
    {
        return $this->createIndexBuilder(IndexBuilder::TYPE_INDEX, $column, $index_name);
    }

    public function indexFulltext(mixed $column, ?string $index_name = null): IndexBuilderInterface
    {
        return $this->createIndexBuilder(IndexBuilder::TYPE_FULLTEXT, $column, $index_name);
    }

    public function indexUnique(mixed $column, ?string $index_name = null): IndexBuilderInterface
    {
        return $this->createIndexBuilder(IndexBuilder::TYPE_UNIQUE, $column, $index_name);
    }

    public function modifyColumn(mixed $column, string $table_name): mixed
    {
        if (is_string($column)) {
            if (strpos($column, ' ') === false) {
                throw new EDatabaseError('Column information is required for that operation.');
            }
            $this->columns[$column] = [];
        } elseif ($column instanceof ColumnBuilderInterface) {
            $this->columns[] = $column;
        } elseif (is_array($column)) {
            $this->columns = array_merge($this->columns, $column);
        }

        $sql = $this->composeAlterTableColumns('MODIFY', $table_name, $this->columns);

        return $this->execute($sql);
    }

    public function prepare($value = false): self
    {
        $this->prepare = $value;

        return $this;
    }

    public function primaryKey(mixed $column): IndexBuilderInterface
    {
        return $this->createIndexBuilder(IndexBuilder::TYPE_PK, $column);
    }

    public function renameTable(string $from_table, string $to_table): mixed
    {
        $sql = $this->composeAlterTableName($from_table, $to_table);

        return $this->execute($sql);
    }

    public function showDatabases(): mixed
    {
        $sql = $this->composeShowDatabases();

        return $this->realize($sql, [], 'fetchAll');
    }

    public function showIndex(string $table_name, ?string $db_name = null): mixed
    {
        $sql = $this->composeShowIndexes($table_name, $db_name);

        return $this->realize($sql, [], 'fetchAll');
    }

    public function showTables(?string $db_name = null): mixed
    {
        $sql = $this->composeShowTables($db_name);

        return $this->realize($sql, [], 'fetchAll');
    }

    abstract protected function composeAlterTableColumns(string $alter_spec, string $table_name, array $columns = []): string;

    abstract protected function composeAlterTableKeys(string $alter_spec, string $table_name, array $keys = []): string;

    abstract protected function composeAlterTableName(string $from_table, string $to_table): string;

    abstract protected function composeCreateDatabase(string $db_name, bool $if_not_exists = true, ?string $char_set = null, ?string $collate = null): string;

    abstract protected function composeCreateTable(string $table_name, array $columns, array $keys, bool $if_not_exists = true): string;

    abstract protected function composeDescribeTable(string $table_name): string;

    abstract protected function composeDropDatabase(string $db_name, bool $if_not_exists = true): string;

    abstract protected function composeDropIndex(string $index_name, string $table_name, bool $is_primary_key = false): string;

    abstract protected function composeDropTable(string $table_name, bool $if_not_exists = true): string;

    abstract protected function composeExistsTable(string $table_name): string;

    abstract protected function composeShowDatabases(): string;

    abstract protected function composeShowIndexes(string $table_name, ?string $db_name = null): string;

    abstract protected function composeShowTables(?string $db_name = null): string;

    protected function createColumnBuilder(string $type, mixed $constraint = null): ColumnBuilderInterface
    {
        return ColumnBuilder::create($this->getDb()->getDriverName(), ['type' => $type, 'constraint' => $constraint, 'db' => $this->db]);
    }

    protected function createIndexBuilder(string $type, mixed $column, ?string $index_name = null): IndexBuilderInterface
    {
        return IndexBuilder::create($this->getDb()->getDriverName(), ['type' => $type, 'column' => $column, 'index_name' => $index_name, 'db' => $this->db]);
    }

    protected function execute($sql): mixed
    {
        return $this->realize($sql, [], 'execute');
    }

    protected function flush(): self
    {
        parent::flush();
        $this->keys    = [];
        $this->columns = [];

        return $this;
    }
}

/* End of file SchemaBuilderAbstract.php */
