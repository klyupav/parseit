<?php
namespace ParseIt;

Class simpleParser
{

    public $project = '';
    public $cookie;
    public $sources = [];
    public $saveToCache = false;

    function __construct()
    {
        $this->cookieFile = 'cookie-'.get_class($this).'.txt';
        $this->project = str_replace('https://', '', trim('/', $this->project) );
        $this->project = str_replace('http://', '', trim('/', $this->project) );
    }

    public function loadUrl($url, $opts = [])
    {
        $html = ParseItSourceManager::load($url, $opts);
        return $html;
    }

    public function fixUrl($url)
    {
        $url = trim($url);
        if ( preg_match('%^//%', trim($url)) )
        {
            return 'http:'.$url;
        }
        $url = ltrim($url, "/");
        $url = str_replace("&amp;", "&", $url);
        if (!substr_count($url, "http://") || !substr_count($url, "https://"))
        {
            $url = 'http://' . $this->project . '/' . $url;
        }

        return $url;
    }
}