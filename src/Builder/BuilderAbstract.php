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

use WebStone\PDO\Database;
use WebStone\PDO\DriverAdapters;
use WebStone\PDO\Exceptions\EDatabaseError;
use WebStone\Stdlib\Classes\AutoInitialized;

/**
 * BuilderAbstract
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 13.08.2024 15:05:00
 */
abstract class BuilderAbstract extends DriverAdapters
{
    protected ?Database $db          = null;
    protected bool $prepare          = false;

    abstract public static function create(string $driver_type, array $config = []): self;

    public function __toArray()
    {
        return $this->composeArray();
    }

    public function __toString()
    {
        return $this->composeString();
    }

    public function composeArray()
    {
        return [];
    }

    public function composeString()
    {
        return '';
    }

    public function getDb(): Database
    {
        return $this->db;
    }

    public function setDb(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get the value of prepare
     */
    public function getPrepare(): bool
    {
        return $this->prepare;
    }

    /**
     * Set the value of prepare
     *
     * @return  self
     */
    public function setPrepare(bool $prepare): self
    {
        $this->prepare = $prepare;
        return $this;
    }

    protected function flush(): self
    {
        $this->prepare = false;
        return $this;
    }

    protected function realize($sql, array $params = [], $method = 'execute', array $args = []): mixed
    {
        $result = $this->prepare == true ? $sql : $this->getDb()->loadSql($sql, $params)->$method($args);
        $this->flush();

        return $result;
    }
}
/** End of BuilderAbstract **/
