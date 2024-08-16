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

/**
 * BuilderColumnAbstract
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 13.08.2024 19:15:00
 */
abstract class ColumnBuilder extends BuilderAbstract implements ColumnBuilderInterface
{
    const TYPE_PK          = 'pk';
    const TYPE_BIGPK       = 'bigpk';
    const TYPE_STRING      = 'string';
    const TYPE_TEXT        = 'text';
    const TYPE_MEDIUM_TEXT = 'mediumtext';
    const TYPE_LONG_TEXT   = 'longtext';
    const TYPE_TINYINT     = 'tinyint';
    const TYPE_SMALLINT    = 'smallint';
    const TYPE_INTEGER     = 'integer';
    const TYPE_BIGINT      = 'bigint';
    const TYPE_FLOAT       = 'float';
    const TYPE_DOUBLE      = 'double';
    const TYPE_DECIMAL     = 'decimal';
    const TYPE_DATETIME    = 'datetime';
    const TYPE_TIMESTAMP   = 'timestamp';
    const TYPE_TIME        = 'time';
    const TYPE_DATE        = 'date';
    const TYPE_BINARY      = 'binary';
    const TYPE_BOOLEAN     = 'boolean';
    const TYPE_MONEY       = 'money';
    const TYPE_JSON        = 'json';

    const LOCATE_FIRST = 1;
    const LOCATE_AFTER = 2;

    protected ?string $column_name = null;

    protected ?string $column_prev = null;

    protected ?string $comment = null;

    /**
     * @var integer|string|array column size or precision definition. This is what goes into the parenthesis after
     * the column type. This can be either a string, an integer or an array. If it is an array, the array values will
     * be joined into a string separated by comma.
     */
    protected $constraint;
    /**
     * @var mixed default value of the column.
     */
    protected mixed $default = null;
    /**
     * @var boolean whether the column is not nullable. If this is `true`, a `NOT NULL` constraint will be added.
     */
    protected bool $isNotNull = false;
    /**
     * @var boolean whether the column values should be unique. If this is `true`, a `UNIQUE` constraint will be added.
     */
    protected bool $isUnique = false;
    /**
     * @var bool for column type definition such as INTEGER, SMALLINT, etc.
     */
    protected bool $isUnsigned = false;
    /**
     * @var int
     */
    protected int $location = 0;
    /**
     * @var string the column type definition such as INTEGER, VARCHAR, DATETIME, etc.
     */
    protected string $type = 'string';

    protected static array $adapters = [
        'mysql'  => \WebStone\PDO\Builder\Adapter\MySQL\Column::class,
    ];

    public static function create(string $driver_type, array $config = []): self
    {
        if ($driver  = static::getAdapter($driver_type)) {
            $builder = new $driver($config);
            return $builder;
        }

        throw new EDatabaseError("Adapter type `{$driver_type}` does not exist");
    }

    /**
     * @param string $column
     *
     * @return $this
     */
    public function after(string $column): self
    {
        $this->location    = self::LOCATE_AFTER;
        $this->column_prev = $column;

        return $this;
    }

    /**
     * @param string $str
     *
     * @return $this
     */
    public function comment(string $str): self
    {
        $this->comment = $str;

        return $this;
    }

    public function composeString(): string
    {
        return
            $this->composeName() .
            $this->composeType() .
            $this->composeNotNull() .
            $this->composeUnique() .
            $this->composeDefault() .
            $this->composeComment() .
            $this->composeLocation();
    }

    public function defaultValue(mixed $default, bool $quoted = true): self
    {
        if (is_string($default)) {
            $default = str_replace("'", "\\'", $default);
        }
        $this->default = $quoted == true ? "'$default'" : $default;

        return $this;
    }

    public function first(): self
    {
        $this->location    = self::LOCATE_FIRST;
        $this->column_prev = null;

        return $this;
    }

    public function name(string $str): self
    {
        $this->column_name = $str;

        return $this;
    }

    public function notNull(bool $val = true): self
    {
        $this->isNotNull = $val;

        return $this;
    }

    public function unique(bool $val = true): self
    {
        $this->isUnique = $val;

        return $this;
    }

    public function unsigned(bool $val = true): self
    {
        $this->isUnsigned = $val;

        return $this;
    }

    abstract protected function composeComment(): string;

    abstract protected function composeDefault(): string;

    abstract protected function composeLocation(): string;

    abstract protected function composeName(): string;

    abstract protected function composeNotNull(): string;

    abstract protected function composeType(): string;

    abstract protected function composeUnique(): string;

    /**
     * Get column size or precision definition. This is what goes into the parenthesis after
     *
     * @return  integer|string|array
     */
    public function getConstraint(): mixed
    {
        return $this->constraint;
    }

    /**
     * Set column size or precision definition. This is what goes into the parenthesis after
     *
     * @param  integer|string|array  $constraint  column size or precision definition. This is what goes into the parenthesis after
     *
     * @return  self
     */
    public function setConstraint(mixed $constraint = null): self
    {
        $this->constraint = $constraint;

        return $this;
    }

    /**
     * Get the value of column_name
     */
    public function getColumnName(): string
    {
        return $this->column_name;
    }

    /**
     * Set the value of column_name
     *
     * @return  self
     */
    public function setColumnName(string $column_name): self
    {
        $this->column_name = $column_name;

        return $this;
    }

    /**
     * Get the value of column_prev
     */
    public function getColumnPrev(): mixed
    {
        return $this->column_prev;
    }

    /**
     * Set the value of column_prev
     *
     * @return  self
     */
    public function setColumnPrev(string $column_prev): self
    {
        $this->column_prev = $column_prev;

        return $this;
    }
}
/** End of ColumnBuilderAbstract **/
