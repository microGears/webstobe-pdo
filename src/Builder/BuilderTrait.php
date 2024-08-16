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

trait BuilderTrait
{
    protected $identifierDelimiter = "`";
    protected $reservedIdentifiers = ['*'];

    public function quote($value)
    {
        if (is_string( $value )) {
            $value = trim( $value, "'" );
            $value = "'".$this->quoteStr( $value )."'";
        } elseif (is_numeric( $value )) {
            $value = "'$value'";
        } elseif (is_bool( $value )) {
            $value = ($value === false) ? 0 : 1;
        } elseif (is_null( $value )) {
            $value = 'NULL';
        }

        return $value;
    }

    public function quoteIdentifier($identifier)
    {

        $delimiter = $this->identifierDelimiter;

        if (empty( $delimiter ) || ($identifier == '*')) {
            return $identifier;
        }
        $identifier = explode( ".", $identifier );
        $identifier = array_map(
          function ($part) use ($delimiter) {
              if ($part == '*') {
                  return $part;
              } else {
                  return $delimiter.str_replace( $delimiter, "$delimiter$delimiter", $part ).$delimiter;
              }
          },
          $identifier
        );

        return implode( ".", $identifier );
    }

    public function quoteStr($str, $like = false)
    {
        if (is_array( $str )) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->quoteStr( $val, $like );
            }

            return $str;
        }

        // escape LIKE condition wildcards
        if ($like === true) {
            $str = str_replace( ['%', '_'], ['\\%', '\\_'], $str );
        }

        // escape default
        if (!empty( $str ) && is_string( $str )) {
            $str = str_replace( ['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $str );
        }

        return $str;
    }

    protected function protectIdentifiers($item, $protectIdentifiers = null)
    {
        if (!is_bool( $protectIdentifiers )) {
            $protectIdentifiers = true;
        }

        if (is_array( $item )) {
            $escaped_array = [];

            foreach ($item as $k => $v) {
                $escaped_array[$this->protectIdentifiers( $k )] = $this->protectIdentifiers( $v );
            }

            return $escaped_array;
        }

        // Convert tabs or multiple spaces into single spaces
        $item = preg_replace( '/[\t\n ]+/', ' ', (string)$item );        

        // If the item has an alias declaration we remove it and set it aside.
        // Basically we remove everything to the right of the first space
        if ($item != null && strpos( $item, ' ' ) !== false) {
            $alias = strstr( $item, ' ' );
            $item  = substr( $item, 0, -strlen( $alias ) );
        } else {
            $alias = '';
        }

        // This is basically a bug fix for queries that use MAX, MIN, etc.
        // If a parenthesis is found we know that we do not need to
        // escape the data or add a prefix.  There's probably a more graceful
        // way to deal with this, but I'm not thinking of it -- Rick
        if (strpos( $item, '(' ) !== false) {
            return "$item$alias";
        }

        // Break the string apart if it contains periods, then insert the table prefix
        // in the correct location, assuming the period doesn't indicate that we're dealing
        // with an alias. While we're at it, we will escape the components
        if (strpos( $item, '.' ) !== false) {
            if ($protectIdentifiers === true) {
                $item = $this->quoteIdentifier( $item );
            }

            return "$item$alias";
        }

        if ($protectIdentifiers === true and !in_array( $item, $this->reservedIdentifiers )) {
            $item = $this->quoteIdentifier( $item );
        }

        return "$item$alias";
    }
}