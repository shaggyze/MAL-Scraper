<?php

namespace MalScraper\Model;

define('MAX_FILE_SIZE', 100000000);

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
        $file_headers = @get_headers($url);
        if (empty($file_headers) || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
            return 404;
        }

        if (empty($file_headers) || $file_headers[0] == 'HTTP/1.1 403 Forbidden') {
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
		try {
			$html = HtmlDomParser::file_get_html($url);
			if (!$html) {
				throw new Exception("Failed to fetch HTML content from URL: $url");
			}

			$html = $html->find($contentDiv, 0);
			if (!$html) {
				throw new Exception("Content div not found: $contentDiv");
			}

			$html = !$additionalSetting ? $html : $html->next_sibling();
			if (!$html) {
				throw new Exception("Next sibling not found");
			}

			// The rest of your code remains unchanged
			$html = $html->outertext;
			$html = str_replace('&quot;', '\"', $html);
			$html = str_replace('&lt;', '&l-t;', $html); // handle '<'
			$html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
			$html = str_replace('&l-t;', '&lt;', $html);
			$html = HtmlDomParser::str_get_html($html);

			return $html;   

		} catch (Exception $e) {
			// Handle the exception, e.g., log it, display an error message, or retry the request
			if ($e->getCode() === 405) {
				// Handle 405 error specifically
				echo "405 Method Not Allowed";
			} else {
				// Handle other exceptions
				echo "An error occurred: " . $e->getMessage();
			}
		}
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
