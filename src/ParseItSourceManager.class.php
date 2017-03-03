<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ParseItSourceManager
 *
 * @author NapolskyHome
 */

namespace ParseIt;

class ParseItSourceManager
{
    public $url;
    public $html;
    public static $cache_dir = 'urlcache/';
    public $cookie;

    function ParseItSourceManager(&$source)
    {
        $this->source = $source;
    }

    public function getXml()
    {
        if ($this->html) {
            $this->initWithSource();
        }

        if ($this->url && !$this->html) {
            $this->initWithUrl();
        }
    }

    private function initWithSource()
    {
        
    }

    private function initWithUrl()
    {
        if ($this->source->useUrlCache) {
            $this->loadUrlContentFromCache();
        } else {

            $this->loadUrlContent();
        }
    }

    private function loadUrlContentFromCache()
    {
        $hash = md5(Users::current()->absId . '_' . $this->url);

        if (file_exists('urlcache/' . $hash)) {
            // load from cache
            $this->setHtml(file_get_contents('urlcache/' . $hash));
        } else {
            $this->loadUrlContent();
        }
    }

    private function loadUrlContent()
    {

        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            return;
        }



//        $context = stream_context_create(array(
//            'http' => array(
//                'method' => 'GET',
//                'header' => implode("\r\n", array(
//                    'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3', // optional
//                    'Accept-Charset: utf-8, Windows-1251, ANSI, ISO-8859-1;q=0.7,*;q=0.7', // optional
//                    'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0',
//                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
//                    'Accept-Encoding: gzip;q=0, compress;q=0'
//                )),
//            )
//                ));

        /* if (!$html = file_get_contents($this->url, FILE_TEXT))
          {
          return;
          }
         */

        if (!$html = $this->loadUrlWithCurl()) {
            return;
        }

      // die(htmlspecialchars($html));


