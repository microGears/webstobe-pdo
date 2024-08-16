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

use WebStone\PDO\Exceptions\ESQLError;

/**
 * Query
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 13.08.2024 15:15:00
 */
abstract class QueryBuilder extends BuilderAbstract implements QueryBuilderInterface
{
    use BuilderTrait;

    const BRACKET_START = '(';
    const BRACKET_END   = ')';

    protected $bracketsPrev   = [];
    protected $bracketsSource = null;
    protected $from           = [];
    protected $groupBy        = [];
    protected $having         = [];
    protected $join           = [];
    protected $like           = [];
    protected $likeQuoteChr   = '';
    protected $likeQuoteStr   = '';
    protected $limit          = false;
    protected $modifiers      = [];
    protected $offset         = false;
    protected $order          = false;
    protected $orderBy        = [];
    protected $randomKeyword  = '';
    protected $select         = [];
    protected $set            = [];
    protected $tableAliased   = [];
    protected $where          = [];
    protected $whereKey       = null;

    public static function create(string $driver_type, array $config = []): self
    {
        return new Query($config);
    }

    public function delete($table = '', $where = '', $limit = null): mixed
    {

        if ($table == '') {
            if (!isset($this->from[0])) {
                return false;
            }
            $table = $this->from[0];
        } elseif (is_array($table)) {
            $deleteBatch = [];
            foreach ($table as $single_table) {
                $sql           = $this->delete($single_table, $where, $limit);
                $sql           = rtrim($sql, ';') . ';';
                $deleteBatch[] = $sql;
            }

            if (count($deleteBatch) > 0) {
                return implode("\n", $deleteBatch);
            }

            throw new ESQLError('SQL query is empty');
        } else {
            $table = $this->protectIdentifiers($table);
        }

        if ($where != '') {
            $this->where($where);
        }

        if ($limit != null) {
            $this->limit($limit);
        }

        if (count($this->where) == 0 && count($this->like) == 0) {
            throw new ESQLError('SQL query(delete) must use where condition');
        }

        return $this->realize($this->composeDelete($table, $this->where, $this->like, $this->limit));
    }


    /**
     * Execute sql
     *
     * @param string $sql
     * @param array  $params
     * @param string $method [execute|fetch|fetchAll]
     * @param array  $args
     *
     * @return mixed
     */
    public function execute($sql, array $params = [], $method = 'execute', array $args = []): mixed
    {
        return $this->realize($sql, $params, $method, $args);
    }

    public function flush(): self
    {
        parent::flush();

        $this->from           = [];
        $this->groupBy        = [];
        $this->having         = [];
        $this->join           = [];
        $this->like           = [];
        $this->likeQuoteChr   = '';
        $this->likeQuoteStr   = '';
        $this->limit          = false;
        $this->offset         = false;
        $this->order          = false;
        $this->orderBy        = [];
        $this->randomKeyword  = '';
        $this->select         = [];
        $this->set            = [];
        $this->tableAliased   = [];
        $this->where          = [];
        $this->whereKey       = null;
        $this->bracketsSource = null;
        $this->bracketsPrev   = [];

        return $this;
    }

    public function from($from): self
    {
        foreach ((array) $from as $val) {
            if (strpos($val, ',') !== false) {
                foreach (explode(',', $val) as $v) {
                    $v = trim($v);
                    $this->tableAliases($v);
                    $this->from[] = $this->protectIdentifiers($v);
                }
            } else {
                $val = trim($val);

                // Extract any aliases that might exist.  We use this information
                // in the protectIdentifiers to know whether to add a table prefix
                $this->tableAliases($val);
                $this->from[] = $this->protectIdentifiers($val);
            }
        }

        return $this;
    }

    public function groupBy($by): self
    {
        if (is_string($by)) {
            $by = explode(',', $by);
        }

        foreach ($by as $val) {
            $val = trim($val);

            if ($val != '') {
                $this->groupBy[] = $this->protectIdentifiers($val);
            }
        }

        return $this;
    }

    public function hasOperator($str): bool
    {
        $str = trim($str);
        if (!preg_match("/(\s|<|>|!|=|is null|is not null)/i", $str)) {
            return false;
        }

        return true;
    }

