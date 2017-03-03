<?php

namespace ParseIt;

class ParseItHelpers
{

    public static function getLabelsFromTable($node, $cls)
    {
        $ret = array();
        $t = $cls->getParser()->get('th', $node);
        if ($t->_dom instanceof DOMNodeList) {
            foreach ($t->_dom as $node) {
                $ret[] = $node->textContent;
            }
        }

        return $ret;
    }

    // строит след дочернего элемента относительно его прапредка.
    public static function buildPathStampForElement($node, $rootNode, $opt = [])
    {
        $hashParts = [];
        $tmp = $node;

        while (($tmp !== $rootNode) && ($b < 50) && (ParseItHelpers::isChildNode($rootNode, $tmp))) {
            $b++;
            $hashPart = $tmp->tagName;

            if ( $tmp->tagName === 'td' )
            {
                // хорошо бы еще добавить $rootNode->parent === 'корневой бокс итемов'  для нормальной работы вложенных элементов
                $hashPart .= ParseItHelpers::getTdColumn($tmp);
            }
            else
            {
                //$hashPart .= ParseItHelpers::getSiblingLevel($tmp); // 29.12.2013 - не добвавляем уровень вложенности так как переходим на определение с классами
            }

            if (method_exists($tmp, 'getAttribute') && ($class = $tmp->getAttribute('class')) && !$opt['skipClasses'])
            {
                if (!in_array($class, (array) $opt['badClassesIdentificators']))
                {
                    $hashPart .= ':' . $class;
                    $hashParts[] = $hashPart;
                    break;
                }
            }

            $hashParts[] = $hashPart;
            $tmp = $tmp->parentNode;
        }


        $hash = join('.', array_reverse($hashParts));


        /* if (($node->tagName !== 'html') && $node->parentNode->getAttribute('class') && !$opt['skipClasses']) {
          $hash .= ':' . preg_replace('#[0-9]+\b#', '', $node->parentNode->getAttribute('class'));  // удаляем числа на конце классов, чтобы обрабатывать правильно случаи типа class="title item-1", class="title item-2"
          } */

        return $hash;
    }

    public static function getSiblingLevel($el)
    {
        $k = 0;
        foreach ($el->parentNode->childNodes as $childNode)
        {
            if ($childNode === $el)
            {
                return $k;
            }
            $k++;
        }
    }

    public static function getTdColumn($td)
    {
        $k = 0;
        foreach ($td->parentNode->childNodes as $childNode)
        {
            $d = 1;
            if (method_exists($childNode, 'getAttribute') && $childNode->getAttribute('colspan'))
            {
                $d = (int) $childNode->getAttribute('colspan');
            }
            if ($childNode === $td)
            {
                return $k;
            }
            $k+= $d;
        }
    }

    public static function getHtmlForNode($node)
    {
        $ret = '<' . $node->tagName;
        if ($node && $node->hasAttributes())
        {
            foreach ($node->attributes as $attr)
            {
                $name = $attr->nodeName;
                $value = $attr->nodeValue;
                $ret .= ' ' . $name . '="' . $value . '"';
            }
        }

        $ret .= '>';

        return $ret;
    }

    public static function isSimilarTagStamps($stamp1, $stamp2, $maxDifferency)
    {
        static $cache = array();  //  функция работает медленно, результаты надо кешировать
        $cacheKey = $stamp1 . '::' . $stamp2 . '::' . (string) $maxDifferency;

        if (isset($cache[$cacheKey]))
        {
            return $cache[$cacheKey];
        }

        $minLen = min(strlen($stamp1), strlen($stamp2));
        // сравниваем как сначала..
        if (levenshtein(substr($stamp1, 0, 254), substr($stamp2, 0, 254)) > (int) (min($minLen, 255) * $maxDifferency))
        {
            $cache[$cacheKey] = false;
            return false;
        }

        // ..так и с конца (пАтАму что ливенштейн не поддерживает строки более 255 символов в длинну)
        if (levenshtein(substr($stamp1, -254), substr($stamp2, -254)) > (int) (min($minLen, 255) * $maxDifferency))
        {
            $cache[$cacheKey] = false;
            return false;
        }

        $cache[$cacheKey] = true;
        return true;
    }

    public static function getTagStampSimilarity($stamp1, $stamp2)
    {
        $maxLen = max(strlen($stamp1), strlen($stamp2));
        $l1 = levenshtein(substr($stamp1, 0, 254), substr($stamp2, 0, 254));
        $l2 = levenshtein(substr($stamp1, -254), substr($stamp2, -254));

        $l = ($l1 + $l2) / 2;
        return 1 - ($l / max($maxLen, 255));
    }

