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

use PDO;
use PDOStatement;
use WebStone\PDO\Exceptions\EDatabaseError;
use WebStone\PDO\Exceptions\ESQLError;
use WebStone\Stdlib\Classes\AutoInitialized;

class Driver extends AutoInitialized
{
    protected ?string $dsn      = null;
    protected ?string $username = null;
    protected ?string $password = null;
    protected bool $connected   = false;
    protected array $options    = [];
    protected ?PDO $pdo         = null;

    /**
     * Begins a transaction.
     *
     * @return bool Returns true if the transaction was successfully started, false otherwise.
     */
    public function begin(): bool
    {
        return $this->getPDO()->beginTransaction();
    }

    /**
     * Commits the current transaction.
     *
     * @return bool Returns true if the transaction is successfully committed, false otherwise.
     */
    public function commit(): bool
    {
        return $this->getPDO()->commit();
    }

    /**
     * Rolls back the current transaction.
     *
     * @return bool Returns true on success, false on failure.
     */
    public function rollback(): bool
    {
        return $this->getPDO()->rollBack();
    }

    /**
     * Create new PDO object & establishes a connection to the database.
     *
     * @return bool
     */
    protected function connect(): bool
    {
        if ($this->getConnected() == true) {
            return true;
        }

        try {
            $this->setPdo(new PDO($this->getDsn(), $this->getUsername(), $this->getPassword(), $this->getOptions()));
        } catch (\PDOException $exception) {
            $matches = [];
            $dbname  = (preg_match('/dbname=(\w+)/', $this->getDsn(), $matches)) ? $matches[1] : 'undefined';
            throw new EDatabaseError(sprintf("Could not connect to database (%s), original error: %s", $dbname, $exception->getMessage()), $exception->getCode());
        }

        return $this->getConnected();
    }

    /**
     * Disconnects from the database.
     *
     * This method closes the connection to the database server.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->pdo       = null;
        $this->connected = false;
    }

    /**
     * Executes a SQL query with optional parameters and options.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params An array of parameters to bind to the query.
     * @param array $options An array of options for the query execution.
     * @return mixed The result of the query execution.
     */
    public function execute(string $sql, array $params = [], array $options = [])
    {
        try {
            $statement = $this->prepare($sql, $options);
            if ($statement instanceof PDOStatement) {
                if (count($params)) {
                    $this->bindParams($statement, $params);
                }
                $statement->execute();
            }
            return $statement;
        } catch (\PDOException $exception) {
            $err = new ESQLError($exception->getMessage(), 0);
            $err->setSQLState($exception->getCode());
            throw $err;
        }
    }

    /**
     * Retrieves the last inserted ID from the database.
     *
     * @param string|null $sequence The name of the sequence object from which the ID should be retrieved.
     * @return mixed The last inserted ID.
     */
    public function getLastInsertID(?string $sequence = null): mixed
    {
        return $this->getPDO()->lastInsertId($sequence);
    }

    /**
     * Returns the underlying PHP PDO instance.
     *
     */
    public function getPDO(): PDO
    {
        $this->connect();

        return $this->pdo;
    }

    /**
     * Retrieves the parameter type for a given value.
     *
     * @param mixed $value The value for which to retrieve the parameter type.
     * @return int The parameter type.
     */
    public function getParamType($value): int
    {
        if (is_null($value)) {
            return PDO::PARAM_NULL;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_int($value)) {
            return PDO::PARAM_INT;
        } else {
            return PDO::PARAM_STR;
        }
    }

    /**
     * Returns the signature of the driver.
     *
     * @return string The signature of the driver.
     */
    public function getSignature(): string
    {
        return md5($this->dsn . serialize($this->options));
    }

    /**
     * Returns the version number of the database.
     *
     * @return int
     */
    public function getVersion(): int
    {
        return $this->getPDO()->getAttribute(PDO::ATTR_CLIENT_VERSION);
    }

    /**
     * Prepares an SQL statement for execution.
     *
     * @param string $sql The SQL statement to prepare.
     * @param array $params [optional] An array of values to bind to the placeholders in the SQL statement.
     * @param array $options [optional] Additional options for preparing the statement.
     * @return bool|PDOStatement Returns a PDOStatement object representing the prepared statement, or false on failure.
     */
    public function prepare($sql, $params = [], $options = []): bool | PDOStatement
    {
        $result = $this->getPDO()->prepare($sql, $options);
        if ($result instanceof PDOStatement && count($params)) {
            $this->bindParams($result, $params);
        }

        return $result;
    }

    /**
     * Binds parameters to a prepared statement.
     *
     * @param PDOStatement $statement The prepared statement to bind the parameters to.
     * @param array $params The parameters to bind.
     * @return void
     */
    protected function bindParams(PDOStatement &$statement, array $params = []): void
    {
        foreach ($params as $key => &$value) {
            if (is_integer($key)) {
                if (is_null($value)) {
                    $statement->bindValue($key + 1, null, PDO::PARAM_NULL);
                } else {
                    $statement->bindParam($key + 1, $value, $this->getParamType($value));
                }
            } else {
                if (is_null($value)) {
                    $statement->bindValue($key, null, PDO::PARAM_NULL);
                } else {
                    $statement->bindParam($key, $value, $this->getParamType($value));
                }
            }
        }
    }

    /**
     * Set the value of pdo
     *
     * @return  self
     */
    public function setPdo(PDO $pdo): self
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if (strtolower($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql') {
            $ver      = floatval($this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
            $encoding = ($ver >= 5.5) ? 'utf8mb4' : 'utf8';
            $this->pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES $encoding");
            $this->pdo->exec(" SET NAMES $encoding");
        }

        $this->setConnected(true);

        return $this;
    }

    /**
     * Get the value of dsn
     */
    public function getDsn(): string
    {
        return $this->dsn;
    }

    /**
     * Set the value of dsn
     *
     * @return  self
     */
    public function setDsn($dsn): self
    {
        $this->dsn = $dsn;

        return $this;
    }

    /**
     * Get the value of user
     */
    public function getUsername(): string | null
    {
        return $this->username;
    }

    /**
     * Set the value of user
     *
     * @return  self
     */
    public function setUsername($username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get the value of password
     */
    public function getPassword(): string | null
    {
        return $this->password;
    }

    /**
     * Set the value of password
     *
     * @return  self
     */
    public function setPassword($password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get the value of options
     */
    public function getOptions(): array | null
    {
        return $this->options;
    }

    /**
     * Set the value of options
     *
     * @return  self
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the value of connected
     */
    public function getConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Set the value of connected
     *
     * @return  self
     */
    public function setConnected(bool $value): self
    {
        if ($this->connected == true && $value == false) {
            $this->disconnect();
        }
        $this->connected = $value;

        return $this;
    }
}

/* End of file Driver.php */
