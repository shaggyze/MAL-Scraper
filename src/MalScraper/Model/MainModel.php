<?php

namespace MalScraper\Model;
ini_set("log_errors", TRUE);
ini_set("error_log", "error_log");
define('MAX_FILE_SIZE', 1000000000);

use HtmlDomParser;

/**
 * MainModel class.
 *
 * Base model for all model class.
 */
class MainModel
{
    /**
     * MyAnimeList main URL.
     *
     * @var string
     */
    protected $_myAnimeListUrl = 'https://myanimelist.net';

    /**
     * Trimmed HtmlDomParser.
     *
     * @var \simplehtmldom_1_5\simple_html_dom
     */
    protected $_parser;

    /**
     * Area to be parsed.
     *
     * @var string
     */
    protected $_parserArea;

    /**
     * Complete MyAnimeList page URL.
     *
     * @var string
     */
    protected $_url;

    /**
     * Error response.
     *
     * @var string|int
     */
    protected $_error;

    /**
     * Get URL header.
     *
     * @param string $url URL of full MyAnimeList page
     *
     * @return int
     */
    public static function getHeader($url)
    {
        $file_headers = @get_headers($url) ?: [];
		$html = HtmlDomParser::file_get_html($url);
		$title = $html ? $html->find('title', 0)->plaintext : '';
		error_log('header: ' . $file_headers[0] . ' title: ' . $title);
        if ((isset($file_headers[0]) && $file_headers[0] === 'HTTP/1.1 404 Not Found') || $title == '404 Not Found - MyAnimeList.net') {
            return 404;
        }

        if ((isset($file_headers[0]) && $file_headers[0] === 'HTTP/1.1 503 Service Unavailable') || $title == 'Service Unavailable') {
            return 503;
        }

        if ((isset($file_headers[0]) && $file_headers[0] === 'HTTP/1.1 504 Gateway Time-out') || $title == '504 Gateway Time-out') {
            return 504;
        }

        if ((isset($file_headers[0]) && $file_headers[0] === 'HTTP/1.1 403 Forbidden') || $title == '403 Forbidden' || !$html || empty($file_headers)) {
            return 403;
        }

        return 200;
    }

    /**
     * Get trimmed HtmlDomParser class.
     *
     * @param string $url        URL of full MyAnimeList page
     * @param string $contentDiv Specific area to be parsed
     *
     * @return \simplehtmldom_1_5\simple_html_dom
     */
    public static function getParser($url, $contentDiv, $additionalSetting = false)
    {
        $html = HtmlDomParser::file_get_html($url)->find($contentDiv, 0);
        $html = !$additionalSetting ? $html : $html->next_sibling();
        if (!empty($html) && isset($html->outertext)) {
			$html = $html->outertext;
			$html = str_replace('&quot;', '\"', $html);
			$html = str_replace('&lt;', '&l-t;', $html); // handle '<'
			$html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
			$html = str_replace('&l-t;', '&lt;', $html);
			$html = HtmlDomParser::str_get_html($html);
		}
        return $html;
    }

    /**
     * Header error check.
     *
     * @param MainModel $model Any model
     *
     * @return void
     */
    public static function errorCheck($model)
    {
        $className = self::getCleanClassName($model);

        if (strpos($className, 'Search') !== false) {
            if (strlen($model->_query) < 3) {
                $model->_error = 400;
            }
        }

        if (!$model->_error) {
            $header = self::getHeader($model->_url);
            if ($header == 200) {
                if ($className != 'UserListModel') {
                    $additionalSetting = ($className == 'CharacterPeoplePictureModel');
                    $model->_parser = self::getParser($model->_url, $model->_parserArea, $additionalSetting);
                }
            } else {
                $model->_error = $header;
            }
        }
    }

    /**
     * Get clean class name.
     *
     * @param MainModel $model Any model
     *
     * @return string
     */
    public static function getCleanClassName($model)
    {
        $className = get_class($model);
        $className = explode('\\', $className);

        return $className[count($className) - 1];
    }
}