    public function having($key, $value = '', $quote = true): self
    {
        return $this->composeHaving($key, $value, 'AND ', $quote);
    }

    public function havingBrackets(): self
    {
        return $this->brackets($this->having);
    }

    public function havingBracketsEnd(): self
    {
        return $this->bracketsEnd($this->having);
    }

    public function insert($table = '', $set = null, $replaceIfExists = false): mixed
    {

        if (!is_null($set)) {
            if (is_array($set) && isset($set[0])) {
                $this->setAsBatch($set);
            } else {
                $this->set($set);
            }
        }

        if (count($this->set) == 0) {
            return false;
        }

        if ($table == '') {
            if (!isset($this->from[0])) {
                return false;
            }
            $table = $this->from[0];
        }

        if (isset($this->set[0])) {
            $sql = $this->composeInsertBatch($this->protectIdentifiers($table), $this->set, $replaceIfExists);
        } else {
            $sql = $this->composeInsert($this->protectIdentifiers($table), array_keys($this->set), array_values($this->set), $replaceIfExists);
        }

        return $this->realize($sql);
    }

    public function join($table, $rule, $type = ''): self
    {
        if ($type != '') {
            $type = strtoupper(trim($type));

            if (!in_array($type, ['LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'])) {
                $type = '';
            } else {
                $type .= ' ';
            }
        }

        // Extract any aliases that might exist.  We use this information
        // in the _protect_identifiers to know whether to add a table prefix
        $this->tableAliases($table);

        // Strip apart the condition and protect the identifiers
        if (preg_match('/([\w\.]+)([\W\s]+)(.+)/', $rule, $match)) {
            $match[1] = $this->protectIdentifiers($match[1]);
            $match[3] = $this->protectIdentifiers($match[3]);

            $rule = $match[1] . $match[2] . $match[3];
        }

        // Assemble the JOIN statement
        $join = $type . 'JOIN ' . $this->protectIdentifiers($table) . ' ON ' . $rule;

        $this->join[] = $join;

        return $this;
    }

    public function joinInner($table, $rule): self
    {
        return $this->join($table, $rule, 'INNER');
    }

    public function joinLeft($table, $rule): self
    {
        return $this->join($table, $rule, 'LEFT');
    }

    public function joinOuter($table, $rule): self
    {
        return $this->join($table, $rule, 'OUTER');
    }

    public function joinRight($table, $rule): self
    {
        return $this->join($table, $rule, 'RIGHT');
    }

    public function like($field, $match = '', $side = 'both'): mixed
    {
        return $this->composeLike($field, $match, 'AND ', $side);
    }

    public function likeBrackets(): self
    {
        return $this->brackets($this->like);
    }

    public function likeBracketsEnd(): self
    {
        return $this->bracketsEnd($this->like);
    }

    public function limit($value, $offset = null): self
    {
        if (is_numeric($value) && $value > 0) {
            $this->limit = (int) $value;

            if (!empty($offset)) {
                $this->offset = (int) $offset;
            }
        } else {
            $this->limit  = false;
            $this->offset = false;
        }

        return $this;
    }

    public function modifiers($modifiers = null): self
    {
        if ($modifiers !== null) {
            if (is_string($modifiers)) {
                $modifiers = explode(' ', $modifiers);
            }

            foreach ($modifiers as $modifier) {
                $this->modifiers[] = trim(strtoupper($modifier));
            }
        }

        return $this;
    }

    public function notLike($field, $match = '', $side = 'both'): self
    {
        return $this->composeLike($field, $match, 'AND ', $side, 'NOT');
    }

    public function offset($offset): self
    {
        $this->offset = (int) $offset;

        return $this;
    }

    public function orHaving($key, $value = '', $quote = true): self
    {
        return $this->composeHaving($key, $value, 'OR ', $quote);
    }

    public function orLike($field, $match = '', $side = 'both'): self
    {
        return $this->composeLike($field, $match, 'OR ', $side);
    }

    public function orNotLike($field, $match = '', $side = 'both'): self
    {
        return $this->composeLike($field, $match, 'OR ', $side, 'NOT');
    }
    public function orWhere($key, $value = null, $escape = true): self
    {
        return $this->composeWhere($key, $value, 'OR ', $escape);
    }
    public function orWhereIn($key = null, $values = null): self
    {
        return $this->composeWhereIn($key, $values, false, 'OR ');
    }

