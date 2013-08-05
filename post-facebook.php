<?php

include(__DIR__ . '/webdata/init.inc.php');
include(__DIR__ . '/webdata/stdlibs/facebook-php-sdk-master/src/facebook.php');

class MyFacebook extends BaseFacebook
{
    public $_persistent_data = array();

    protected function setPersistentData($key, $value)
    {
        $this->_persistent_data[$key] = $value;
    }

    protected function getPersistentData($key, $default = false)
    {
        return array_key_exists($key, $this->_persistent_data) ? $this->_persistent_data[$key] : $default;
    }

    protected function clearPersistentData($key)
    {
        unset($this->_persistent_data[$key]);
    }

    protected function clearAllPersistentData()
    {
        $this->_persistent_data = array();
    }
}

$config = array(
    'appId' => getenv('FB_APPID'),
    'secret' => getenv('FB_SECRET'),
);

$facebook = new MyFacebook($config);
$facebook->setAccessToken(getenv('FB_ACCESSTOKEN'));

$params = array();
$time = strtotime('0:0:0 -30day');
if (!$log = HeadLineLog::find($time)) {
    exit;
}
if ($log->facebook_id) {
    exit;
}
$data = json_decode($log->data);
$message = date('Y/m/d', $time) . '四大報頭版新聞' . PHP_EOL;
foreach ($data->headlines as $headline) {
    list($paper, $title) = $headline;
    $message .= $paper . ':' . $title . PHP_EOL;
}

for ($i = 0; $i < 3; $i ++) {
    $ret = $facebook->api('lagnews.tw/feed', 'post', array(
        'message' => $message,
        'link' => $data->link,
    ));
    if (!$ret['id']) {
        continue;
    }

    $log->update(array('facebook_id' => $ret['id']));
    break;
}

// TODO: 如果失敗要寄信通知
