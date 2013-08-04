<?php

include(__DIR__ . '/webdata/init.inc.php');

class Crawler
{
    protected function searchDom($doms, $attr, $class)
    {
        foreach ($doms as $dom) {
            if (in_array($class, explode(' ', $dom->getAttribute($attr)))) {
                return $dom;
            }
        }
        return null;
    }

    public function getFromDimension()
    {
        $url = 'http://dimension.tw/tag/dimension-%E8%AE%80%E5%A0%B1/';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);

        $content = str_replace('<head>', '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">', $content);

        $doc = new DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML($content);

        $articles = array();
        foreach ($doc->getElementsByTagName('article') as $article_dom) {
            $article = new StdClass;
            $header_dom = $article_dom->getElementsByTagName('header')->item(0);
            $article->title = $header_dom->getElementsByTagName('a')->item(0)->nodeValue;
            $article->link = $header_dom->getElementsByTagName('a')->item(0)->getAttribute('href');
            if (!preg_match('#4 大報頭條 (\d+)/(\d+)/(\d+)#', $article->title, $matches)) {
                throw new Exception('failed: ' . $article->link);
            }
            $article->time = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
            if ($img_dom = $this->searchDom($header_dom->getElementsByTagName('img'), 'class', 'attachment-thumbnail')) {
                $article->image_link = $img_dom->getAttribute('src');
            }
            $p_dom = $article_dom->getElementsByTagName('p')->item(0);
            if (!preg_match('/自由時報：(.*) 聯合報：(.*) 中國時報：(.*) 蘋果日報：(.*) 自由時報/', $p_dom->nodeValue, $matches)) {
                throw new Exception('failed: ' . $article->link);
            }
            $article->headlines = array(
                array('自由時報', $matches[1]),
                array('聯合報', $matches[2]),
                array('中國時報', $matches[3]),
                array('蘋果日報', $matches[4]),
            );
            $articles[] = $article;
        }
        return $articles;
    }

    public function getFromETToday($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);

        $doc = new DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML($content);

        $story_dom = $this->searchDom($doc->getElementsByTagName('div'), 'class', 'story');
        $paper = null;
        $headlines = array();

        $article = new StdClass;
        $article->link = $url;
        $article->title = $this->searchDom($doc->getElementsByTagName('meta'), 'property', 'og:title')->getAttribute('content');;
        $article->image_link = $this->searchDom($doc->getElementsByTagName('meta'), 'property', 'og:image')->getAttribute('content');;
        if (!preg_match('#http://www.ettoday.net/news/(\d\d\d\d)(\d\d)(\d\d)/#', $url, $matches)) {
            throw new Exception('invalid ' . $url);
        }
        $article->time = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);

        foreach ($story_dom->getElementsByTagName('strong') as $strong_dom) {
            $title = trim($strong_dom->nodeValue);
            if (is_null($paper)) {
                if (!preg_match('/【(.*)】/', $title, $matches)) {
                    continue;
                }
                $paper = $matches[1];
            } else {
                $headlines[] = array($paper, $title);
                $paper = null;
            }
        }
        $article->headlines = $headlines;
        return $article;
    }

    public function getFromETTodayByGoogle($date)
    {
        $query = '"' . $date . '四大報頭版"';
        $cx = getenv('SEARCH_ID');
        $key = getenv('SEARCH_KEY');
        $url = 'https://www.googleapis.com/customsearch/v1?key=' . urlencode($key) .'&cx=' . urlencode($cx) . '&q=' . urlencode($query);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);
        curl_close($curl);
        if (!$json = json_decode($content)) {
            throw new Exception('invalid ' . $url);
        }
        if (!$item = $json->items[0]) {
            throw new Exception('invalid ' . $url);
        }
        return $this->getFromETToday($item->link);
    }

    public function main()
    {
        // 先爬 dimensions 最新的
        $articles = $this->getFromDimension();
        foreach ($articles as $article) {
            if (!$headlinelog = HeadLineLog::find($article->time)) {
                HeadLineLog::insert(array(
                    'time' => $article->time,
                    'data' => json_encode($article, JSON_UNESCAPED_UNICODE),
                ));
            }
        }

        // 再從 google 搜尋 ettoday 七天的資料
        for ($i = 0; $i < 7; $i ++) {
            $time = strtotime('00:00:00 -' . $i . 'day');
            if (HeadLineLog::find($time)) {
                // 資料庫中已經有了就不用再找了
                continue;
            }
            try {
                $article = $this->getFromETTodayByGoogle(date('md', $time));
            } catch (Exception $e) {
                continue;
            }
            if (!$headlinelog = HeadLineLog::find($article->time)) {
                HeadLineLog::insert(array(
                    'time' => $article->time,
                    'data' => json_encode($article, JSON_UNESCAPED_UNICODE),
                ));
            }
        }
        exit;


    }
}

$c = new Crawler;
$c->main();
