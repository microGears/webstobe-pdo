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

namespace WebStone\PDO;

use WebStone\Stdlib\Helpers\StringHelper;

abstract class RecordsetAbstract
{
    protected Database $db;
    protected array $params   = [];
    protected array $columns  = [];    
    protected array $rows     = [];
    protected int $rows_index = 0;
    protected int $page_index = 1;
    protected int $page_size  = 10;
    
    abstract public function getQuery(): string;

    /**
     * Fetches rows from the recordset.
     *
     * @param array $args An optional array of arguments.
     * @return self The updated recordset object.
     */
    public function fetchRows(array $args = []): self
    {
        if ($rows = $this->getDb()->loadSql($this->getQuery(), $this->getParams())->fetchAll($args)) {
            return $this->setRows($rows);
        }
        return $this->setRows([]);
    }

    /**
     * Fetches the next row from the recordset.
     *
     * @return mixed The next row from the recordset.
     */
    public function fetchRow(): mixed
    {
        if (is_null($row_index = key($this->rows)) === false) {
            $this->rows_index = $row_index;
            $result           = $this->getRow();
            $this->nextRow();

            return $result;
        }

        return null;
    }

    /**
     * Retrieves the first row from the recordset.
     *
     * @return mixed The first row of the recordset.
     */
    function firstRow(): mixed
    {
        return reset($this->rows);
    }

    /**
     * Flushes any changes
     *
     * @return void
     */
    public function flush(): void
    {
        $this->rows_index = 0;
        $this->rows       = [];
    }

    /**
     * Get the value of db
     */
    public function getDb(): Database
    {
        return $this->db;
    }

    /**
     * Set the value of db
     *
     * @return  self
     */
    public function setDb(Database $db): self
    {
        $this->db = $db;

        return $this;
    }

    /**
     * Retrieves the value of a specific column in the recordset.
     *
     * @param string $column The name of the column to retrieve.
     * @param int|null $row The optional row number to retrieve the column value from. If not specified, the current row will be used.
     * @return mixed The value of the specified column.
     */
    public function getColumn($column, $row = null): mixed
    {

        if ($row === null) {
            $row = $this->getRow();
        }

        if (is_object($row)) {
            if (method_exists($row, $method = 'get' . StringHelper::normalizeName($column))) {
                return call_user_func([$row, $method]);
            } elseif (property_exists($row, $column)) {
                return $row->$column;
            }
        } else {
            if (isset($row[$column])) {
                return $row[$column];
            }
        }

        return null;
    }

    /**
     * Retrieves the columns of the recordset.
     *
     * @return array An array containing the columns of the recordset.
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Returns the number of columns in the recordset.
     *
     * @return int The number of columns in the recordset.
     */
    public function getColumnsCount(): int
    {
        return count($this->columns);
    }

    public function getRow($index = null): mixed
    {
        if ($index !== null) {
            if ($this->hasRow($index)) {
                return $this->rows[$index];
            }
        }

        return current($this->rows);
    }

    /**
     * Retrieves the rows of the recordset.
     *
     * @return array The rows from the recordset.
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Returns the number of rows in the recordset.
     *
     * @return int The number of rows in the recordset.
     */
    public function getRowsCount(): int
    {
        return count($this->rows);
    }

    /**
     * Retrieves the current index of the rows in the recordset.
     *
     * @return int The index of the rows.
     */
    public function getRowsIndex(): int
    {
        return $this->rows_index;
    }

    /**
     * Check if a row exists at the specified index.
     *
     * @param mixed $index The index of the row to check.
     * @return bool Returns true if a row exists at the specified index, false otherwise.
     */
    public function hasRow($index): bool
    {
        return isset($this->rows[$index]);
    }

    /**
     * Moves the internal pointer to the end & retrieves the last row of the recordset.
     *
     * @return mixed The last row from the recordset.
     */
    public function lastRow(): mixed
    {
        return end($this->rows);
    }

    /**
     * Moves the internal pointer to the next row in the recordset.
     *
     * @return mixed The next row in the recordset.
     */
    public function nextRow(): mixed
    {
        return next($this->rows);
    }

    /**
     * Moves the internal pointer to the previous row in the recordset.
     *
     * @return mixed The previous row in the recordset.
     */
    public function prevRow(): mixed
    {
        return prev($this->rows);
    }

    /**
     * Set the columns for the recordset.
     *
     * @param array $columns The columns to set.
     * @return self
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Set the current row of the recordset.
     *
     * @param mixed $row The row to set.
     * @return self Returns the updated recordset object.
     */
    public function setRow(mixed $row): self
    {
        if ($row = $this->normalize($row)) {
            $this->rows[] = $row;
        }
        return $this;
    }

    /**
     * Set the rows of the recordset.
     *
     * @param array $rows The rows to set.
     * @return self
     */
    public function setRows(array $rows): self
    {
        $this->flush();
        foreach ($rows as $row) {
            $this->setRow($row);
        }

        return $this;
    }

    /**
     * Normalize the given row.
     *
     * @param mixed $row The row to be normalized.
     * @return mixed The normalized row.
     */
    protected function normalize(mixed $row): mixed
    {
        if ($columns = $this->getColumns()) {
            // Only for array item
            if (is_array($row)) {
                $row = array_intersect_key((array) $row, array_fill_keys($columns, 'empty'));
            } // Only for object item
            elseif (is_object($row)) {
                foreach (array_keys(get_object_vars($row)) as $property) {
                    if (!in_array($property, $columns)) {
                        unset($row->{$property});
                    }
                }
            }
        }

        return $row;
    }

    /**
     * Get the value of page_index
     */
    public function getPageIndex(): int
    {
        return $this->page_index;
    }

    /**
     * Set the value of page_index
     *
     * @return  self
     */
    public function setPageIndex(int $page_index): self
    {
        $this->page_index = $page_index;
        return $this;
    }

    /**
     * Get the value of page_size
     */
    public function getPageSize(): int
    {
        return $this->page_size;
    }

    /**
     * Set the value of page_size
     *
     * @return  self
     */
    public function setPageSize(int $page_size): self
    {
        $this->page_size = $page_size;
        return $this;
    }

    /**
     * Get the value of params
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Set the value of params
     *
     * @return  self
     */
    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }
}

/* End of file RecordsetAbstract.php */