    public function orWhereNotIn($key = null, $values = null): self
    {
        return $this->composeWhereIn($key, $values, true, 'OR ');
    }

    public function orderBy($order, $direction = ''): self
    {
        if (strtolower($direction) == 'random') {
            $order     = '';
            $direction = $this->randomKeyword;
        } elseif (trim($direction) != '') {
            $direction = (in_array(strtoupper(trim($direction)), ['ASC', 'DESC'], true)) ? ' ' . $direction : ' ASC';
        }

        if (strpos($order, ',') !== false) {
            $temp = [];
            foreach (explode(',', $order) as $part) {
                $part = trim($part);
                if (!in_array($part, $this->tableAliased)) {
                    $part = $this->protectIdentifiers(trim($part));
                }

                $temp[] = $part;
            }

            $order = implode(', ', $temp);
        } elseif ($direction != $this->randomKeyword) {
            $order = $this->protectIdentifiers($order);
        }

        $statement       = $order . $direction;
        $this->orderBy[] = $statement;

        return $this;
    }

    public function prepare(bool $value = false): self
    {
        $this->prepare = $value;

        return $this;
    }

    public function row(array $args = []): mixed
    {
        return $this->realize($this->composeSelect(), [], 'fetch', $args);
    }

    public function rowColumn(array $args = []): mixed
    {
        return $this->realize($this->composeSelect(), [], 'fetchColumn', $args);
    }

    public function rows(array $args = []): mixed
    {
        return $this->realize($this->composeSelect(), [], 'fetchAll', $args);
    }

    public function select($select = '*', $quoted = null): self
    {
        if (is_string($select)) {
            $select = explode(',', $select);
        }

        foreach ($select as $identifier) {
            $identifier = trim($identifier);
            if ($identifier != '') {
                $this->select[$identifier] = $quoted;
            }
        }

        return $this;
    }

    public function set($key, $value = null, $quote = true, $isBatch = false): self
    {
        $key = $this->objectToArray($key);

        if (!is_array($key)) {
            $key = [(string) $key => $value];
        }

        if ($isBatch == true) {
            $record = [];
            foreach ($key as $_key => $_value) {
                $record[$this->protectIdentifiers($_key)] = $quote !== false ? $this->quote($_value) : $_value;
            }
            ksort($record);
            $this->set[] = $record;
        } else {
            foreach ($key as $_key => $_value) {
                $this->set[$this->protectIdentifiers($_key)] = $quote !== false ? $this->quote($_value) : $_value;
            }
        }

        return $this;
    }

    public function setAsBatch($key, $value = null, $quote = true): self
    {
        if (is_array($key) && isset($key[0])) {
            foreach ($key as $batch) {
                $this->setAsBatch($batch, null, $quote);
            }

            return $this;
        }

        return $this->set($key, $value, $quote, true);
    }

    public function truncate($table = ''): bool
    {
        if ($table == '') {
            if (!isset($this->from[0])) {
                return false;
            }

            $table = $this->from[0];
        } else {
            $table = $this->protectIdentifiers($table);
        }

        return $this->realize($this->composeTruncate($table));
    }

    public function update($table = '', $set = null, $where = null, $whereKey = null, $limit = null): bool
    {
        if (!is_null($set)) {
            if (is_array($set) && isset($set[0])) {
                $this->setAsBatch($set);
            } else {
                $this->set($set);
            }
        }

        if (count($this->set) == 0) {
            return false;
        }

        if ($table == '') {
            if (!isset($this->from[0])) {
                return false;
            }
            $table = $this->from[0];
        }

        if ($where != null) {
            $this->where($where);
        }

        if ($limit != null) {
            $this->limit($limit);
        }

        if ($whereKey != null) {
            $this->whereKey($whereKey);
        }

        if (isset($this->set[0])) {
            $sql = $this->composeUpdateBatch($this->protectIdentifiers($table), $this->set, $this->where, $this->protectIdentifiers($this->whereKey), $this->orderBy, $this->limit);
        } else {
            $sql = $this->composeUpdate($this->protectIdentifiers($table), $this->set, $this->where, $this->orderBy, $this->limit);
        }

        return (bool)$this->realize($sql);
    }

