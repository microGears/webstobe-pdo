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

use WebStone\PDO\Builder\Adapter\MySQL\Index;
use WebStone\PDO\Exceptions\EDatabaseError;

/**
 * IndexBuilderAbstract
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 14.08.2024 11:40:00
 */
abstract class IndexBuilder extends BuilderAbstract implements IndexBuilderInterface
{
    const TYPE_PK       = 'primary';
    const TYPE_INDEX    = 'index';
    const TYPE_UNIQUE   = 'unique';
    const TYPE_FULLTEXT = 'fulltext';

    protected array $columns = [];
    protected ?string $index_name = null;
    protected string $type = self::TYPE_INDEX;

    protected static array $adapters = [
        'mysql'  => \WebStone\PDO\Builder\Adapter\MySQL\Index::class,
    ];

    public static function create(string $driver_type, array $config = []): self
    {
        if ($driver  = static::getAdapter($driver_type)) {
            $builder = new $driver($config);
            return $builder;
        }

        throw new EDatabaseError("Adapter type `{$driver_type}` does not exist");
    }

    public function column(mixed $column):self
    {
        if (is_string($column)) {
            if (!in_array($column, $this->columns)) {
                $this->columns[] = $column;
            }
        } elseif (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->column($val);
            }
        }

        return $this;
    }

    public function name(mixed $index_name):self
    {
        if (!empty($index_name) && is_string($index_name) && $this->index_name != (string)$index_name) {
            $this->index_name = (string)$index_name;
        }

        return $this;
    }    

    public function composeArray():array
    {
        return [
            'type'       => $this->type,
            'column'     => $this->columns,
            'index_name' => $this->index_name,
        ];
    }

    public function composeString():string
    {
        return $this->composeIndex();
    }

    protected function setColumn(mixed $column):self
    {
        return $this->column($column);
    }

    protected function setIndexName(mixed $index_name):self
    {
        return $this->name($index_name);
    }

    abstract protected function composeIndex();
}
/** End of IndexBuilderAbstract **/
