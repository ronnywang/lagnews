<?php

include(__DIR__ . '/webdata/init.inc.php');

$article = new StdClass;
$article->title = '0706四大早報頭條 | 要聞 | 即時新聞 | 聯合新聞網';
$article->link = 'http://udn.com/NEWS/BREAKINGNEWS/BREAKINGNEWS1/8010790.shtml';
$article->image_link = '';
$article->time = strtotime('2013/7/06 0:0:0');
$article->headlines = array(
    array('聯合報', '女雙晉決賽 謝淑薇搶溫網金杯'),
    array('蘋果日報', '恨爸當眾打頭 少女負氣飆車雙亡'),
    array('中國時報', '謝淑薇勇闖溫網冠軍賽'),
    array('自由時報', '府院虛晃一招 劉政鴻：大埔案就是拆'),
);
HeadLineLog::insert(array(
    'time' => $article->time,
    'data' => json_encode($article, JSON_UNESCAPED_UNICODE),
));