    public function where($key, $value = null, $escape = true): self
    {
        return $this->composeWhere($key, $value, 'AND ', $escape);
    }

    public function whereBrackets(): self
    {
        return $this->brackets($this->where);
    }

    public function whereBracketsEnd(): self
    {
        return $this->bracketsEnd($this->where);
    }

    public function whereIn($key = null, $values = null): self
    {
        return $this->composeWhereIn($key, $values);
    }

    public function whereKey($key): self
    {
        if (is_string($key)) {
            $this->whereKey = $key;
        }

        return $this;
    }

    public function whereNotIn($key = null, $values = null): self
    {
        return $this->composeWhereIn($key, $values, true);
    }

    protected function brackets(&$source): self
    {
        $source[] = self::BRACKET_START;

        $this->bracketsPrev[] = $this->bracketsSource;
        $this->bracketsSource = &$source;

        return $this;
    }

    protected function bracketsAccept($value)
    {
        if ($this->bracketsSource !== null) {
            $this->bracketsSource[] = $value;
        }
    }

    protected function bracketsEnd(&$source): self
    {
        $source[] = self::BRACKET_END;

        $bracketsPrev         = array_pop($this->bracketsPrev);
        $this->bracketsSource = &$bracketsPrev;

        return $this;
    }

    abstract protected function composeDelete($tableName, $where = [], $like = [], $limit = false): string;

    abstract protected function composeHaving($key, $value = '', $type = 'AND ', $quote = true): self;

    abstract protected function composeInsert($tableName, $keys, $values, $replaceIfExists = false): string;

    abstract protected function composeInsertBatch($tableName, $rows, $replaceIfExists = false): string;

    abstract protected function composeLike($field, $match = '', $type = 'AND ', $side = 'both', $not = ''): self;

    abstract protected function composeLimit($sql, $limit, $offset): string;

    abstract protected function composeSelect(): string;

    abstract protected function composeTruncate($tableName): string;

    abstract protected function composeUpdate($tableName, $values, $where = null, $orderBy = [], $limit = false): string;

    abstract protected function composeUpdateBatch($tableName, $values, $where = null, $whereKey = null, $orderBy = [], $limit = false): string;

    abstract protected function composeWhere($key, $value = null, $type = 'AND ', $quote = null): self;

    abstract protected function composeWhereIn($key, $values = null, $not = false, $type = 'AND '): self;

    /**
     * @return bool
     */
    protected function hasBrackets()
    {
        return $this->bracketsSource !== null;
    }

    protected function objectToArray($object)
    {
        if (!is_object($object)) {
            return $object;
        }

        $array = [];
        foreach (get_object_vars($object) as $key => $val) {
            // There are some built in keys we need to ignore for this conversion
            if (!is_object($val) && !is_array($val) && $key != '_parent_name') {
                $array[$key] = $val;
            }
        }

        return $array;
    }

    protected function objectToArrayBatch($object)
    {
        if (!is_object($object)) {
            return $object;
        }

        $array  = [];
        $vars   = get_object_vars($object);
        $fields = array_keys($vars);

        foreach ($fields as $val) {
            // There are some built in keys we need to ignore for this conversion
            if ($val != '_parent_name') {

                $i = 0;
                foreach ($vars[$val] as $data) {
                    $array[$i][$val] = $data;
                    $i++;
                }
            }
        }

        return $array;
    }

    protected function tableAliases($table)
    {
        if (is_array($table)) {
            foreach ($table as $t) {
                $this->tableAliases($t);
            }

            return true;
        }

        // Does the string contain a comma?  If so, we need to separate
        // the string into discreet statements
        if (strpos($table, ',') !== false) {
            return $this->tableAliases(explode(',', $table));
        }

        // if a table alias is used we can recognize it by a space
        if (strpos($table, " ") !== false) {
            // if the alias is written with the AS keyword, remove it
            $table = preg_replace('/\s+AS\s+/i', ' ', $table);

            // Grab the alias
            $table = trim(strrchr($table, " "));

            // Store the alias, if it doesn't already exist
            if (!in_array($table, $this->tableAliased)) {
                $this->tableAliased[] = $table;
            }

            return true;
        }

        return false;
    }
}
/** End of Query **/
