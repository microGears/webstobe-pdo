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

final class Query extends QueryBuilder
{
    public function from($from):self
    {
        /* Support for nested queries */
        if (is_string( $from ) && preg_match( '/(SELECT|SHOW|DESCRIBE)\b/i', $from )) {
            $this->from[] = $from;

            return $this;
        }

        return parent::from( $from );
    }

    protected function composeDelete($tableName, $where = [], $like = [], $limit = false):string
    {
        $sql = 'DELETE ';
        if (count( $this->modifiers )) {
            $sql .= implode( ' ', $this->modifiers );
        }

        $conditions = '';
        if (count( $where ) > 0 || count( $like ) > 0) {
            $conditions = "\nWHERE ";
            $conditions .= implode( "\n", $this->where );
            if (count( $where ) > 0 && count( $like ) > 0) {
                $conditions .= " AND ";
            }
            $conditions .= implode( "\n", $like );
        }

        $limit = (!$limit) ? '' : " LIMIT $limit";

        return "$sql FROM $tableName $conditions $limit";
    }

    protected function composeHaving($key, $value = '', $type = 'AND ', $quote = true):self
    {
        if (!is_array( $key )) {
            $key = [$key => $value];
        }

        foreach ($key as $k => $v) {

            switch ($this->hasBrackets()) {
                case true:
                    $prefix = $type;
                    if (end( $this->bracketsSource ) == static::BRACKET_START) {
                        $prefix = "";
                        while (end( $this->bracketsSource ) == static::BRACKET_START) {
                            $prefix .= array_pop( $this->bracketsSource );
                        }

                        if (count( $this->bracketsSource )) {
                            $prefix = "$type $prefix";
                        }
                    }
                    break;
                default:
                    $prefix = count( $this->having ) == 0 ? '' : $type;
                    break;
            }

            if ($quote === true) {
                $k = $this->protectIdentifiers( $k );
            }

            if (!$this->hasOperator( $k )) {
                $k .= ' = ';
            }

            if ($v != '') {
                $v = ' '.$this->quote( $v );
            }

            $statement = "$prefix$k$v";

            if ($this->hasBrackets()) {
                $this->bracketsAccept( $statement );
            } else {
                $this->having[] = $statement;
            }
        }

        return $this;
    }

    protected function composeInsert($tableName, $keys, $values, $replaceIfExists = false):string
    {
        $sql = 'INSERT ';
        if ($replaceIfExists == true) {
            $sql = 'REPLACE ';
        }

        if (count( $this->modifiers )) {
            $sql .= implode( ' ', $this->modifiers );
        }

        return "$sql INTO $tableName (".implode( ', ', $keys ).") VALUES (".implode( ', ', $values ).")";
    }

    protected function composeInsertBatch($tableName, $rows, $replaceIfExists = false):string
    {
        $sql = 'INSERT ';
        if ($replaceIfExists == true) {
            $sql = 'REPLACE ';
        }

        if (count( $this->modifiers )) {
            $sql .= implode( ' ', $this->modifiers );
        }

        $keys   = [];
        $values = [];

        for ($index = 0; $index < count( $rows ); $index++) {
            if (empty( $keys )) {
                $keys = array_keys( $rows[$index] );
            }
            $values[] = "(".implode( ', ', array_values( $rows[$index] ) ).")";
        }

        return "$sql INTO $tableName (".implode( ', ', $keys ).") VALUES ".implode( ', ', $values );
    }

    protected function composeLike($field, $match = '', $type = 'AND ', $side = 'both', $not = ''):self
    {
        if (!is_array( $field )) {
            $field = [$field => $match];
        }

        foreach ($field as $k => $v) {
            switch ($this->hasBrackets()) {
                case true:
                    $prefix = $type;
                    if (end( $this->bracketsSource ) == static::BRACKET_START) {
                        $prefix = "";
                        while (end( $this->bracketsSource ) == static::BRACKET_START) {
                            $prefix .= array_pop( $this->bracketsSource );
                        }

                        if (count( $this->bracketsSource )) {
                            $prefix = $type.' '.$prefix;
                        }
                    }
                    break;
                default:
                    $prefix = count( $this->like ) == 0 ? '' : $type;
                    break;
            }

            $k = $this->protectIdentifiers( $k );
            $v = $this->quoteStr( $v, $side != 'none' );

            if ($side == 'none') {
                $statement = $prefix." $k $not LIKE '{$v}'";
            } elseif ($side == 'before') {
                $statement = $prefix." $k $not LIKE '%{$v}'";
            } elseif ($side == 'after') {
                $statement = $prefix." $k $not LIKE '{$v}%'";
            } else {
                $statement = $prefix." $k $not LIKE '%{$v}%'";
            }

            if ($this->hasBrackets()) {
                $this->bracketsAccept( $statement );
            } else {
                $this->like[] = $statement;
            }
        }

        return $this;
    }

