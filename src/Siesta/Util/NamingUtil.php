<?php

namespace Siesta\Util;

class NamingUtil
{

    /**
     * @param string $string
     * @return string
     */
    public static function camelCaseToUnderscore(string $string): string
    {
        return preg_replace('/([a-z])([A-Z])/', '$1_$2', $string);
    }

    /**
     * @param string $string
     * @return string
     */
    public static function camelCaseToUpperCaseUnderscore(string $string): string
    {
        return strtoupper(self::camelCaseToUnderscore($string));
    }

    /**
     * @param string $string
     * @return string
     */
    public static function camelCaseToLowerCaseUnderscore(string $string): string
    {
        return strtolower(self::camelCaseToUnderscore($string));
    }


}