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

namespace WebStone\PDO\Builder\Adapter\MySQL;

use WebStone\PDO\Builder\BuilderTrait;
use WebStone\PDO\Builder\ColumnBuilderInterface;
use WebStone\PDO\Builder\IndexBuilderInterface;
use WebStone\PDO\Builder\SchemaBuilder;
use WebStone\PDO\Exceptions\EDatabaseError;

final class Schema extends SchemaBuilder
{
    use BuilderTrait;

    protected function composeAlterTableColumns(string $alter_spec, string $table_name, array $columns = []): string
    {
        $alter_spec = strtoupper($alter_spec);
        $sql        = sprintf('ALTER TABLE %s %s;', $this->protectIdentifiers($table_name), $this->composeColumns($columns, $alter_spec));

        return $sql;
    }

    protected function composeAlterTableKeys(string $alter_spec, string $table_name, array $keys = []): string
    {
        $alter_spec = strtoupper($alter_spec);
        $sql        = sprintf('ALTER TABLE %s %s;', $this->protectIdentifiers($table_name), $this->composeKeys($keys, $alter_spec));

        return $sql;
    }

    protected function composeAlterTableName($from_table, $to_table): string
    {
        $sql = sprintf('ALTER TABLE %s RENAME TO %s;', $this->protectIdentifiers($from_table), $this->protectIdentifiers($to_table));

        return $sql;
    }

    protected function composeColumn(mixed $column, array $attributes = [], ?string $alter_spec = null)
    {
        $sql = "\n\t";

        if ($alter_spec != null) {
            $sql .= "$alter_spec ";
        }

        $sql .= $this->protectIdentifiers($column);

        if ($alter_spec != null && strstr($alter_spec, 'DROP') !== false) {
            return $sql;
        }

        if (is_array($attributes) && !empty($attributes)) {

            $attributes = array_change_key_case($attributes, CASE_UPPER);

            if (array_key_exists('NAME', $attributes)) {
                $sql .= ' ' . $this->protectIdentifiers($attributes['NAME']) . ' ';
            }

            if (array_key_exists('TYPE', $attributes)) {
                $sql .= ' ' . $attributes['TYPE'];

                if (array_key_exists('CONSTRAINT', $attributes)) {
                    switch ($attributes['TYPE']) {
                        case 'decimal':
                        case 'float':
                        case 'numeric':
                            $sql .= '(' . implode(',', $attributes['CONSTRAINT']) . ')';
                            break;

                        case 'enum':
                        case 'set':
                            $sql .= '("' . implode('","', $attributes['CONSTRAINT']) . '")';
                            break;

                        default:
                            $sql .= '(' . $attributes['CONSTRAINT'] . ')';
                    }
                }

                if (array_key_exists('UNSIGNED', $attributes) && $attributes['UNSIGNED'] === true) {
                    $sql .= ' UNSIGNED';
                }

                if (array_key_exists('DEFAULT', $attributes)) {
                    $sql .= ' DEFAULT \'' . $attributes['DEFAULT'] . '\'';
                }

                if (array_key_exists('NULL', $attributes) && $attributes['NULL'] === true) {
                    $sql .= ' NULL';
                } else {
                    $sql .= ' NOT NULL';
                }

                if (array_key_exists('AUTO_INCREMENT', $attributes) && $attributes['AUTO_INCREMENT'] === true) {
                    $sql .= ' AUTO_INCREMENT PRIMARY KEY';
                }
            }
        } elseif ($attributes instanceof ColumnBuilderInterface) {
            $sql .= ' ' . $this->protectIdentifiers($attributes);
        }

        return $sql;
    }

    protected function composeColumns(array $columns, ?string $alter_spec = null)
    {
        $columns_count = 0;
        $sql           = '';

        foreach ($columns as $column => $attributes) {
            // Numeric field names aren't allowed in databases, so if the key is
            // numeric, we know it was assigned by PHP and the developer manually
            // entered the field information, so we'll simply add it to the list
            if (is_numeric($column)) {
                $sql .= $this->composeColumn($attributes, [], $alter_spec);
            } else {
                $sql .= $this->composeColumn($column, $attributes, $alter_spec);
            }

            // don't add a comma on the end of the last field
            if (++$columns_count < count($columns)) {
                $sql .= ',';
            }
        }

        return $sql;
    }

    protected function composeCreateDatabase(string $db_name, bool $if_not_exists = true, ?string $char_set = null, ?string $collate = null): string
    {
        if (empty($db_name)) {
            throw new EDatabaseError('Database name can not be empty.');
        }

        $sql = 'CREATE DATABASE ';

        if ($if_not_exists == true) {
            $sql .= 'IF NOT EXISTS ';
        }

        $sql .= $this->quoteIdentifier($db_name);

        if (!empty($char_set)) {
            $sql .= ' CHARACTER SET ' . $char_set;
        }

        if (!empty($collate)) {
            $sql .= ' COLLATE ' . $collate;
        }

        return $sql . ';';
    }

