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
 * IndexBuilderInterface
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 14.08.2024 11:49:00
 */
interface IndexBuilderInterface
{
    public function column(mixed $column): self;
    public function name(string $index_name): self;
}
/** End of IndexBuilderInterface **/
