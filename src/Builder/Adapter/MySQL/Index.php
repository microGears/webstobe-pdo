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

use WebStone\PDO\Builder\BuilderTrait;
use WebStone\PDO\Builder\IndexBuilder;
use WebStone\PDO\Exceptions\EDatabaseError;

/**
 * IndexBuilder
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 14.08.2024 11:42:00
 */
class Index extends IndexBuilder
{
    use BuilderTrait;
    protected $map_type = [
        self::TYPE_PK       => 'PRIMARY KEY',
        self::TYPE_INDEX    => 'INDEX',
        self::TYPE_UNIQUE   => 'UNIQUE',
        self::TYPE_FULLTEXT => 'FULLTEXT',
      ];
  
      protected function composeIndex():string
      {
          $result = '';
  
          if (empty( $this->columns )) {
              throw new EDatabaseError( 'Column information is required for that operation.' );
          }
  
          if (empty( $this->index_name )) {
              $this->index_name = implode( '_', $this->columns );
          }
  
          $indexName = $this->protectIdentifiers( $this->index_name );
          $column    = $this->protectIdentifiers( $this->columns );
  
          $result .= $this->map_type[$this->type];
  
          if ($this->type == static::TYPE_PK) {
              $result .= ' ('.implode( ',', $column ).')';
          } else {
              $result .= ' '.$indexName.' ('.implode( ',', $column ).')';
          }
  
          return $result;
      }
}
/** End of IndexBuilder **/