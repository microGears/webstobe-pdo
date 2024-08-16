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

use stdClass;
use WebStone\Stdlib\Helpers\StringHelper;

class RecordsetItem
{
    protected ?stdClass $properties = null;

    public function __construct()
    {
        $this->__flush();
    }

    public function __get($name)
    {
        if (method_exists($this, $method = 'get' . StringHelper::normalizeName($name))) {
            return call_user_func([$this, $method]);
        }

        return property_exists($this->__getProperties(), $name) ? $this->__getProperties()->$name : null;
    }

    public function __set($name, $value)
    {
        if (method_exists($this, $method = 'set' . StringHelper::normalizeName($name))) {
            return call_user_func_array([$this, $method], [$value]);
        }

        return $this->__getProperties()->$name = $value;
    }

    public function __flush(): void
    {
        $this->properties = new stdClass();
    }

    protected function __getVars(): array
    {
        return get_object_vars($this->__getProperties());
    }

    protected function __getProperties(): stdClass
    {
        if ($this->properties === null) {
            $this->__flush();
        }

        return $this->properties;
    }

}

/* End of file RecordsetItem.php */