    // должна определить, обернут ли html в заголовки, и если нет, то обернуть
    public static function fixHeader($html)
    {
        if (mb_strpos(mb_strtolower($html), '<head') === false)
        {
            $html = '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=UTF-8" /></head><body>' . $html . '</body></html>';
        }

        return $html;
    }

    public static function fixEncoding($html)
    {
        $newhtml = $html;

        // set default - utf-8
        // в str_replace счетчик 1, так как не забываем про тег <header>  в <body>
        if (!preg_match('#\<meta[^\>]+charset#is', ParseItHelpers::getHeadSection($html)))
        {
            $newhtml = preg_replace('#\<head#is', '<head><meta http-equiv=content-type content="text/html; charset=UTF-8">', $html, 1);
        }

        if (preg_match('#\<meta[^\>]+charset\=[^\>]+utf-8#is', ParseItHelpers::getHeadSection($html)))
        {
            $newhtml = preg_replace('#\<head#is', '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8" />', $html, 1); // не <head>, т к  <head id="something" >
            $newhtml = iconv("UTF-8","UTF-8//IGNORE", $newhtml);
        }

        if (preg_match('#\<meta[^\>]+charset\=[^\>]+windows-1251#is', ParseItHelpers::getHeadSection($html)))
        {
            //$newhtml = str_replace('<head', '<head><meta http-equiv="content-type" content="text/html; charset=windows-1251" />', $html);
            $newhtml = preg_replace('#\<head#is', '<head><meta http-equiv="content-type" content="text/html; charset=windows-1251" />', $html, 1);
        }

        //die(htmlspecialchars($newhtml));
        return $newhtml;
    }

    public static function getHeadSection($html)
    {
        $startPos = mb_strpos(mb_strtolower($html), '<head');
        $endPos = mb_strpos(mb_strtolower($html), '</head>');

        //echo $startPos.'-'.$endPos; die();
        if (!$startPos || !$endPos)
        {
            return '';
        }

        return mb_substr($html, $startPos, $endPos - $startPos + 7);
    }

    public static function clearNodeFromEmptyText($node)
    {
        // @fixIt!! very stupid, but works fine
        for ($i = 0; $i < 100; $i++)
        {
            foreach ($node->childNodes as $childNode)
            {
                if ($childNode->nodeName === '#text' && !strlen(trim($childNode->wholeText)))
                {
                    $node->removeChild($childNode);
                }
            }
        }

        return $node;
    }

    static function cleanNodeFromAttributes($node)
    {
        return $node;
    }

    /*
     * (DOMNode)<div><a href="#">link</a><span>text</span><br/></div>     ====>     (string)'div.a.span.br'
     */

    public static function buildTagStampForNode2($node)
    {

        $cleanNode = self::cleanNodeFromAttributes($node);  //! not working

        $newdoc = new DOMDocument();

        $cloned = $cleanNode->cloneNode(TRUE);
        if (!$cloned)
        {
            return;
        }
        $newdoc->appendChild($newdoc->importNode($cloned, TRUE));
        $html = $newdoc->saveXML();
        preg_match_all('#<([a-zA-Z0-9]+)#i', $html, $matches);

        $tagsArr = $matches[1];
        $tagsToRemove = array('script', 'style', 'head', 'link', 'meta', 'sup', 'sub', 'br');
        $tagsArr = array_diff($tagsArr, $tagsToRemove);

        $tagStamp = join('|', $tagsArr);
        $htmlTagsOptimizer = array(
            '.div,', '.table,', '.td,', '.tr,', '.th,', '.a,', '.span,', '.b,', '.i,', '.noindex,', '.img,', '.h1,', '.h2,', '.h3,', '.h4,', '.h5,'
        );
        $tagStamp = trim(str_replace($htmlTagsOptimizer, array_keys($htmlTagsOptimizer), $tagStamp));

        return $tagStamp;
    }

