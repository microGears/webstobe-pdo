<?php
/**
 * This file is part of WebStone\PDO.
 *
 * (C) 2009-2024 Maxim Kirichenko <kirichenko.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace WebStone\PDO\Exceptions;

class ESQLError extends EDatabaseError
{
    /**
     * @var string
     */
    private $sql_state;

    /**
     * To String prints both code and SQL state.
     *
     * @return string
     */
    public function __toString():string
    {
        return '['.$this->getSQLState().'] - '.$this->getMessage()."\n".$this->getTraceAsString();
    }

    /**
     * Returns an ANSI-92 compliant SQL state.
     *
     * @return string
     */
    public function getSQLState()
    {
        return $this->sql_state;
    }

    /**
     * Returns the raw SQL STATE, possibly compliant with
     * ANSI SQL error codes - but this depends on database driver.
     *
     * @param string $value SQL state error code
     *
     * @return void
     */
    public function setSQLState(int $value)
    {
        $this->sql_state = $value;
    }
}

/* End of file ESqlError.php */
