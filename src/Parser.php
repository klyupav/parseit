<?php
/**
 * Created by PhpStorm.
 * User: klyupav
 * Date: 19.03.17
 * Time: 13:46
 */

namespace ParseIt;


class Parser
{
    public $donorClasses = [];

    function __construct()
    {
        $pathToDonorsDir = __DIR__.'/../../../../donors';
        $files = scandir( $pathToDonorsDir );
        foreach ( $files as $file )
        {
            if( preg_match('%^(.*?)\.php$%is', $file, $match) )
            {
                $DonorClass = $match[1];
                $this->donorClasses[] = $DonorClass;
                if (!class_exists( $DonorClass ))
                {
                    require_once $pathToDonorsDir. DIRECTORY_SEPARATOR . "{$DonorClass}.php";
                }
            }
        }
    }

    public function parsingAllDonors()
    {
        $allData = [];
        foreach ($this->donorClasses as $donorClass)
        {
            ParserLogger::logToFile("Запуск сбора данных с донора {$donorClass}");
            $_{$donorClass} = new $donorClass();
            $_{$donorClass}->getSources();
            $data = [];
            foreach ( $_{$donorClass}->sources as $source )
            {
                $content = $_{$donorClass}->loadUrl($source['url'], $source);
                $data[] = $_{$donorClass}->onSourceLoaded($content, $source['url'], $source);
            }
            foreach ( $data as $d )
            {
                $allData[] = $d;
            }
            $countResult = count($data);
            ParserLogger::logToFile("{$donorClass} результаты: всего данных собрано  - {$countResult}");
        }

        return $allData;
    }

    public function parsingDonor($url, $donorClass)
    {
        ParserLogger::logToFile("Запуск сбора данных с донора {$donorClass}");
        $class = "Parseit\\".$donorClass;
        $_{$donorClass} = new $class();
        $_{$donorClass}->getSources($url);
        $data = [];
        foreach ( $_{$donorClass}->sources as $source )
        {
            $content = $_{$donorClass}->loadUrl($source['url'], $source);
            $data[] = $_{$donorClass}->onSourceLoaded($content, $source['url'], $source);
        }
        $countResult = count($data);
        ParserLogger::logToFile("{$donorClass} результаты: всего данных собрано  - {$countResult}");

        return $data;
    }
}