    protected function composeLimit($sql, $limit, $offset):string
    {
        if ($offset == 0) {
            $offset = '';
        } else {
            $offset .= ", ";
        }

        return "{$sql}LIMIT $offset$limit";
    }

    protected function composeSelect():string
    {

        $sql = 'SELECT ';
        if (count( $this->modifiers )) {
            $sql .= implode( ' ', $this->modifiers ).' ';
        }

        if (count( $this->select ) == 0) {
            $sql .= '*';
        } else {
            // Cycle through the "select" portion of the query and prep each column name.
            // The reason we protect identifiers here rather then in the select() function
            // is because until the user calls the from() function we don't know if there are aliases
            foreach ($this->select as $key => $quoting) {
                $this->select[$key] = $this->protectIdentifiers( $key, $quoting );
            }

            $sql .= implode( ', ', $this->select );
        }

        // ----------------------------------------------------------------

        // Write the "FROM" portion of the query

        if (count( $this->from ) > 0) {
            // fix for MySQL 8.x
            // Note! when using nested queries in FROM (...),
            // the presence of isolating brackets is controlled by the developer
            $br_start = self::BRACKET_START;
            $br_end   = self::BRACKET_END;
            $from     = implode( ', ', $this->from );

            if (preg_match( '/\s*(SELECT|FROM|JOIN)\b/i', $from )) {
                $br_start = $br_end = '';
            }

            $sql .= "\nFROM ";
            $sql .= $br_start.$from.$br_end;
        }

        // ----------------------------------------------------------------

        // Write the "JOIN" portion of the query

        if (count( $this->join ) > 0) {
            $sql .= "\n";
            $sql .= implode( "\n", $this->join );
        }

        // ----------------------------------------------------------------

        // Write the "WHERE" portion of the query

        if (count( $this->where ) > 0 || count( $this->like ) > 0) {
            $sql .= "\nWHERE ";
        }

        $sql .= implode( "\n", $this->where );

        // ----------------------------------------------------------------

        // Write the "LIKE" portion of the query

        if (count( $this->like ) > 0) {
            if (count( $this->where ) > 0) {
                $sql .= "\nAND ";
            }

            $sql .= implode( "\n", $this->like );
        }

        // ----------------------------------------------------------------

        // Write the "GROUP BY" portion of the query

        if (count( $this->groupBy ) > 0) {
            $sql .= "\nGROUP BY ";
            $sql .= implode( ', ', $this->groupBy );
        }

        // ----------------------------------------------------------------

        // Write the "HAVING" portion of the query

        if (count( $this->having ) > 0) {
            $sql .= "\nHAVING ";
            $sql .= implode( "\n", $this->having );
        }

        // ----------------------------------------------------------------

        // Write the "ORDER BY" portion of the query

        if (count( $this->orderBy ) > 0) {
            $sql .= "\nORDER BY ";
            $sql .= implode( ', ', $this->orderBy );
        }

        // ----------------------------------------------------------------

        // Write the "LIMIT" portion of the query
        if (is_numeric( $this->limit )) {
            $sql .= "\n";
            $sql = $this->composeLimit( $sql, $this->limit, $this->offset );
        }

        return $sql;
    }

    protected function composeTruncate($tableName):string
    {
        return "TRUNCATE $tableName";
    }

    protected function composeUpdate($tableName, $values, $where = null, $orderBy = [], $limit = false):string
    {
        $sql = 'UPDATE ';
        if (count( $this->modifiers )) {
            $sql .= implode( ' ', $this->modifiers ).' ';
        }

        $values_str = [];
        foreach ($values as $key => $val) {
            $values_str[] = "$key = $val";
        }

        $limit     = (!$limit) ? '' : ' LIMIT '.$limit;
        $orderBy   = (count( $orderBy ) >= 1) ? ' ORDER BY '.implode( ", ", $orderBy ) : '';
        $tableName = is_array( $tableName ) ? implode( ", ", $tableName ) : $tableName;

        $sql .= $tableName." SET ".implode( ', ', $values_str );
        $sql .= ($where != '' and count( $where ) >= 1) ? " WHERE ".implode( " ", $where ) : '';
        $sql .= $orderBy.$limit;

        return $sql;
    }