    protected function composeCreateTable(string $table_name, array $columns, array $keys, bool $if_not_exists = true): string
    {
        $sql = 'CREATE TABLE ';

        if ($if_not_exists === true) {
            $sql .= 'IF NOT EXISTS ';
        }

        $sql .= $this->quoteIdentifier($table_name) . " (";
        $sql .= $this->composeColumns($columns);
        $sql .= $this->composeKeys($keys);
        $sql .= ");";

        return $sql;
    }

    protected function composeDescribeTable(string $table_name): string
    {
        return "DESCRIBE $table_name";
    }

    protected function composeDropDatabase(string $db_name, bool $if_exists = true): string
    {
        if (empty($db_name)) {
            throw new EDatabaseError('Database name can not be empty.');
        }

        $sql = 'DROP DATABASE ';
        if ($if_exists == true) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->quoteIdentifier($db_name) . ';';

        return $sql;
    }

    protected function composeDropIndex(string $index_name, string $table_name, bool $is_primary_key = false): string
    {
        if ($is_primary_key == true) {
            return 'ALTER TABLE ' . $this->protectIdentifiers($table_name) . ' DROP CONSTRAINT ' . $this->protectIdentifiers($index_name);
        }

        return 'DROP INDEX ' . $this->protectIdentifiers($index_name) . ' ON ' . $this->protectIdentifiers($table_name);
    }

    protected function composeDropTable(string $table_name, bool $if_exists = true): string
    {
        if (empty($table_name)) {
            throw new EDatabaseError('Table name can not be empty.');
        }

        $sql = 'DROP TABLE ';
        if ($if_exists == true) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->quoteIdentifier($table_name);

        return "$sql;\n";
    }

    protected function composeExistsTable(string $table_name): string
    {
        if (empty($table_name)) {
            throw new EDatabaseError('Table name can not be empty.');
        }

        $sql = "SHOW TABLES LIKE '{$table_name}'";

        return "$sql;\n";
    }

    protected function composeKey($key, $alterSpec = null)
    {
        $sql = "\n\t";

        if ($alterSpec != null) {
            $sql .= "$alterSpec ";
        }

        // if key is array
        if (is_array($key)) {
            $attributes = array_change_key_case($key, CASE_UPPER);

            $column = [];
            if (array_key_exists('COLUMN', $attributes)) {
                if (is_array($attributes['COLUMN'])) {
                    $column = $attributes['COLUMN'];
                } elseif (is_string($attributes['COLUMN'])) {
                    $column[] = $attributes['COLUMN'];
                }
            }

            $indexName = implode('_', $column);
            if (array_key_exists('INDEXNAME', $attributes)) {
                $indexName = $attributes['INDEXNAME'];
            }

            $type      = 'INDEX';
            $isPrimary = false;
            if (array_key_exists('TYPE', $attributes)) {
                if (strpos('PRIMARY', strtoupper($attributes['TYPE'])) === true || strtoupper($attributes['TYPE']) == 'PRIMARY') {
                    $type      = 'PRIMARY KEY';
                    $isPrimary = true;
                } else {
                    $type = strtoupper($attributes['TYPE']);
                }
            }

            $indexName = $this->protectIdentifiers($indexName);
            $column    = $this->protectIdentifiers($column);

            $sql .= ' ' . $type . ($isPrimary != true ? ' ' . $indexName : '') . ' (' . implode(',', $column) . ')';
        } //if key is IndexBuilder
        elseif ($key instanceof IndexBuilderInterface) {
            $sql .= " $key";
        }

        return $sql;
    }

    protected function composeKeys($keys, $alterSpec = null)
    {
        $keys_count = 0;
        $sql        = '';

        foreach ($keys as $key => $attributes) {
            if (is_numeric($key)) {
                $sql .= $this->composeKey($attributes, $alterSpec);
            } else {
                $sql .= $this->composeKey($key, $alterSpec);
            }

            // don't add a comma on the end of the last field
            if (++$keys_count < count($keys)) {
                $sql .= ',';
            }
        }

        return ($alterSpec == null && $keys_count > 0 ? ",\n\t" : "") . $sql;
    }

    protected function composeShowDatabases(): string
    {
        return 'SHOW DATABASES';
    }

    protected function composeShowIndexes(string $table_name, ?string $db_name = null): string
    {
        $sql = 'SHOW INDEX FROM ' . $table_name;
        if (is_string($db_name)) {
            $sql .= " FROM $db_name";
        }

        return $sql;
    }

    protected function composeShowTables(?string $db_name = null): string
    {
        $sql = 'SHOW TABLES';
        if (is_string($db_name)) {
            $sql .= " FROM $db_name";
        }

        return $sql;
    }
}

/* End of file SchemaBuilder.php */
