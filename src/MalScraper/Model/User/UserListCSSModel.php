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
            'http' => ['ignore_errors' => true]
        ]);

        // 1. Attempt to fetch from the primary source.
        $content_json = @file_get_contents(htmlspecialchars_decode($primary_url), false, $context);

        if (isset($http_response_header) && count($http_response_header) > 0) {
            preg_match('{HTTP\/\S+\s(\d{3})}', $http_response_header[0], $match);
            if (isset($match[1])) {
                $http_status = (int)$match[1];
            }
        }

        // 2. Decide if we need to use the fallback.
        if ($content_json === false || ($http_status === 405)) {
            $use_alternate_url = true;
        }

        if ($use_alternate_url) {
            // IMPORTANT: If we are trying to paginate and the primary fails, we must stop.
            // Only use the alternate URL on the very first attempt (offset=0).
            if ($offset > 0) {
                break;
            }
            echo "DEBUG: Primary URL failed. Attempting alternate URL.\n";
            $alternate_url = 'https://shaggyze.website/maldb/userlist/'.$this->_user.'_'.$this->_type.'_'.$this->_status.'_'.$this->_genre.'.json';
            echo "DEBUG: Using alternate URL: " . $alternate_url . "\n";
            $content_json = @file_get_contents(htmlspecialchars_decode($alternate_url));
        }

        // 3. Decode the result.
        $content = null;
        if ($content_json) {
            $decoded_json = json_decode($content_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $content = $decoded_json;
            }
        }
        
        // 4. Normalize the data structure if the alternate URL was used.
        if ($use_alternate_url && isset($content['data']) && is_array($content['data'])) {
            $content = array_values($content['data']);
        }
        
        // 5. MAIN EXIT CONDITION: If there is no data to process, we are done.
        if (empty($content)) {
            break;
        }

        // 6. Process the data. (Your original code remains here).
        $count = count($content);
        for ($i = 0; $i < $count; $i++) {
            // Your entire for-loop with all its logic.
            // ... (e.g., fetching from url1/url2, calculating stats, etc.) ...
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
			if ($content[$i]['status'] == 1) {
			    $te_cwr += 1;
				$te_all += 1;
			} elseif ($content[$i]['status'] == 2) {
			    $te_c += 1;
				$te_all += 1;
			} elseif ($content[$i]['status'] == 3) {
			    $te_oh += 1;
				$te_all += 1;
			} elseif ($content[$i]['status'] == 4) {
			    $te_d += 1;
				$te_all += 1;
			} elseif ($content[$i]['status'] == 6) {
			    $te_ptwr += 1;
				$te_all += 1;
			}
        }
        $data = array_merge($data, $content);

        // 7. SECONDARY EXIT CONDITION: If we successfully used the backup, our job is done.
        if ($use_alternate_url) {
            break;
        }

        // 8. If we are still here, it's because the primary URL worked. Prepare for the next page.
        $offset += 300;
      }
      return $data;
    }
}