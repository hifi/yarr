<?php

/*
 * Copyright (c) 2011, 2013 Toni Spets <toni.spets@iki.fi>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

class YARR_Format
{
    protected $_obj;
    protected $_type;

    static $formatters = array();

    static function initialized()
    {
        return count(self::$formatters) > 0;
    }

    static function register($type, $to, $from)
    {
        self::$formatters[$type] = array(
            $to,
            $from
        );
    }

    static function registerDefault()
    {
        if (count(self::$formatters) == 0) {
            self::$formatters['raw'] = array(
                function($to) { return $to; },
                function($from) { return $from; }
            );
            self::$formatters['html'] = array(
                function($to) { return htmlspecialchars($to); },
                function($from) { return htmlspecialchars_decode($from); }
            );
        }
    }

    function __construct($obj)
    {
        $this->_obj = $obj;
    }

    public function supported($type)
    {
        return array_key_exists($type, self::$formatters);
    }

    public function type($type)
    {
        $this->_type = $type;
        return $this;
    }

    static public function to($type, $value)
    {
        if (array_key_exists($type, self::$formatters)) {
            $formatter = self::$formatters[$type][0];
            return $formatter($value);
        }

        return $value;
    }

    static public function from($type, $value)
    {
        if (array_key_exists($type, self::$formatters)) {
            $formatter = self::$formatters[$type][1];
            return $formatter($value);
        }

        return $value;
    }

    function __get($key)
    {
        return self::to($this->_type, $this->_obj->$key);
    }

    function __set($key, $value)
    {
        return ($this->_obj->$key = self::from($this->_type, $value));
    }
}
