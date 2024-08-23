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

use WebStone\PDO\Exceptions\EDatabaseError;
use WebStone\Stdlib\Classes\AutoInitialized;

/**
 * DriverAdapters
 *
 * @author Maxim Kirichenko <kirichenko.maxim@gmail.com>
 * @datetime 23.08.2024 10:59:00
 */
abstract class DriverAdapters extends AutoInitialized
{
    protected static array $adapters = [];

    public static function appendAdapter(string $driver_type, string $driver_class, bool $overwrite = false): void
    {
        if (isset(static::$adapters[$driver_type]) && !$overwrite) {
            throw new EDatabaseError("Adapter type `{$driver_type}` already exists");
        }

        static::$adapters[$driver_type] = $driver_class;
    }

    public static function updateAdapter(string $driver_type, string $driver_class): void
    {
        if (!isset(static::$adapters[$driver_type])) {
            static::appendAdapter($driver_type, $driver_class);
            return;
        }

        static::$adapters[$driver_type] = $driver_class;
    }

    public static function removeAdapter(string $driver_type): void
    {
        if (!isset(static::$adapters[$driver_type])) {
            throw new EDatabaseError("Adapter type `{$driver_type}` does not exist");
        }
        unset(static::$adapters[$driver_type]);
    }

    public static function getAdapter(string $driver_type): string | null
    {
        return static::$adapters[$driver_type] ?? null;
    }
}
/** End of DriverAdapters **/