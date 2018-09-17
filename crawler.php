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

    public function getFromCNAFacebookPage($timestamp)
    {
        $access_token = getenv('FB_ACCESSTOKEN');

        $until = $timestamp + 86400;
        $url = 'https://graph.facebook.com/148395741852581/feed?limit=100&until=' . $until . '&access_token=' . urlencode($access_token) . '&fields=message,link,picture,created_time';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $json = json_decode(curl_exec($curl));

        $ret = new StdClass;
        $paper_map = array(
            '中國時報' => '中國時報',
            '中時' => '中國時報',
            '聯合報' => '聯合報',
            '聯合' => '聯合報',
            '自由時報' => '自由時報',
            '自時報' => '自由時報', // From https://www.facebook.com/148395741852581/photos/a.187493897942765.56864.148395741852581/918008641557950/?type=1&permPage=1
            '自由' => '自由時報',
            '蘋果日報' => '蘋果日報',
            '蘋果' => '蘋果日報',
        );
        foreach ($json->data as $post) {
            if (false !== strpos($post->message, '頭條')) {
            } elseif (false !== strpos($post->message, '四大報頭')) {
                // https://www.facebook.com/148395741852581/photos/a.187493897942765.56864.148395741852581/1042588335766646/?type=1&permPage=1 漏字
            } elseif (strpos($post->message, '中時') and strpos($post->message, '自由') and strpos($post->message, '聯合')) {
            } elseif (strpos($post->message, '中國時報') and strpos($post->message, '自由') and strpos($post->message, '聯合')) {
            } else {
                continue;
            }
            if (false === strpos($post->message, '蘋果')) {
                continue;
            }
            if (strtotime($post->created_time) < $timestamp) {
                break;
            }
            $message = $post->message;
            $message = str_replace('【小編報頭條】中時', '【小編報頭條】' . "\n" . '中時', $message);
            $message = preg_replace('#http[^\s]*#', '', $message);
            $message = str_replace('★', '', $message);
            $message = str_replace('●', '', $message);
            $message = str_replace('◎', '', $message);
            $message = str_replace('＊', '', $message);
            $message = str_replace('※', '', $message);
            $message = str_replace('↓', '', $message); // https://www.facebook.com/photo.php?fbid=801459959879486&set=a.187493897942765.56864.148395741852581&type=1&stream_ref=10
            $message = str_replace('↑', '', $message); // https://www.facebook.com/photo.php?fbid=810338305658318&set=a.187493897942765.56864.148395741852581&type=1&stream_ref=10
            $lines = explode("\n", $message);
            $ret->title = $lines[0];
            $ret->link = $post->link;
            $ret->image_link = $post->picture;
            $ret->time = $timestamp;
            $ret->headlines = array();
            foreach ($lines as $line) {
                $line = str_replace('》', ':', $line);
                $line = str_replace('：', ':', $line);
                if (FALSE === strpos($line, ':')) {
                    continue;
                }
                list($paper, $title) = explode(':', trim($line), 2);
                $paper = str_replace(' ', '', $paper);
                if (!array_key_exists($paper, $paper_map)) {
                    continue;
                }
                $ret->headlines[] = array($paper_map[$paper], $title);

            }

            if (count(array_unique(array_map(function($a){ return $a[0]; }, $ret->headlines))) == 4) {
                return $ret;
            }
        }
    }

    public function getFromCNAPage($time)
    {
        error_log(date('Ymd', $time));
        $papers = array('聯合報', '中國時報', '蘋果日報', '自由時報');
        for ($news_id = 0; $news_id < 10; $news_id ++) {
            $url = sprintf("http://www.cna.com.tw/news/firstnews/%s5%03d-1.aspx", date('Ymd', $time), $news_id);
            $content = file_get_contents($url);
            $doc = new DOMDocument;
            @$doc->loadHTML($content);

            $title = trim($doc->getElementsByTagName('title')->item(0)->nodeValue);
            error_log($title);
            if (!preg_match('#' . sprintf("%d月%d日\s*台灣各報頭條速報", date('m', $time), date('d', $time)) . '#', $title)) {
                continue;
            }

            $body = $doc->saveHTML($doc->getElementsByTagName('section')->item(0));
            preg_match_all('#>([^<：》]*)[》：]([^<]*)<#u', $body, $matches);
            $papers = array('聯合報', '中國時報', '蘋果日報', '自由時報');
            $article = new StdClass;
            $article->link = $url;
            $article->title = $title;
            if ($img_dom = $doc->getElementsByTagName('section')->item(0)->getElementsByTagName('img')->item(0)) {
                $article->image_link = $img_dom->getAttribute('src');
            }
            $article->time = $time;

            $headlines = array();
            $papers = array_combine($papers, $papers);

            foreach ($matches[1] as $idx => $paper) {
                if (!array_key_exists($paper, $papers)) {
                    continue;
                }
                unset($papers[$paper]);

                $headlines[] = array($paper, htmlspecialchars_decode($matches[2][$idx]));
            }
            $article->headlines = $headlines;
            if (count($papers)) {
                throw new Exception("no 4 news");
            }
            return $article;

        }
        throw new Exception("not found");
    }

    public function main()
    {
        // 從中央社新聞抓 30 天資料
        for ($i = 0; $i < 30; $i ++) {
            $time = strtotime('00:00:00 -' . $i . 'day');
            if ($article = HeadLineLog::find($time)) {
                if (!json_decode($article->data)->headlines) {
                    $article->delete();
                } else {
                    // 資料庫中已經有了就不用再找了
                    continue;
                }
            }
            try {
                $article = $this->getFromCNAPage($time);
            } catch (Exception $e) {
                continue;
            }
            if (!$headlinelog = HeadLineLog::find($article->time)) {
                HeadLineLog::insert(array(
                    'time' => $article->time,
                    'data' => json_encode($article),
                ));
            }
        }

        // 再從 中央社粉絲團搜尋 30 天的資料
        for ($i = 0; $i < 30; $i ++) {
            $time = strtotime('00:00:00 -' . $i . 'day');
            if (HeadLineLog::find($time)) {
                // 資料庫中已經有了就不用再找了
                continue;
            }
            try {
                $article = $this->getFromCNAFacebookPage($time);
            } catch (Exception $e) {
                continue;
            }
            if ($article and !$headlinelog = HeadLineLog::find($article->time)) {
                HeadLineLog::insert(array(
                    'time' => $article->time,
                    'data' => json_encode($article),
                ));
            }
        }
        exit;


    }
}

$c = new Crawler;
$c->main();
