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

use WebStone\PDO\Builder\ColumnBuilder;
use WebStone\PDO\Builder\BuilderTrait;
use WebStone\PDO\Exceptions\EDatabaseError;

final class Column extends ColumnBuilder
{
    use BuilderTrait;

    protected $map_type = [
        self::TYPE_PK          => 'int(%s)%s NOT NULL AUTO_INCREMENT PRIMARY KEY',
        self::TYPE_BIGPK       => 'bigint(%s)%s NOT NULL AUTO_INCREMENT PRIMARY KEY',
        self::TYPE_STRING      => 'varchar(%s)',
        self::TYPE_TEXT        => 'text',
        self::TYPE_MEDIUM_TEXT => 'mediumtext',
        self::TYPE_LONG_TEXT   => 'longtext',
        self::TYPE_TINYINT     => 'tinyint(%s)%s',
        self::TYPE_SMALLINT    => 'smallint(%s)%s',
        self::TYPE_INTEGER     => 'int(%s)%s',
        self::TYPE_BIGINT      => 'bigint(%s)%s',
        self::TYPE_FLOAT       => 'float(%s)%s',
        self::TYPE_DOUBLE      => 'double(%s)%s',
        self::TYPE_DECIMAL     => 'decimal(%s)%s',
        self::TYPE_DATETIME    => 'datetime',
        self::TYPE_TIMESTAMP   => 'timestamp',
        self::TYPE_TIME        => 'time',
        self::TYPE_DATE        => 'date',
        self::TYPE_BINARY      => 'blob',
        self::TYPE_BOOLEAN     => 'tinyint(%s)%s',
        self::TYPE_MONEY       => 'decimal(%s)%s',
        self::TYPE_JSON        => 'json',
    ];

    protected $map_defaults = [
        self::TYPE_PK          => 11,
        self::TYPE_BIGPK       => 20,
        self::TYPE_STRING      => 255,
        self::TYPE_TEXT        => null,
        self::TYPE_MEDIUM_TEXT => null,
        self::TYPE_LONG_TEXT   => null,
        self::TYPE_TINYINT     => 3,
        self::TYPE_SMALLINT    => 6,
        self::TYPE_INTEGER     => 11,
        self::TYPE_BIGINT      => 20,
        self::TYPE_FLOAT       => [10, 0],
        self::TYPE_DOUBLE      => [10, 0],
        self::TYPE_DECIMAL     => [10, 0],
        self::TYPE_DATETIME    => null,
        self::TYPE_TIMESTAMP   => null,
        self::TYPE_TIME        => null,
        self::TYPE_DATE        => null,
        self::TYPE_BINARY      => null,
        self::TYPE_BOOLEAN     => 1,
        self::TYPE_MONEY       => [19, 4],
        self::TYPE_JSON        => null,
    ];

    protected function composeComment(): string
    {
        if (empty($this->comment) || is_string($this->comment) != true) {
            return '';
        }

        return " COMMENT '" . addslashes($this->comment) . "'";
    }

    protected function composeConstraint($type)
    {
        $result = $this->constraint;

        if ($result === null || $result === []) {
            $result = isset($this->map_defaults[$type]) ? $this->map_defaults[$type] : '';
        }

        if (is_array($result)) {
            $result = implode(',', array_slice($result, 0, 2));
        } elseif (is_float($result)) {
            $result = implode(',', array_slice(explode('.', (string)$result), 0, 2));
        }

        return $result;
    }

    protected function composeDefault(): string
    {
        if ($this->default === null) {
            return '';
        }

        $result = ' DEFAULT ';
        switch (gettype($this->default)) {
            case 'integer':
                $result .= (string)$this->default;
                break;
            case 'double':
            case 'float':
                $result .= str_replace(',', '.', (string)$this->default);
                break;
            case 'boolean':
                $result .= $this->default ? 'TRUE' : 'FALSE';
                break;
            default:
                $result .= $this->default;
        }

        return $result;
    }

    protected function composeLocation(): string
    {
        $result = '';
        if ($this->location === static::LOCATE_FIRST) {
            $result = ' FIRST';
        } elseif ($this->location === static::LOCATE_AFTER && !empty($this->column_prev)) {
            $result = ' AFTER ' . $this->protectIdentifiers($this->column_prev);
        }

        return $result;
    }

    protected function composeName(): string
    {
        if ($this->column_name === null) {
            return '';
        }

        return "{$this->column_name} ";
    }

    protected function composeNotNull(): string
    {
        return $this->isNotNull == true ? ' NOT NULL' : ($this->type != static::TYPE_PK && $this->type != static::TYPE_BIGPK ? ' NULL' : '');
    }

    protected function composeType(): string
    {
        if (isset($this->map_type[$this->type])) {
            return sprintf($this->map_type[$this->type], $this->composeConstraint($this->type), $this->isUnsigned ? ' UNSIGNED' : '');
        }

        throw new EDatabaseError(sprintf('Column type "%s" is not supported', $this->type));
    }

    protected function composeUnique(): string
    {
        return $this->isUnique ? ' UNIQUE' : '';
    }
}

/* End of file BuilderColumn.php */
