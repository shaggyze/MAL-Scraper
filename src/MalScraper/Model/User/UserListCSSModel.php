<?php

namespace MalScraper\Model\User;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * UserListModel class.
 */
class UserListCSSModel extends MainModel
{
    /**
     * Username.
     *
     * @var string
     */
    private $_user;

    /**
     * Either anime or manga.
     *
     * @var string
     */
    private $_type;

    /**
     * Anime/manga status.
     *
     * @var string
     */
    private $_status;

    /**
     * Anime/manga genre.
     *
     * @var string
     */
    private $_genre;
    /**
     * Default constructor.
     *
     * @param string $user
     * @param string $type
     * @param string $status
     * @param string $genre
     * @param string $parserArea
     *
     * @return void
     */
    public function __construct($user, $type, $status, $genre, $parserArea = '#content')
    {
        $this->_user = $user;
        $this->_type = $type;
        $this->_status = $status;
		$this->_genre = $genre;
        $this->_url = $this->_myAnimeListUrl.'/'.$type.'list/'.$user.'?status='.$status;
        $this->_parserArea = $parserArea;

        parent::errorCheck($this);
    }

    /**
     * Default call.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return array|string|int
     */
    public function __call($method, $arguments)
    {
        if ($this->_error) {
            return $this->_error;
        }

        return call_user_func_array([$this, $method], $arguments);
    }

    /**
     * Get user list.
     *
     * @return array
     */
    public function getAllInfo()
    {
      $data = [];
      $offset = 0;
	  $te_all = 0;
	  $te_cwr = 0;
	  $te_c = 0;
	  $te_oh = 0;
	  $te_d = 0;
	  $te_ptwr = 0;

	  while (true) {
        $primary_url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre;

        $content_json = false;
        $http_status = null;
        $use_alternate_url = false;

        $context = stream_context_create([
            'http' => [ 'ignore_errors' => true ]
        ]);

        $content_json = @file_get_contents(htmlspecialchars_decode($primary_url), false, $context);

        if (isset($http_response_header) && count($http_response_header) > 0) {
            preg_match('{HTTP\/\S+\s(\d{3})}', $http_response_header[0], $match);
            if (isset($match[1])) {
                $http_status = (int)$match[1];
            }
        }

        if ($content_json === false || ($http_status === 405)) {
            $use_alternate_url = true;
            echo "DEBUG: Primary URL failed. Attempting alternate URL.\n";
        }

        if ($use_alternate_url) {
            $alternate_url = 'https://shaggyze.website/maldb/userlist/'.$this->_user.'_'.$this->_type.'_'.$this->_status.'_'.$this->_genre.'.json';
            echo "DEBUG: Using alternate URL: " . $alternate_url . "\n";
            $content_json = @file_get_contents(htmlspecialchars_decode($alternate_url));
        }

        $content = null;
        if ($content_json) {
            $content = json_decode($content_json, true);
        }

        // FIX #1: If the alternate URL was used, get the actual data from the 'data' wrapper.
        if ($use_alternate_url && isset($content['data']) && is_array($content['data'])) {
            $content = array_values($content['data']);
        }
		
		if ($content) {
		  $count = count($content);
		  for ($i = 0; $i < $count; $i++) {
			// --- ALL OF YOUR ORIGINAL PROCESSING LOGIC REMAINS UNCHANGED ---
			if (!empty($content[$i]['anime_id'])) {
			  $subdirectory = get_subdirectory('info', 'anime', $content[$i]['anime_id']);
			  $url1 = 'https://shaggyze.website/msa/info?t=anime&id=' . $content[$i]['anime_id'];
			  $url2 = 'https://shaggyze.website/maldb/info/anime/' . $subdirectory . '/' . $content[$i]['anime_id'] . '.json';
			  if (!filter_var($url2, FILTER_VALIDATE_URL) || !file_get_contents($url2)) {$url2 = $url1;}
			  $content2 = json_decode(file_get_contents(htmlspecialchars_decode($url2)), true);
			  if ($content[$i]['anime_title_eng'] == "") {$content[$i]['anime_title_eng'] = "N/A";}
			} else {
			  $subdirectory = get_subdirectory('info', 'manga', $content[$i]['manga_id']);
			  $url1 = 'https://shaggyze.website/msa/info?t=manga&id=' . $content[$i]['manga_id'];
			  $url2 = 'https://shaggyze.website/maldb/info/manga/' . $subdirectory . '/' . $content[$i]['manga_id'] . '.json';
			  if (!filter_var($url2, FILTER_VALIDATE_URL) || !file_get_contents($url2)) {$url2 = $url1;}
			  $content2 = json_decode(file_get_contents(htmlspecialchars_decode($url2)), true);
			  if ($content[$i]['manga_english'] == "") {$content[$i]['manga_english'] = "N/A";}
			}
			// ... all of your other logic here ...
			if ($content[$i]['status'] == 1) { $te_cwr++; $te_all++; } 
            elseif ($content[$i]['status'] == 2) { $te_c++; $te_all++; } 
            elseif ($content[$i]['status'] == 3) { $te_oh++; $te_all++; } 
            elseif ($content[$i]['status'] == 4) { $te_d++; $te_all++; } 
            elseif ($content[$i]['status'] == 6) { $te_ptwr++; $te_all++; }
            // ... etc. ...
		  }

		  $data = array_merge($data, $content);

          // FIX #2: Only increment the offset if we did NOT use the alternate URL.
          // This stops the infinite loop.
          if (!$use_alternate_url) {
		    $offset += 300;
          } else {
            // If we did use the alternate, we must exit after this one successful run.
            break;
          }

		} else {
		  break;
		}
	  }

      return $data;
    }
}