        $this->setHtml($html);
        $hash = md5(Users::current()->absId . '_' . $this->url);
        $this->cache = self::placeFilename($hash);
        file_put_contents($this->cache, $html);
    }

    public static function placeFilename($hash)
    {
        $dir = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . substr($hash, 4, 2) . '/';

        if (!is_dir(static::$cache_dir . $dir)) {
            mkdir(static::$cache_dir . $dir, 0777, true);
        }

        $filename = static::$cache_dir . $dir . $hash . '.htm';

        return $filename;
    }

    public function setHtml($html)
    {

        $this->html = ParseItHelpers::fixEncoding(trim($html));
    }

    private function loadUrlWithCurl()
    {
        return static::load($this->url, ['proxy' => $this->useProxy, 'cookie' => $this->cookie]);
    }

    public static function load($url, $opt = [], $popitka = 0)
    {
        // use contentCache 
        if ($opt['useContentCacheOnDate']) {
            $cache = static::getContentCache($url, $opt, $opt['useContentCacheOnDate']);
            if ($cache != false) {
                return $cache;
            }
        }
        $hostsByProxy = array('www.bn.ru');

        if ($opt['sleep']) {
            sleep($opt['sleep']);
        }
        if ($opt['PurgeURL']) {
            exec('curl -X PURGE '.$url);
        }

        $ch = curl_init();
        $timeout = 10;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($opt['returnHeader']) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        $defaultHeaders = [
            //'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            'Accept-Language:ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
            'Accept-Charset: utf-8, Windows-1251, ANSI, ISO-8859-1;q=0.7,*;q=0.7',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0',
            'Accept-Encoding: gzip;q=0, compress;q=0',
        ];
        $headersConfig = array_merge($defaultHeaders, (array) $opt['headers']);

        if ($opt['ajax']) {
            $headersConfig[] = 'X-Requested-With: XMLHttpRequest';
        }

        if ($opt['referer']) {
            $headersConfig[] = 'Referer: ' . $opt['referer'];
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headersConfig);

        // CURLOPT_FAILONERROR default to true
        curl_setopt($ch, CURLOPT_FAILONERROR, $opt['failOnError'] === false ? false : true);

        if (empty($opt['no-follow'])) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		//curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        //curl_setopt($ch, CURLOPT_AUTOREFERER, true);

        $timeout = $opt['timeout'] ?: 30;
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

        if (!empty($opt['cookieFile'])) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $opt['cookieFile']);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $opt['cookieFile']);
        }

        if (is_array($opt['cookie'])) {
            foreach ($opt['cookie'] as $n => $v) {
                $cookie .= $n . '=' . $v . '; ';
            }
        } else {
            $cookie = $opt['cookie'];
        }

        //curl_setopt($ch, CURLOPT_COOKIE, $cookie);

        // basic authentication
        if ($opt['auth']) {
            curl_setopt($ch, CURLOPT_USERPWD, $opt['auth']);  
        }

        // post data
        if (is_array($opt['post'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['post']);
        }

        // post data
        if (is_string($opt['post'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['post']);
        }
/*
        if (in_array(parse_url($url, PHP_URL_HOST), $hostsByProxy) || $opt['proxy']) {
            if ($proxy = ProxyList::getLiveProxy()) {
                curl_setopt($ch, CURLOPT_PROXY, $proxy['ip']);
                curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
            }
        }
*/
        if ($opt['proxy']) {
            $proxies = [
                '178.57.68.212:8085',
                '5.62.155.250:8085',
                '185.101.71.222:8085',
                '79.110.31.126:8085',
                '188.68.1.219:8085',
            ];
            $proxy = $proxies[array_rand($proxies)];
            //curl_setopt($ch, CURLOPT_PROXYUSERPWD, 'MiniRUS218729:kYoj8PloCM');
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
		curl_setopt($ch, CURLOPT_MAXREDIRS, 15);

        $data = curl_exec($ch);
        //print_r($data);die();
		
		$status = curl_getinfo($ch);
        //print_r($status);
		if($status['http_code']!=200){
			if($status['http_code'] == 301 || $status['http_code'] == 302) {
				//print_r($status);die();
				if (isset($status['redirect_url'])) {
					$data = ParseItSourceManager::load($status['redirect_url'], $opt);
				}
			}
		}
/*
        $retries = $opt['retries'] ?: 3;
        $retryDelay = $opt['retryDelay'] ?: 1;
        while (!in_array(curl_errno($ch), [CURLE_OK, CURLE_HTTP_RETURNED_ERROR]) && $retries--) {
            printf("Retry url '%s'\n", $url);
            sleep($retryDelay);

            if (in_array(parse_url($url, PHP_URL_HOST), $hostsByProxy) || $opt['proxy']) {
                if ($proxy = ProxyList::getLiveProxy()) {
                    curl_setopt($ch, CURLOPT_PROXY, $proxy['ip']);
                    curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
                }
            }

            $data = curl_exec($ch);
        }
*/
        curl_close($ch);

        // store ContentCache

        if ($opt['saveContentCache']) {
            static::saveContentCache($data, $url, $opt);
        }

        if ($opt['json']) {
            $data = json_decode($data);
        }

        if ( $opt['attempts'] > 0 ) {
            if(!$data) {
                if( $popitka === 10 ) {
                    return $data;
                } else {
                    $data = ParseItSourceManager::load($url, $opt, $popitka+1);
                }
            }
        }

        return $data;
    }

    public static function getCookiesFromHeader($header)
    {
        // get cookie
        $cookies = [];
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $m);
        
        foreach ($m[1] as $cookieStr)
        {
            parse_str($cookieStr, $cookiesTmp);
            $cookies = array_merge($cookies, $cookiesTmp);
        }

        return $cookies;
    }

    public static function saveContentCache($data, $url, $opt)
    {
        $hash = static::generateContentCacheHash($url, $opt);
        $data = @gzdeflate($data);
        ContentCache::saveCache($hash, $data, $url);
    }

    public static function generateContentCacheHash($url, $opt)
    {
        $keysForHash = [
            'returnHeader',
            'ajax',
            'referer',
            'cookie',
            'post',
        ];
        $hashArr = [$url];

        foreach ($keysForHash as $k) {
            (isset($opt[$k]) && $opt[$k] !== '') ? $hashArr[] = $opt[$k] : null;
        }

        $hash = md5(join('#', $hashArr));
        return $hash;
    }

    public static function getContentCache($url, $opt, $date)
    {
        $hash = static::generateContentCacheHash($url, $opt);
        return @gzinflate( ContentCache::getCache($hash, ['date' => $date]) );
    }
}
