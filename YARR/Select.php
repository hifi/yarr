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

require_once 'Zend/Db/Select.php';

require_once 'YARR/Abstract.php';

class YARR_Select extends Zend_Db_Select implements Countable, Iterator
{
    protected $adapter;
    protected $class;
    protected $iterator_stmt;
    protected $iterator_pos;
    protected $iterator_cur;

    static function fromObject($class, $adapter = false)
    {
        $select = new YARR_Select($class::table(), $adapter);
        $select->asObject($class);
        return $select;
    }

    static function fromTable($table, $adapter = false)
    {
        return new YARR_Select($table, $adapter);
    }

    public function __construct($table, $adapter = false)
    {
        $this->adapter = $adapter ? $adapter : YARR_Abstract::getAdapter();
        $this->class = false;
        parent::__construct($this->adapter);
        $this->from($table);
    }

    public function asArray()
    {
        $this->class = false;
        return $this;
    }

    public function asObject($class)
    {
        $this->class = $class;
        return $this;
    }

    public function getOne()
    {
        $row = $this->adapter->fetchRow($this);

        if ($this->class)
            return $row ? new $this->class($row) : null;

        return $row ? $row : null;
    }

    public function getAll()
    {
        $rows = $this->adapter->fetchAll($this);

        if ($this->class) {
            $objs = array();

            foreach ($rows as $row) {
                $objs[] = new $this->class($row);
            }

            return $objs;
        }

        return $rows;
    }

    public function fetchOne()
    {
        return $this->adapter->fetchOne($this);
    }

    public function count()
    {
        $select = clone $this;
        return $this->adapter->fetchOne($select->reset('columns')->columns('COUNT(*)'));
    }

    public function copy()
    {
        return clone $this;
    }

    public function current()
    {
        if ($this->class)
            return $this->iterator_cur ? new $this->class($this->iterator_cur) : null;

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
        $this->iterator_stmt = $this->adapter->query($this);
        $this->iterator_pos = 0;
        $this->iterator_cur = $this->iterator_stmt->fetch();
    }

    public function valid()
    {
        return ($this->iterator_cur !== false);
    }
}
