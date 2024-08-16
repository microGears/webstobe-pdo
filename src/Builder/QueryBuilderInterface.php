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
 * QueryBuilderInterface
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 13.08.2024 15:00:00
 */
interface QueryBuilderInterface
{
   public function delete($table = '', $where = '', $limit = null): mixed;
   public function from($from): self;
   public function groupBy($by): self;
   public function having($key, $value = '', $quote = true): self;
   public function insert($table = '', $set = null, $replaceIfExists = false): mixed;
   public function join($table, $rule, $type = ''): self;
   public function joinInner($table, $rule): self;
   public function joinLeft($table, $rule): self;
   public function joinOuter($table, $rule): self;
   public function joinRight($table, $rule): self;
   public function like($field, $match = '', $side = 'both'): mixed;
   public function limit($value, $offset = null): self;
   public function modifiers($modifiers = null): self;
   public function notLike($field, $match = '', $side = 'both'): self;
   public function offset($offset): self;
   public function orHaving($key, $value = '', $quote = true): self;
   public function orLike($field, $match = '', $side = 'both'): self;
   public function orNotLike($field, $match = '', $side = 'both'): self;
   public function orWhere($key, $value = null, $escape = true): self;
   public function orWhereIn($key = null, $values = null): self;
   public function orWhereNotIn($key = null, $values = null): self;
   public function orderBy($order, $direction = ''): self;
   public function prepare(bool $value = false): self;
   public function row(array $args = []): mixed;
   public function rowColumn(array $args = []): mixed;
   public function rows(array $args = []): mixed;
   public function select($select = '*', $quoted = null): self;
   public function set($key, $value = null, $quote = true, $isBatch = false): self;
   public function setAsBatch($key, $value = null, $quote = true): self;
   public function truncate($table = ''): bool;
   public function update($table = '', $set = null, $where = null, $whereKey = null, $limit = null): bool;
   public function where($key, $value = null, $escape = true): self;
   public function whereBrackets(): self;
   public function whereBracketsEnd(): self;
   public function whereIn($key = null, $values = null): self;
   public function whereNotIn($key = null, $values = null): self;
   public function execute($sql, array $params = [], $method = 'execute', array $args = []): mixed;
   public function flush(): self;
}
/** End of QueryBuilderInterface **/
