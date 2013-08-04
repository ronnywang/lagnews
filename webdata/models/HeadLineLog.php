<?php

class HeadLineLog extends Pix_Table
{
    public function init()
    {
        $this->_name = 'headline_log';
        $this->_primary = 'time';

        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['data'] = array('type' => 'text');
    }
}
