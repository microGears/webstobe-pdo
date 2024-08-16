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


/**
 * ModelAbstract
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 15.08.2024 12:30:00
 */
abstract class ModelAbstract extends RecordsetItem
{
    protected Database $db;
    protected ?string $table        = null;
    protected string $primary_key   = 'id';
    protected bool $is_exists       = false;

    public function __construct(string $table, ?string $primary_key = 'id')
    {
        parent::__construct();

        $this->setTable($table);
        $this->setPrimaryKey($primary_key);
    }


    /**
     * Loads data into the model.
     *
     * @param array $data The data to load into the model.
     *
     * @return bool Returns true if the data was successfully loaded, false otherwise.
     */
    public function load(array $data): bool
    {
        $this->__flush();
        try {
            if ($this->beforeLoad($data) === true) {
                foreach ($data as $attribute => $value) {
                    $this->__set($attribute, $value);
                }
                $this->afterLoad();
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
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
     * Get the value of table
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Set the value of table
     *
     * @return  self
     */
    public function setTable(string $table)
    {
        $this->table = trim(strtolower(preg_replace('/(?<![A-Z])[A-Z]/', '_\0', $table)), '_');

        return $this;
    }

    /**
     * Get the value of primaryKey
     */
    public function getPrimaryKey(): string
    {
        return $this->primary_key;
    }

    /**
     * Set the value of primaryKey
     *
     * @return  self
     */
    public function setPrimaryKey(?string $primary_key = 'id')
    {
        $this->primary_key = $primary_key;
        return $this;
    }

    /**
     * Retrieves the ID of the model.
     *
     * @return int The ID of the model.
     */
    public function getId():mixed
    {
        return $this->__get($this->primary_key);
    }

    /**
     * Check if the model exists.
     *
     * @return bool Returns true if the model exists, false otherwise.
     */
    public function isExisting():bool
    {
        return $this->is_exists == true && $this->__get($this->primary_key) != null;
    }

    /**
     * Find a record by its ID.
     *
     * @param mixed $id The ID of the record to find.
     * @return mixed The found record.
     */
    public function find($id): mixed
    {
        return $this->findByCondition([$this->primary_key => $id], true);
    }

    /**
     * Finds record in the database based on a given condition.
     *
     * @param mixed $condition The condition to search for.
     * @param bool $assign Whether to assign the result to a variable or not. Default is false.
     * @return mixed The result of the search.
     */
    public function findByCondition($condition, bool $assign = false): mixed
    {
        $result = null;

        if ($assign == true) {
            $this->is_exists = false;
        }

        if (($sql = $this->getQuery($condition)) != null) {
            $row = $this->getDb()->loadSql($sql)->fetch();
            if ($assign == true) {
                if ($this->load($row) === false) {
                    return null;
                }
                $this->is_exists = true;
            }

            $result = $row;
        }

        return $result;
    }

    /**
     * Retrieves the query string based on the provided condition.
     *
     * @param mixed $condition The condition used to generate the query.
     *
     * @return string|null The generated query string, or null if the condition is invalid.
     */
    protected function getQuery(mixed $condition): string | null
    {
        $result = null;
        if ($query = $this->getDb()->getQueryBuilder()) {
            $query
                ->prepare(true)
                ->select('*')
                ->from($this->getTable())
                ->where($condition)
                ->limit(1);

            $result = $query->row();
        }

        return $result;
    }

    /**
     * Insert a new record into the database.
     *
     * @return mixed The result of the insert operation.
     */
    public function insert(): mixed
    {
        $result = null;

        if ($this->isExisting() === true) {
            return $this->update();
        }

        if ($this->beforeInsert() === true) {
            if ($query = $this->getDb()->getQueryBuilder()) {
                if ($result = $query->set($this->__getVars())->insert($this->getTable())) {
                    $this->__set($this->primary_key, $this->getDb()->getLastInsertID());
                    $this->is_exists = true;
                    $this->afterInsert();
                }
            }
        }

        return $result;
    }

    /**
     * Updates the model.
     *
     * @return mixed The result of the update operation.
     */
    public function update(): mixed
    {
        $result = null;
        if (!$this->isExisting()) {
            return $this->insert();
        }

        if ($this->beforeUpdate() === true) {
            if ($query = $this->getDb()->getQueryBuilder()) {
                $result = $query->set($this->__getVars())->where($this->primary_key, $this->__get($this->primary_key))->update($this->getTable());
                $this->afterUpdate();
            }
        }
        return $result;
    }

    /**
     * Deletes a record from the table.
     *
     * @param mixed $id The ID of the record to delete.
     * @return mixed The result of the delete operation.
     */
    public function delete($id = null): mixed
    {
        $result = null;

        if ($id === null) {
            $id = $this->__get($this->primary_key);
        }

        if ($this->beforeDelete()) {
            if ($query = $this->getDb()->getQueryBuilder()) {
                $result = $query->where($this->primary_key, $id)->delete($this->getTable(), '', 1);
                $this->afterDelete();
            }
        }

        return $result;
    }

    /**
     * Truncates the data in the model.
     *
     * @return mixed The result of the truncation operation.
     */
    protected function truncate(): mixed
    {
        $result = null;
        if ($this->beforeTruncate()) {
            if ($query = $this->getDb()->getQueryBuilder()) {
                $result = $query->truncate($this->getTable());
                $this->afterTruncate();
            }
        }

        return $result;
    }

    protected function beforeInsert(): bool
    {
        return true;
    }

    protected function beforeUpdate(): bool
    {
        return true;
    }

    protected function beforeDelete(): bool
    {
        return true;
    }

    protected function beforeTruncate(): bool
    {
        return true;
    }

    protected function beforeLoad(array &$data = null): bool
    {
        return true;
    }

    protected function afterInsert(): bool
    {
        return true;
    }

    protected function afterUpdate(): bool
    {
        return true;
    }

    protected function afterDelete(): bool
    {
        return true;
    }

    protected function afterTruncate(): bool
    {
        return true;
    }

    protected function afterLoad(): bool
    {
        return true;
    }
}
/** End of ModelAbstract **/
