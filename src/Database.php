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

use PDOStatement;
use WebStone\Cache\Cache;
use WebStone\PDO\Exceptions\EDatabaseError;
use WebStone\PDO\Exceptions\ESQLError;
use WebStone\Stdlib\Classes\AutoInitialized;
use WebStone\Stdlib\Helpers\ArrayHelper;

/**
 * Database
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 01.08.2024 16:47:00
 */
class Database extends AutoInitialized
{
    protected ?Cache $cache = null;
    protected array $connections     = [];
    protected ?Driver $driver        = null;
    protected ?string $driverKey     = null;
    protected int $queryCounter      = 0;
    protected string $queryStr       = '';
    protected array $queryParams     = [];
    protected int $queryRowsAffected = 0;

    /**
     * Adds a new database connection.
     *
     * @param string $key The key to identify the connection.
     * @param string $dsn The Data Source Name (DSN) for the connection.
     * @param string|null $username The username for the connection (optional).
     * @param string|null $password The password for the connection (optional).
     * @param array $options Additional options for the connection (optional).
     *
     * @return self Returns the current instance of the Database class.
     */
    public function addConnection($key, $dsn, $username = null, $password = null, $options = []): self
    {
        if (isset($this->connections[$key])) {
            throw new EDatabaseError(sprintf('Database has already be specified for key "%s"', $key));
        }

        $type = substr($dsn, 0, strpos($dsn, ':'));
        if (!$this->isSupported($type)) {
            throw new EDatabaseError(sprintf('PDO Driver "%s" now is not supported.', $type));
        }

        $this->connections[$key] = new Driver([
            'dsn'      => $dsn,
            'username' => $username,
            'password' => $password,
            'options'  => $options,
        ]);

        return $this;
    }

    /**
     * Selects a database connection based on the given key.
     *
     * @param string $key The key of the connection to select.
     * @return bool Returns true if the connection was successfully selected, false otherwise.
     */
    public function selectConnection($key): bool
    {
        if ($this->driverKey === $key) {
            return false;
        }

        if (!isset($this->connections[$key]) || !($this->connections[$key] instanceof Driver)) {
            throw new EDatabaseError(sprintf("Connection \"%s\" was not found or is not a Driver type", $key));
        }

        $this->setDriver($this->connections[$this->driverKey = $key]);
        return true;
    }

    /**
     * Retrieves the cache object.
     *
     * @return Cache The cache object.
     */
    public function getCache()
    {
        if ($this->cache == null) {
            $this->cache = new Cache(['enabled' => false]);
        }
        return $this->cache;
    }

