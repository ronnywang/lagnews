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

    public function getFromApplePage($time)
    {
        $date = date('Ymd', $time);
        $query = '"各報頭條搶先" site:https://tw.appledaily.com/new/realtime/' . $date;
        $cx = getenv('SEARCH_ID');
        $key = getenv('SEARCH_KEY');
        $url = 'https://www.googleapis.com/customsearch/v1?key=' . urlencode($key) .'&cx=' . urlencode($cx) . '&q=' . urlencode($query);
        $obj = json_decode(file_get_contents($url));
        foreach ($obj->items as $item) {
            if (false === strpos($item->title, '各報頭條搶先')) {
                continue;
            }
            error_log($item->link);
            $content = file_get_contents($item->link);
            $papers = array('聯合報', '中國時報', '蘋果日報', '自由時報');
            $papers = array_combine($papers, $papers);

            $article = new StdClass;
            $article->link = $item->link;
            $article->title = $item->title;
            if (preg_match('#<link href="([^"]*)" rel="image_src" type="image/jpeg">#', $content, $matches)) {
                $article->image_link = $matches[1];
            }

            $article->time = $time;
            preg_match_all('#(<[^>]*>)+([^<]*)頭條(<[^>]*>)+([^<]*)#', $content, $matches);
            foreach ($matches[2] as $idx => $paper) {
                if (!array_key_exists($paper, $papers))  {
                    continue;
                }
                unset($papers[$paper]);
                $headlines[] = array($paper, htmlspecialchars_decode($matches[4][$idx]));
            }
            $article->headlines = $headlines;

            if (count($papers)) {
                throw new Exception("no 4 news");
            }
            return $article;
        }
        throw new Exception("not found");
    }

    public function getFromYahooPage($time)
    {
        $article_url = null;
        if (false) { // 從 google 搜尋
            $date = date('Ymd', $time);
            $title=  sprintf('今日（%s）重點新聞報你知', date('n/j', $time));
            $query = sprintf('"今日（%s）重點新聞報你知" site:tw.news.yahoo.com', date('n/j', $time));
            $cx = getenv('SEARCH_ID');
            $key = getenv('SEARCH_KEY');
            $url = 'https://www.googleapis.com/customsearch/v1?key=' . urlencode($key) .'&cx=' . urlencode($cx) . '&q=' . urlencode($query);
            $obj = json_decode(file_get_contents($url));
            foreach ($obj->items as $item) {
                if (false === strpos($item->title, $title)) {
                    continue;
                }
                if (false === strpos($item->htmlSnippet, '個月前') and false === strpos($item->htmlSnippet, date('Y', $time) . '年')) {
                    continue;
                }
                error_log($item->title);
                $article_url = $item->link;
            }
        } else {

            $date = date('Ymd', $time);
            $query = sprintf("今日（%d/%d）重點新聞報你知", date('m', $time), intval(date('d', $time)));
            error_log($query);

            $url = 'https://tw.news.yahoo.com/search?p=' . urlencode($query);
            $content = file_get_contents($url);
            $doc = new DOMDocument;
            @$doc->loadHTML($content);
            foreach ($doc->getElementsByTagName('a') as $a_dom) {
                if ($a_dom->nodeValue == $query) {
                    $article_url = 'https://tw.news.yahoo.com' . $a_dom->getAttribute('href');
                    error_log($article_url);
                }
            }
        }
        if ($article_url) {
            if (true) {
                $curl = curl_init($article_url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36');
                $content = curl_exec($curl);
                $doc2 = new DOMDocument;
                @$doc2->loadHTML($content);

                $article = new StdClass;
                $article->link = $article_url;
                $article->title = $query;
                $article->time = $time;
                $papers = array('聯合報', '中國時報', '蘋果日報', '自由時報');
                $papers = array_combine($papers, $papers);
                $headlines = array();

                foreach ($doc2->getElementsByTagName('strong') as $node) {
                    $text = $node->nodeValue;

                    if (strpos($text, '》')) {
                        preg_match_all('#>([^<》]*)》([^<]*)<#u', '>' . $text. '<', $matches);
                        foreach ($matches[1] as $idx => $paper) {
                            $title = $matches[2][$idx];
                            if (array_key_exists($paper, $papers)) {
                                unset($papers[$paper]);
                                $headlines[] = array($paper, $title);
                            }
                        }
                    }
                }
                foreach ($doc2->getElementsByTagName('p') as $p_dom) {
                    foreach ($p_dom->childNodes as $node) {
                        if ($node->nodeName != '#text') {
                            continue;
                        }
                        $text = $node->nodeValue;

                        if (strpos($text, '》')) {
                            preg_match_all('#>([^<》]*)》([^<]*)<#u', '>' . $text. '<', $matches);
                            foreach ($matches[1] as $idx => $paper) {
                                $title = $matches[2][$idx];
                                if (array_key_exists($paper, $papers)) {
                                    unset($papers[$paper]);
                                    $headlines[] = array($paper, $title);
                                }
                            }
                        }
                    }
                }

                $article->headlines = $headlines;
                if (count($papers) and !(count($papers) == 1 and array_keys($papers)[0] == '蘋果日報')) {
                    throw new Exception("no 4 news");
                }
                return $article;
            }
        }

        throw new Exception("not found");
    }

    public function getFromCNAPage($time)
    {
        error_log(date('Ymd', $time));
        $papers = array('聯合報', '中國時報', '蘋果日報', '自由時報');
        for ($news_id = 0; $news_id < 10; $news_id ++) {
            $url = sprintf("http://www.cna.com.tw/news/firstnews/%s5%03d.aspx", date('Ymd', $time), $news_id);
            $content = file_get_contents($url);
            $doc = new DOMDocument;
            $content = str_replace('<head>', '<head><meta charset="utf-8" />', $content);
            @$doc->loadHTML($content);

            $title = trim($doc->getElementsByTagName('title')->item(0)->nodeValue);
            error_log($title);
            if (!preg_match('#' . sprintf("%d月%d日\s*台灣各報頭條速報", date('m', $time), date('d', $time)) . '#', $title)) {
                continue;
            }

            $body = $doc->saveHTML($doc->getElementsByTagName('article')->item(0));
            $body = str_replace('&nbsp;', '', $body);
            preg_match_all('#>\s*([^<：》]*)[》：]([^<]*)<#u', $body, $matches);
            $papers = array('聯合報', '中國時報', '蘋果日報', '自由時報');
            $article = new StdClass;
            $article->link = $url;
            $article->title = $title;
            if ($img_dom = $doc->getElementsByTagName('article')->item(0)->getElementsByTagName('img')->item(0)) {
                $article->image_link = $img_dom->getAttribute('src');
            }
            $article->time = $time;

            $headlines = array();
            $papers = array_combine($papers, $papers);

            foreach ($matches[1] as $idx => $paper) {
                if ($paper == '蘋果時報') {
                    $paper = '蘋果日報';
                }
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
        // 從 Yahoo 新聞抓 30 天資料
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
                $article = $this->getFromYahooPage($time);
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
    }
}

$c = new Crawler;
$c->main();
