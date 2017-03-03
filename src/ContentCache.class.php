<?php
/**
 * Description of ContentCache
 *
 * @author Anton
 */
namespace ParseIt;

class ContentCache
{
    // put content to cache
    public static function saveCache($hash, $content, $url)
    {
		file_put_contents("cache/$hash", $content);
    }

    // get content from cache
    public static function getCache($hash, $opt = [])
    {
		$filename = "cache/$hash";
		if( file_exists($filename) ) {
            return file_get_contents($filename);
        }
        
        return false;
    }
}