    protected function composeUpdateBatch($tableName, $values, $where = null, $whereKey = null, $orderBy = [], $limit = false):string
    {
        if (empty( $whereKey )) {
            return '';
        }

        $sql = 'UPDATE ';
        if (count( $this->modifiers )) {
            $sql .= implode( ' ', $this->modifiers ).' ';
        }

        $where     = ($where != '' and count( $where ) >= 1) ? implode( " ", $where ).' AND ' : '';
        $limit     = (!$limit) ? '' : " LIMIT $limit";
        $orderBy   = (count( $orderBy ) >= 1) ? ' ORDER BY '.implode( ", ", $orderBy ) : '';
        $tableName = is_array( $tableName ) ? implode( ", ", $tableName ) : $tableName;

        $cases_idx = [];
        $cases     = [];
        foreach ($values as $key => $value) {
            $cases_idx[] = $value[$whereKey];
            foreach (array_keys( $value ) as $field) {
                if ($field != $whereKey) {
                    $cases[$field][] = 'WHEN '.$whereKey.' = '.$value[$whereKey].' THEN '.$value[$field];
                }
            }
        }

        $sql       .= "$tableName SET ";
        $cases_str = '';
        foreach ($cases as $k => $v) {
            $cases_str .= "$k = CASE \n";
            foreach ($v as $row) {
                $cases_str .= "$row\n";
            }
            $cases_str .= "ELSE $k END, ";
        }

        $sql .= substr( $cases_str, 0, -2 );
        $sql .= ' WHERE '.$where.$whereKey.' IN ('.implode( ',', $cases_idx ).')';
        $sql .= "$orderBy$limit";

        return $sql;
    }

    protected function composeWhere($key, $value = null, $type = 'AND ', $quote = null):self
    {
        if ($key === null && $value === null) {
            return $this;
        }

        if (!is_array( $key )) {
            $key = [$key => $value];
        }

        foreach ($key as $k => $v) {
            switch ($this->hasBrackets()) {
                case true:
                    $prefix = $type;
                    if (end( $this->bracketsSource ) == static::BRACKET_START) {
                        $prefix = "";
                        while (end( $this->bracketsSource ) == static::BRACKET_START) {
                            $prefix .= array_pop( $this->bracketsSource );
                        }

                        if (count( $this->bracketsSource )) {
                            $prefix = $type.' '.$prefix;
                        }
                    }
                    break;
                default:
                    $prefix = count( $this->where ) == 0 ? '' : $type;
                    break;
            }

            if (is_null( $v ) && !$this->hasOperator( $k )) {
                // value appears not to have been set, assign the test to IS NULL
                $k .= ' IS NULL';
            }

            if (!is_null( $v )) {
                if ($quote === true) {
                    $k = $this->protectIdentifiers( $k, $quote );
                    $v = ' '.$this->quote( $v );
                }

                if (!$this->hasOperator( $k )) {
                    $k .= ' = ';
                }
            } else {
                $k = $this->protectIdentifiers( $k, $quote );
            }

            $statement = $prefix.$k.$v;

            if ($this->hasBrackets()) {
                $this->bracketsAccept( $statement );
            } else {
                $this->where[] = $statement;
            }
        }

        return $this;
    }

    protected function composeWhereIn($key, $values = null, $not = false, $type = 'AND '):self
    {
        if ($key === null or $values === null) {
            return $this;
        }

        $not     = ($not) ? ' NOT' : '';
        $whereIn = [];

        if (is_string( $values )) {
            $whereIn = $values;
        }

        if (is_array( $values )) {
            foreach ($values as $value) {
                $whereIn[] = $this->quote( $value );
            }

            $whereIn = implode( ", ", $whereIn );
        }

        if (!empty( $whereIn )) {
            switch ($this->hasBrackets()) {
                case true:
                    $prefix = $type;
                    if (end( $this->bracketsSource ) == static::BRACKET_START) {
                        $prefix = "";
                        while (end( $this->bracketsSource ) == static::BRACKET_START) {
                            $prefix .= array_pop( $this->bracketsSource );
                        }

                        if (count( $this->bracketsSource )) {
                            $prefix = "$type $prefix";
                        }
                    }
                    break;
                default:
                    $prefix = count( $this->where ) == 0 ? '' : $type;
                    break;
            }

            $statement = $prefix.$this->protectIdentifiers( $key ).$not." IN (".$whereIn.") ";

            if ($this->hasBrackets()) {
                $this->bracketsAccept( $statement );
            } else {
                $this->where[] = $statement;
            }
        }

        return $this;
    }
}

/* End of file Query.php */
