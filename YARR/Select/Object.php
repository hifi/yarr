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

require_once 'YARR/Select.php';

require_once 'YARR/Abstract.php';

class YARR_Select_Object extends YARR_Select
{
    protected $class;

    function __construct($class)
    {
        parent::__construct($class::table(), $class::getAdapter());
        $this->class = $class;
    }

    public function getOne()
    {
        $row = parent::getOne();
        return $row ? new $this->class($row) : null;
    }

    public function getAll()
    {
        $rows = parent::getAll();

        $ret = array();
        foreach ($rows as $row) {
            $ret[] = new $this->class($row);
        }

        return $ret;
    }

    public function current()
    {
        return $this->iterator_cur ? new $this->class($this->iterator_cur) : null;
    }
}
