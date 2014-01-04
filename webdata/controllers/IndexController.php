<?php

class IndexController extends Pix_Controller
{
    public function indexAction()
    {
        $this->view->before = intval($_GET['before']);
    }

    public function healthAction()
    {
        // 檢查昨天是否有正常抓到資料
        if (!HeadLineLog::find(strtotime('today') - 86400)) {
            echo 'warning';
        } else {
            echo 'ok';
        }
        exit;
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

    public function rssAction()
    {
        $this->view->title = '四大報歷史頭條 RSS';
        $this->view->link = 'http://oldpaper.g0v.ronny.tw';
        $items = array();
        if ($_GET['type'] == 'bypaper') { // 依報紙找出某年
            if (!strval($_GET['paper'])) {
                throw new Exception("需要指定 paper={報紙}");
            }
            $paper = $_GET['paper'];
            $year = intval($_GET['year']) ?: date('Y');
            $start = mktime(0, 0, 0, 1, 1, $year);
            $end = mktime(0, 0, 0, 1, 1, $year + 1);

            foreach (HeadLineLog::search("`time` >= $start AND `time` < $end") as $log) {
                $log = json_decode($log->data);
                foreach ($log->headlines as $headlines) {
                    list($p, $title) = $headlines;
                    if ($p == $paper) {
                        $item = new StdClass;
                        $item->description = $title;
                        $item->time = $log->time;
                        $item->link = $log->link;
                        $items[] = $item;
                    }
                }
            }
        } else { // 依月份找出所有
            $year = intval($_GET['year']) ?: date('Y');
            $month = intval($_GET['month']) ?: date('m');

            $start = mktime(0, 0, 0, $month, 1, $year);
            $end = strtotime('+1 month', $start);
            foreach (HeadLineLog::search("`time` >= $start AND `time` < $end") as $log) {
                $log = json_decode($log->data);
                foreach ($log->headlines as $headlines) {
                    list($p, $title) = $headlines;
                    $item = new StdClass;
                    $item->description = $title;
                    $item->time = $log->time;
                    $item->link = $log->link . '#' . $p;
                    $items[] = $item;
                }
            }
        }
        $this->view->items = $items;
    }
}
