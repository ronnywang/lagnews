<?php

class IndexController extends Pix_Controller
{
    public function indexAction()
    {
        $this->view->before = intval($_GET['before']);
    }

    public function jsonAction()
    {
        $ret = new StdClass;
        $ret->data = array();
        $time = null;
        if (intval($_GET['before'])) {
            $search = "`time` < " . intval($_GET['before']);
        } else {
            $search = '1';
        }
        foreach (HeadLineLog::search($search)->order('`time` DESC')->limit(30) as $log) {
            $ret->data[] = json_decode($log->data);
            $time = $log->time;
        }
        if (!is_null($time)) {
            $ret->next_link = "http://{$_SERVER['HTTP_HOST']}/index/json?before={$time}";
        }

        return $this->json($ret);
    }
}