    public static function buildTagStampForNode($node)
    {
        static $cache = array();  //  функция работает медленно, результаты надо кешировать
        $cacheKey = $node->getNodePath(); // ? а уникальный ли он

        if (isset($cache[$cacheKey]))
        {
            return $cache[$cacheKey];
        }

        $cleanNode = self::cleanNodeFromAttributes($node);  //! not working

        $newdoc = new DOMDocument();
        $cloned = $cleanNode->cloneNode(TRUE);
        if (!$cloned)
        {
            return;
        }
        $newdoc->appendChild($newdoc->importNode($cloned, TRUE));

        foreach ($newdoc->getElementsByTagName('*') as $someNode)
        {
            if ($g++ > 100)
            {
                // максимальная глубина обхода
                break;
            }
            #remove text contents
            /* foreach ($someNode->childNodes as $childNode)
              {
              if (in_array($childNode->nodeName, array('#text', '#comment', 'script', 'style', '#cdata-section', 'head', 'link', 'meta', 'sup','sub')))
              {
              //$someNode->removeChild($childNode);
              continue;
              }
              $tagStamp .= $childNode->nodeName . '|';
              //echo $childNode->nodeName.' ====  ';
              } */

            if (in_array($someNode->nodeName, array('#text', '#comment', 'script', 'style', '#cdata-section', 'head', 'link', 'meta', 'sup', 'sub', 'option')))
            {
                //$someNode->removeChild($childNode);
                continue;
            }
            $tagStamp .= $someNode->nodeName . '|';
            //echo $childNode->nodeName.' ====  ';
        }

        $tagStamp = trim($tagStamp, '|');

        //echo $tagStamp.'%';
        #$tagStamp = $newdoc->saveHTML();
        #$tagStamp = $this->stripArgumentFromTags($tagStamp);
        #$tagStamp = trim(str_replace('>', '>|', $tagStamp)); // add delimiter
        #$tagStamp = preg_replace('#\<\/[a-zA-Z]+\>#', '', $tagStamp); // удаляем закрывающиеся теги (опция, теряется сильное соответствие)
        #$tagStamp = trim(str_replace(array("\n", "\r","\t", ' ', '/'), '', $tagStamp));
        #$tagStamp = trim(str_replace(array("<", ">"), array('.', ','), $tagStamp));


        $htmlTagsOptimizer = array(
            '.div,', '.table,', '.td,', '.tr,', '.th,', '.a,', '.span,', '.b,', '.i,', '.noindex,', '.img,', '.h1,', '.h2,', '.h3,', '.h4,', '.h5,'
        );
        $tagStamp = trim(str_replace($htmlTagsOptimizer, array_keys($htmlTagsOptimizer), $tagStamp));

        //if(strpos($tagStamp, '.html,|.body,') !== false){std::trace(htmlspecialchars($newdoc->saveHTML())); die();}
        $cache[$cacheKey] = $tagStamp;
        return $tagStamp;
    }

    function getSourceLinkRoot()
    {
        if ($this->sourceManager->url)
        {
            return parse_url($this->sourceManager->url, PHP_URL_SCHEME) . '://' . parse_url($this->sourceManager->url, PHP_URL_HOST) . '/';
        }
        else
        {
            return 'document://';
        }
    }

    function getBaseUrl()
    {
        return $this->sourceManager->url;
    }

    function stripArgumentFromTags($htmlString)
    {
        $regEx = '/([^<]*<\s*[a-z](?:[0-9]|[a-z]{0,9}))(?:(?:\s*[a-z\-]{2,14}\s*=\s*(?:"[^"]*"|\'[^\']*\'))*)(\s*\/?>[^<]*)/i'; // match any start tag

        $chunks = preg_split($regEx, $htmlString, -1, PREG_SPLIT_DELIM_CAPTURE);
        $chunkCount = count($chunks);

        $strippedString = '';
        for ($n = 1; $n < $chunkCount; $n++)
        {
            $strippedString .= $chunks[$n];
        }

        return $strippedString;
    }

    public static function isChildNode(&$node, $nodesToCheckArr)
    {
        //return ;
        $nodePath = $node->getNodePath();
        //$nodePath = rand(1,10000);
        static $cache = array();


        if (!is_array($nodesToCheckArr))
        {
            $nodesToCheckArr = array($nodesToCheckArr);
        }

        foreach ($nodesToCheckArr as $nodeToCheck)
        {
            $nodePathToCheck = $nodeToCheck->getNodePath();
            $cacheKey = $nodePathToCheck . ':' . $nodePath;


            if (isset($cache[$cacheKey]))
            {
                return $cache[$cacheKey];
            }
            $cache[$cacheKey] = false; // def value

            if (strpos($nodePathToCheck, $nodePath) !== false)
            {
                $cache[$cacheKey] = true;
                return true;
            }
        }

        return false;
    }

