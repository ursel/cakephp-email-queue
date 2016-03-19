<?php

namespace EmailQueue\Database\Type;

use Cake\Database\Driver;
use Cake\Database\Type\StringType;

class SerializeType extends StringType
{
    public function toPHP($value, Driver $driver)
    {
        if ($value === null) {
            return;
        }

        return unserialize($value);
    }

    public function toDatabase($value, Driver $driver)
    {
        return serialize($value);
    }
    
    public function marshal($value)
    {
        return $value;
    }

    public function requiresToPhpCast()
    {
        return true;
    }
}
