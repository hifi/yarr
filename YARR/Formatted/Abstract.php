<?php

/*
 * Copyright (c) 2011 Toni Spets <toni.spets@iki.fi>
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

abstract class YARR_Formatted_Abstract extends YARR_Abstract
{
    protected $_format;

    function __construct($data)
    {
        parent::__construct($data);
        $this->_format = new YARR_Format($this);
    }

    function __get($k)
    {
        if ($this->_format->supported($k)) {
            return $this->_format->type($k);
        }

        return parent::__get($k);
    }

    function __set($k, $v)
    {
        if ($this->_format->supported($k)) {
            return $this->_format->type($k);
        }

        return parent::__set($k, $v);
    }
}