    public static function isParentNode($node, $nodesToCheckArr)
    {
        $nodePath = $node->getNodePath();
        static $cache = array();


        if (!is_array($nodesToCheckArr))
        {
            $nodesToCheckArr = array($nodesToCheckArr);
        }

        foreach ($nodesToCheckArr as $nodeToCheck)
        {
            $nodePathToCheck = $nodeToCheck->getNodePath();
            $cacheKey = $nodePathToCheck . ':' . $nodePath;
            $cache[$cacheKey] = false; // def value

            if (isset($cache[$cacheKey]))
            {
                return $cache[$cacheKey];
            }

            if (strpos($nodePath, $nodePathToCheck) !== false)
            {
                // неверно! в середине строки иначе тоже зачекает
                $cache[$cacheKey] = true;
                return true;
            }
        }

        return false;
    }

    public static function isChildNodeByXpath($childXpath, $parentXpath) {
        if ($childXpath === $parentXpath)
        {
            return false;
        }
        if (strpos($childXpath, $parentXpath) !== false)
        {
            return true;
        }
    }

    public static function isParentNodeByXpath($childXpath, $parentXpath)
    {
        if ($childXpath === $parentXpath)
        {
            return false;
        }
        if (strpos($parentXpath, $childXpath) !== false)
        {
            return true;
        }
    }

    // работает только для элементов предок->потомок!!
    public static function getDistanceBetweenXPaths($xpath1, $xpath2)
    {
        if (strlen($xpath1) > strlen($xpath2))
        {
            $diff = substr($xpath1, strlen($xpath2));
        }
        else
        {
            $diff = substr($xpath2, strlen($xpath1));
        }

        return count(explode('/', $diff));
    }

    public static function getXpathSimilarity($xpath1, $xpath2)
    {
        if ($xpath1 === $xpath2)
        {
            // /html/body/div[1]/div/table
            // /html/body/div[1]/div/table
            return 1;
        }


        $diff = _String::simpleDiff($xpath1, $xpath2);
        if (is_numeric($diff))
        {
            ///html/body/div[1]/div/table
            // /html/body/div[2]/div/table
            return 0.99;
        }

        // !!very!! stupid
        // @todo: make it smarter
        $deep1 = substr_count($xpath1, '/');
        $deep2 = substr_count($xpath2, '/');
        return min($deep1, $deep2) / max($deep1, $deep2);
    }

    public static function isOneLevel($nodes)
    {
        if (!is_array($nodes))
        {
            return;
        }

        $f = reset($nodes);
        $standartParentNode = $f->parentNode;

        foreach ($nodes as $node)
        {
            if ($node->parentNode !== $standartParentNode)
            {
                return false;
            }
        }

        return true;
    }

    public static function nodeListToArray($domnodelist)
    {
        $return = array();
        for ($i = 0; $i < $domnodelist->length; ++$i)
        {
            $return[] = $domnodelist->item($i);
        }
        return $return;
    }

    public static function fixEncodingForBookmarkMode($html)
    {
        // задача - указать парсеру что контент в utf-8 весь (так как JS передает в utf-8 независимо от оригинала);
        /*
         *  тесты:
         * http://www.kant.ru/tovar.php?t=out_camps&id_razdel=31&m2=1&m3=1&year=2013
         * http://www.rdstroy.ru/catalog/9476/
         */
        $encodingsToFix = array('windows-1251', 'koi8-r');


        /* foreach ($encodingsToFix as $encoding)
          {
          if (preg_match('#\<meta[^\>]+windows\-1251#is', ParseItHelpers::getHeadSection($html)))
          {
          $html = preg_replace('/windows\-1251/uis', 'utf-8', $html, 1);
          //$html = mb_convert_encoding($html, 'windows-1251', 'utf-8');
          }
          } */

        $html = preg_replace('#<head#is', '<head><meta http-equiv=content-type content="text/html; charset=UTF-8"><meta ', $html, 1);
        $html = preg_replace('#</head>#is', '<meta http-equiv=content-type content="text/html; charset=UTF-8"></head>', $html, 1);
        $html = '<!Doctype html>' . $html;
        //echo $html; die();

        return $html;
    }

    public static function formatParsedDataForApi($rows)
    {
        foreach ($rows as &$row)
        {
            foreach ($row as &$el)
            {
                unset($el['attributeStamp']);
                unset($el['name']);
                unset($el['tagName']);
            }
        }

        return $rows;
    }

