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
 * ColumnBuilderInterface
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 13.08.2024 19:00:00
 */
interface ColumnBuilderInterface
{
    public function after(string $column): self;
    public function comment(string $str): self;
    public function defaultValue(mixed $default, bool $quoted = true): self;
    public function first(): self;
    public function name(string $str): self;
    public function notNull(bool $val = true): self;
    public function unique(bool $val = true): self;
    public function unsigned(bool $val = true): self;
}
/** End of ColumnBuilderInterface **/
