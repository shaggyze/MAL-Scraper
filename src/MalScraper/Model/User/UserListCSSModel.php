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
            $content_json = false;
            $use_alternate_url = false;

            // --- Phase 1: Fetch Data ---
            
            // First, try the primary URL
            $primary_url = $this->_myAnimeListUrl . '/' . $this->_type . 'list/' . $this->_user . '/load.json?offset=' . $offset . '&status=' . $this->_status . '&genre=' . $this->_genre;
            $context = stream_context_create(['http' => ['ignore_errors' => true]]);
            $primary_content = @file_get_contents(htmlspecialchars_decode($primary_url), false, $context);
            
            $http_status = null;
            if (isset($http_response_header) && count($http_response_header) > 0) {
                preg_match('{HTTP\/\S+\s(\d{3})}', $http_response_header[0], $match);
                if (isset($match[1])) {
                    $http_status = (int)$match[1];
                }
            }

            // Decide if we need to fall back
            if ($primary_content === false || $http_status === 405) {
                // IMPORTANT: Only use the alternate URL on the first attempt (offset=0).
                // If the primary fails on a later page, we must exit the loop.
                if ($offset === 0) {
                    $use_alternate_url = true;
                    echo "DEBUG: Primary URL failed. Attempting alternate URL.\n";
                    $alternate_url = 'https://shaggyze.website/maldb/userlist/' . $this->_user . '_' . $this->_type . '_' . $this->_status . '_' . $this->_genre . '.json';
                    echo "DEBUG: Using alternate URL: " . $alternate_url . "\n";
                    $content_json = @file_get_contents(htmlspecialchars_decode($alternate_url));
                }
            } else {
                $content_json = $primary_content;
            }

            // --- Phase 2: Decode and Validate ---

            $content = null;
            if ($content_json) {
                $decoded = json_decode($content_json, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $decoded;
                }
            }

            // If the alternate URL was used, normalize its structure
            if ($use_alternate_url && isset($content['data']) && is_array($content['data'])) {
                $content = array_values($content['data']);
            }

            // THE ONLY EXIT POINT: If, after all that, we have no valid data, we are done.
            if (empty($content)) {
                break;
            }

            // --- Phase 3: Process Data ---
            
            // Your original processing logic remains completely untouched.
            $count = count($content);
            for ($i = 0; $i < $count; $i++) {
                if (!empty($content[$i]['anime_id'])) {
                    $subdirectory = get_subdirectory('info', 'anime', $content[$i]['anime_id']);
                    $url1 = 'https://shaggyze.website/msa/info?t=anime&id=' . $content[$i]['anime_id'];
                    $url2 = 'https://shaggyze.website/maldb/info/anime/' . $subdirectory . '/' . $content[$i]['anime_id'] . '.json';
                    if (!filter_var($url2, FILTER_VALIDATE_URL) || !file_get_contents($url2)) {$url2 = $url1;}
                    $content2 = json_decode(file_get_contents(htmlspecialchars_decode($url2)), true);
                    if (empty($content[$i]['anime_title_eng'])) {$content[$i]['anime_title_eng'] = "N/A";}
                } else {
                    $subdirectory = get_subdirectory('info', 'manga', $content[$i]['manga_id']);
                    $url1 = 'https://shaggyze.website/msa/info?t=manga&id=' . $content[$i]['manga_id'];
                    $url2 = 'https://shaggyze.website/maldb/info/manga/' . $subdirectory . '/' . $content[$i]['manga_id'] . '.json';
                    if (!filter_var($url2, FILTER_VALIDATE_URL) || !file_get_contents($url2)) {$url2 = $url1;}
                    $content2 = json_decode(file_get_contents(htmlspecialchars_decode($url2)), true);
                    if (empty($content[$i]['manga_english'])) {$content[$i]['manga_english'] = "N/A";}
                }
                // ... all of your other processing logic ...
                if ($content[$i]['status'] == 1) { $te_cwr++; $te_all++; } 
                elseif ($content[$i]['status'] == 2) { $te_c++; $te_all++; } 
                elseif ($content[$i]['status'] == 3) { $te_oh++; $te_all++; } 
                elseif ($content[$i]['status'] == 4) { $te_d++; $te_all++; } 
                elseif ($content[$i]['status'] == 6) { $te_ptwr++; $te_all++; }
                $content[$i]['total_entries_cwr'] = $te_cwr;
                $content[$i]['total_entries_c'] = $te_c;
                $content[$i]['total_entries_oh'] = $te_oh;
                $content[$i]['total_entries_d'] = $te_d;
                $content[$i]['total_entries_ptwr'] = $te_ptwr;
                $content[$i]['total_entries_all'] = $te_all;
                $content[$i]['\a'] = "-a";
            }

            $data = array_merge($data, $content);

            // --- Phase 4: Decide to Continue or Stop ---

            // If we just processed the alternate URL, our work is done.
            if ($use_alternate_url) {
                break;
            }

            // Otherwise, prepare for the next page from the primary source.
            $offset += 300;
        }

        return $data;
    }
}