    public static function isNodeWithTextContent(&$node)
    {
        $minWords = 5;
        $minLength = 25;

        if ($node->nodeType !== 1)
        {
            return;
        }

        foreach ($node->getElementsByTagName('*') as $someNode)
        {
            //echo $someNode->nodeName.'<br/>';
            if (mb_strlen(_String::trim($someNode->textContent)) < $minLength)
            {
                continue;
            }

            foreach ($someNode->childNodes as $childNode)
            {
                if ($childNode->nodeName !== '#text')
                {
                    continue;
                }
                else
                {
                    if (ParseItAttributeTypesRecognizator::isText($childNode->wholeText) < 0.5)
                    {
                        continue;
                    }
                    return true;
                }
            }
        }
    }

    public static function tableToMatrix($node, $opts = [])
    {
        $matrix = [];
        foreach ($node->getElementsByTagName('tr') as $rowId => $tr)
        {
            foreach ($tr->getElementsByTagName('td') as $colId => $td)
            {
                if ($opts['extended'])
                {
                    $matrix[$rowId][$colId]['value'] = $td->textContent;
                    $matrix[$rowId][$colId]['__ref'] = $td;
                }
                else
                {
                    $matrix[$rowId][$colId] = $td->textContent;
                }
            }
            $numColsInRow = (string) count($matrix[$rowId]);
            $numColsIndex[$numColsInRow]++;   // detect most popular row scheme
        }
        arsort($numColsIndex);
        $mostPopularColsNumberInRow = (int) array_keys($numColsIndex)[0];

        if ($opts['detectNames'])
        {
            $headingRowIndex = 0;
            if($opts['headingRowIndex'])
            {
                $headingRowIndex = $opts['headingRowIndex'];
            }
            $tr = $node->getElementsByTagName('tr')->item($headingRowIndex);

            foreach ($tr->getElementsByTagName('th') as $colId => $th)
            {
                $headings[$colId] = _String::trim($th->textContent);
            }


            if (count($headings) === $mostPopularColsNumberInRow)
            {
                foreach ($matrix as $rowId => $columns)
                {
                    foreach ($columns as $colId => $v)
                    {
                        $newMatrix[$rowId][$headings[$colId]] = $v;
                    }
                }
            }
            $matrix = $newMatrix;
        }

        return $matrix;
    }

    public static function clearDomFromNonContentTags(&$dom)
    {
        $nonContentTags = ['style', 'script'];
        foreach ($dom->getElementsByTagName('*') as $someNode)
        {
            //echo $someNode->tagName.'<br/>';
            if (in_array($someNode->tagName, $nonContentTags))
            {
                $someNode->parentNode->removeChild($someNode);
            }
            $someNode->removeAttribute('style');
        }
    }

    public static function isLinked($node)
    {
        $tmp = $node;
        while ($tmp)
        {
            if ($tmp->tagName == 'a')
            {
                return true;
            }
            $tmp = $tmp->parentNode;
        }
    }

    public static function getXpathForNode(&$node)
    {
        $xpath = $node->getNodePath();
        $tmpNode = $node;
        do
        {
            if (method_exists($tmpNode, 'getAttribute') && ($id = $tmpNode->getAttribute('id')))
            {
                $relativeXpath = $tmpNode->getNodePath();
                //echo $relativeXpath.'<br/>'.$xpath; die();
                $xpath = str_replace($relativeXpath, '//*[@id="' . $id . '"]', $xpath);
                //die($xpath);
                return $xpath;
            }
        }
        while ($tmpNode = $tmpNode->parentNode);

        return $xpath;
    }

    public static function getClientXpathForNode(&$node)
    {
        $xpath = ParseItHelpers::getXpathForNode($node);

        //fix tbody
        $xpath = str_replace(['table/tbody', 'table', '_GG_'], ['_GG_', 'table/tbody', 'table/tbody'], $xpath);
        return $xpath;
    }

    public static function getInnerHtml($node)
    {
        $innerHTML = '';
        $children = $node->childNodes;
        foreach ($children as $child)
        {
            $innerHTML .= $child->ownerDocument->saveXML($child);
        }

        return $innerHTML;
    }

    public static function repairJson($string)
    {
        $result = $string;
        $result = preg_replace("/'([^']*)'/", '"$1"', $result);
        $result = preg_replace('/(\{|\,)\s*(\w+)\s*:/', '$1 "$2":', $result);
        $result = preg_replace('/\}\,\]/', '}]', $result);
        return $result;
    }
}