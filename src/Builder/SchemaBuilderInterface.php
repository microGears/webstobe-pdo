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

/**
 * SchemaBuilderInterface
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 14.08.2024 13:32:00
 */
interface SchemaBuilderInterface
{
    public function addColumn($column): mixed;
    public function addIndex($key): mixed;
    public function columnBigInt(?int $length = null): ColumnBuilderInterface;
    public function columnBigPk(?int $length = null): ColumnBuilderInterface;
    public function columnBinary(?int $length = null): ColumnBuilderInterface;
    public function columnBoolean(): ColumnBuilderInterface;
    public function columnDate(): ColumnBuilderInterface;
    public function columnDateTime(): ColumnBuilderInterface;
    public function columnDecimal(mixed $precision = null): ColumnBuilderInterface;
    public function columnDouble(mixed $precision = null): ColumnBuilderInterface;
    public function columnFloat(mixed $precision = null): ColumnBuilderInterface;
    public function columnInt(?int $length = null): ColumnBuilderInterface;
    public function columnJson(): ColumnBuilderInterface;
    public function columnLongText(): ColumnBuilderInterface;
    public function columnMediumText(): ColumnBuilderInterface;
    public function columnMoney(mixed $precision = null): ColumnBuilderInterface;
    public function columnPrimaryKey(?int $length = null): ColumnBuilderInterface;
    public function columnSmallint(?int $length = null): ColumnBuilderInterface;
    public function columnString(?int $length = null): ColumnBuilderInterface;
    public function columnText(): ColumnBuilderInterface;
    public function columnTime(mixed $precision = null): ColumnBuilderInterface;
    public function columnTimestamp(mixed $precision = null): ColumnBuilderInterface;
    public function columnTinyint(?int $length = null): ColumnBuilderInterface;
    public function createColumn(mixed $column, ?string $table_name = null): mixed;
    public function createDatabase(string $db_name, bool $if_not_exists = true, ?string $char_set = null, ?string $collate = null): mixed;
    public function createIndex(mixed $key, ?string $table_name = null): mixed;
    public function createTable(string $table_name, bool $if_not_exists = true): mixed;
    public function describeTable($table_name): mixed;
    public function dropColumn(mixed $column, string $table_name): mixed;
    public function dropDatabase(string $db_name, bool $if_exists = true): mixed;
    public function dropIndex(string $index_name, string $table_name): mixed;
    public function dropPrimaryKey(string $index_name, string $table_name): mixed;
    public function dropTable(string $table_name, bool $if_exists = true): mixed;
    public function dropTableIfExists($table_name): mixed;
    public function existsTable(string $table_name): bool;
    public function index(mixed $column, ?string $index_name = null): IndexBuilderInterface;
    public function indexFulltext(mixed $column, ?string $index_name = null): IndexBuilderInterface;
    public function indexUnique(mixed $column, ?string $index_name = null): IndexBuilderInterface;
    public function modifyColumn(mixed $column, string $table_name): mixed;
    public function primaryKey(mixed $column): IndexBuilderInterface;
    public function renameTable(string $from_table, string $to_table): mixed;
    public function showDatabases(): mixed;
    public function showIndex(string $table_name, ?string $db_name = null): mixed;
    public function showTables(?string $db_name = null): mixed;
}
/** End of SchemaBuilderInterface **/