    /**
     * Returns the cache key for the database.
     *
     * @return string The cache key.
     */
    public function buildCacheID(): string
    {
        $result = '';
        $args   = func_get_args();
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $result .= serialize($arg);
            } else {
                $result .= $arg;
            }
        }

        return md5($result);
    }

    /**
     * Returns the current driver used by the database connection.
     *
     * @return Driver The driver used by the database connection.
     */
    public function getDriver(): Driver
    {
        if ($this->driver == null) {
            throw new EDatabaseError('Driver is not set');
        }

        return $this->driver;
    }

    /**
     * Returns the name of the database driver.
     *
     * @return string The name of the database driver.
     */
    public function getDriverName(): string
    {
        return $this->getDriver()->getPDO()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Retrieves the last inserted ID from the database.
     *
     * @param string|null $sequence The name of the sequence object from which the ID should be retrieved.
     * @return int The last inserted ID.
     */
    public function getLastInsertID($sequence = null)
    {
        return $this->getDriver()->getLastInsertID($sequence);
    }

    /**
     * Get last query info
     * @return array
     */
    public function getLastQuery()
    {
        return [$this->queryStr, $this->queryParams, $this->queryRowsAffected];
    }

    /**
     * Retrieves the SQL query and parameters.
     *
     * @param string|null $sql The SQL query to retrieve. Default is null.
     * @param array|null $params The parameters to be used in the SQL query. Default is null.
     * @return mixed The SQL query and parameters.
     */
    public function buildSql($sql = null, $params = null)
    {
        if (empty($sql)) {
            $sql = $this->queryStr;
        }

        if (empty($params)) {
            $params = $this->queryParams;
        }

        if (count($params) > 0) {
            foreach ($params as $key => $value) {
                $pattern = is_integer($key) ? ':' . ($key + 1) : $key;
                $sql     = str_replace($pattern, (string) $value, $sql);
            }
        }

        return $sql;
    }

    /**
     * Checks if a given SQL query is cacheable.
     *
     * @param string $sql The SQL query to check.
     * @return bool Returns true if the query is cacheable, false otherwise.
     */
    public function isCacheable($sql)
    {
        return preg_match('/^\s*(SELECT|SHOW|DESCRIBE)\b/i', $sql) > 0;
    }

    /**
     * Checks if a given database driver is supported.
     *
     * @param string $driver The name of the database driver to check.
     * @return bool Returns true if the driver is supported, false otherwise.
     */
    public function isSupported($driver): bool
    {
        static $drivers;
        if ($drivers == null) {
            $drivers = \PDO::getAvailableDrivers();
        }

        return in_array($driver, $drivers);
    }

    /**
     * Sets the cache for the database connection.
     *
     * @param mixed $cache The cache to be set.
     * @return self Returns an instance of the Database class.
     */
    public function setCache(mixed $cache): self
    {
        if (!is_object($cache)) {
            $cache = AutoInitialized::turnInto($cache);
        }

        if (!($cache instanceof Cache)) {
            throw new \InvalidArgumentException('Invalid cache instance');
        }

        $this->cache = $cache;

        return $this;
    }

    /**
     * Sets the connections for the database.
     *
     * @param array $connections An array of database connections.
     * @return void
     */
    public function setConnections(array $connections)
    {
        if (count($connections)) {
            foreach ($connections as $config) {
                list($key, $dsn, $user, $password, $options) = ArrayHelper::elements(['key', 'dsn', 'user', 'password', 'options'], $config, [false, false, null, null, []]);
                if (!$key || !$dsn) {
                    continue;
                }
                $this->addConnection($key, $dsn, $user, $password, $options);
            }
        }
    }

    /**
     * Sets the default value for a given key.
     *
     * @param string $key The key to set the default value for.
     * @return self The current instance of the Database class.
     */
    public function setDefault(string $key): self
    {
        if (!empty($key)) {
            $this->selectConnection($key);
        }
        return $this;
    }

    /**
     * Set the value of driver
     *
     * @return  self
     */
    public function setDriver(Driver $driver): self
    {
        if ($this->driver !== $driver) {
            $this->driver       = $driver;
            $this->queryCounter = 0;
        }
        return $this;
    }

    /**
     * Begins a transaction.
     *
     * @return bool Returns true if the transaction was successfully started, false otherwise.
     */
    public function begin(): bool
    {
        return $this->getDriver()->begin();
    }

    /**
     * Commits the current transaction.
     *
     * @return bool Returns true if the transaction is successfully committed, false otherwise.
     */
    public function commit(): bool
    {
        return $this->getDriver()->commit();
    }


    /**
     * Rolls back the current transaction.
     *
     * @return bool Returns true if the rollback was successful, false otherwise.
     */
    public function rollback():bool
    {
        return $this->getDriver()->rollback();
    }

    /**
     * Executes the prepared statement and returns the result.
     *
     * @return mixed The result of the executed statement.
     */
    public function execute(): mixed
    {
        return $this->queryInternal('');
    }

    /**
     * Fetches data from the database.
     *
     * @param array $args Optional arguments for the fetch operation.
     * @return mixed The fetched data.
     */
    public function fetch($args = []): mixed
    {
        return $this->queryInternal('fetch', $args);
    }

    /**
     * Fetches all rows from the database based on the provided arguments.
     *
     * @param array $args An optional array of arguments to filter the query.
     * @return mixed Returns the fetched rows from the database.
     */
    public function fetchAll($args = []): mixed
    {
        return $this->queryInternal('fetchAll', $args);
    }

    /**
     * Fetches a single column from the next row of a result set.
     *
     * @param array $args An optional array of arguments.
     * @return mixed The value of the requested column from the next row of the result set.
     */
    public function fetchColumn($args = []): mixed
    {
        return $this->queryInternal('fetchColumn', $args);
    }

    /**
     * Loads an SQL query.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params An optional array of parameters to bind to the query.
     * @return self Returns an instance of the Database class.
     */
    public function loadSql($sql, $params = []): self
    {
        foreach ($params as $key => &$value) {
            if (is_array($value)) {
                $array  = array_values($value);
                $pieces = [];
                array_walk($array, function ($element) use (&$pieces) {
                    if (is_scalar($element)) {
                        $pieces[] = $this->getDriver()->getPDO()->quote($element);
                    }
                });

                $value = join(',', $pieces);
            } elseif (is_string($value)) {
                $value = $this->getDriver()->getPDO()->quote($value);
            } else {
                continue;
            }
        }

        $this->queryStr    = $sql;
        $this->queryParams = $params;

        return $this;
    }

    /**
     * Prepares an SQL statement for execution.
     *
     * @param string|null $sql The SQL statement to prepare.
     * @param array $params An array of values to bind to the placeholders in the SQL statement.
     * @param array $options A set of key-value pairs that provide additional options for the prepared statement.
     *
     * @return bool|PDOStatement Returns a PDOStatement object representing the prepared statement, or false on failure.
     */
    public function prepare($sql = null, array $params = [], array $options = []): bool | PDOStatement
    {
        return $this->getDriver()->prepare($sql, $params, $options);
    }

    /**
     * Executes a database query internally.
     *
     * @param string $method The method to execute (e.g., "fetch", "fetchAll", "fetchColumn", etc.).
     * @param array $args Optional arguments for fetching the query results.
     * @return mixed The result of the query execution.
     */
    protected function queryInternal(string $method, array $args = []): mixed
    {
        $sql = $this->buildSql();

        if ($this->getCache()->isEnabled() && $this->isCacheable($sql)) {
            $cacheKey = $this->buildCacheID($sql, $method, $this->driver->getSignature());
            if ($cached = $this->getCache()->get($cacheKey)) {
                return $cached;
            }
        }

        try {
            $this->beforeQuery();
            $sth = $this->prepare($sql, []);
            $sth->execute();
            $result                  = empty($method) ? $sth->rowCount() : call_user_func_array([$sth, $method], $args);
            $this->queryRowsAffected = is_array($result) ? count($result) : $sth->rowCount();
            $sth->closeCursor();
            $this->afterQuery();
        } catch (\PDOException $exception) {
            $errorInfo = $exception->errorInfo;
            $error     = new ESQLError($errorInfo[2], $errorInfo[1]);
            if ($errorInfo[0] != $errorInfo[1]) {
                $error->setSQLState((int) $errorInfo[0]);
            }

            throw $error;
        }

        if ($this->getCache()->isEnabled() && isset($cacheKey)) {
            $this->getCache()->save($cacheKey, $result);
        }

        return $result;
    }

    /**
     * This method is called after executing a database query.
     * It can be overridden in child classes to perform additional actions or modifications.
     */
    protected function afterQuery()
    {
        /** do something */
    }

    /**
     * This method is called before executing a database query.
     * It can be overridden in child classes to perform any necessary setup or validation.
     *
     * @return void
     */
    protected function beforeQuery()
    {
        $this->queryCounter++;
    }

    /**
     * Get the value of dsn
     */
    public function getDsn()
    {
        return $this->getDriver()->getDsn();
    }

    /**
     * Set the value of dsn
     *
     * @return  self
     */
    public function setDsn(string $dsn): self
    {
        $this->getDriver()->setConnected(false)->setDsn($dsn);
        return $this;
    }

    /**
     * Get the value of user
     */
    public function getUsername()
    {
        return $this->getDriver()->getUsername();
    }

    /**
     * Set the value of user
     *
     * @return  self
     */
    public function setUsername(string $user): self
    {
        $this->getDriver()->setConnected(false)->setUsername($user);
        return $this;
    }

    /**
     * Get the value of password
     */
    public function getPassword()
    {
        return $this->getDriver()->getPassword();
    }

    /**
     * Set the value of password
     *
     * @return  self
     */
    public function setPassword(string $password): self
    {
        $this->getDriver()->setConnected(false)->setPassword($password);
        return $this;
    }

    /**
     * Get the value of options
     */
    public function getOptions(): array
    {
        return $this->getDriver()->getOptions();
    }

    /**
     * Set the value of options
     *
     * @return  self
     */
    public function setOptions(array $options): self
    {
        $this->getDriver()->setConnected(false)->setOptions($options);
        return $this;
    }
}
/** End of Database **/
