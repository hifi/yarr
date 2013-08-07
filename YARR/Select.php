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

require_once 'Zend/Db/Select.php';

require_once 'YARR/Abstract.php';

class YARR_Select extends Zend_Db_Select implements Countable, Iterator
{
    protected $db;
    protected $iterator_stmt;
    protected $iterator_pos;
    protected $iterator_cur;

    function __construct($table)
    {
        $this->db = YARR_Abstract::getDefaultAdapter();
        parent::__construct($this->db);
        $this->from($table);
    }

    public function getOne()
    {
        $row = $this->db->fetchRow($this);
        return $row ? $row : null;
    }

    public function getAll()
    {
        return $this->db->fetchAll($this);
    }

    public function fetchOne()
    {
        return $this->db->fetchOne($this);
    }

    public function count()
    {
        $select = clone $this;
        return $this->db->fetchOne($select->reset('columns')->columns('COUNT(*)'));
    }

    public function copy()
    {
        return clone $this;
    }

    public function current()
    {
        return $this->iterator_cur;
    }

    public function key()
    {
        return $this->iterator_pos;
    }

    public function next()
    {
        $this->iterator_cur = $this->iterator_stmt->fetch();
        $this->iterator_pos++;
    }

    public function rewind()
    {
        $this->iterator_stmt = $this->db->query($this);
        $this->iterator_pos = 0;
        $this->iterator_cur = $this->iterator_stmt->fetch();
    }

    public function valid()
    {
        return ($this->iterator_cur !== false);
    }
}
