<?php

namespace MalScraper\Model\User;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;
use MalScraper\Model\General\InfoModel;

// (ini_set and error_reporting lines remain the same)
ini_set('max_execution_time', 20000);
ini_set('memory_limit', "2048M");
ini_set('max_file_size', 1000000000);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


class UserListCSSModel extends MainModel
{
    // (Properties and constructor remain the same)
    private $_user;
    private $_type;
    private $_status;
    private $_genre;

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
                'http' => ['ignore_errors' => true]
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
            }
            
            $content = null;

            if ($use_alternate_url) {
                // We only try the alternate URL once, so we check if offset is 0.
                // This prevents re-fetching the same backup file in a loop.
                if ($offset > 0) {
                    break;
                }
                echo "DEBUG: Primary URL failed. Attempting alternate URL.\n";
                $alternate_url = 'https://shaggyze.website/maldb/userlist/'.$this->_user.'_'.$this->_type.'_'.$this->_status.'_'.$this->_genre.'.json';
                echo "DEBUG: Using alternate URL: " . $alternate_url . "\n";
                $content_json = @file_get_contents(htmlspecialchars_decode($alternate_url));
            }

            if ($content_json !== false) {
                $content = json_decode($content_json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $content = null;
                }
            }

            if ($use_alternate_url && isset($content['data']) && is_array($content['data'])) {
                $content = array_values($content['data']);
            }

            // --- Definitive Exit Condition ---
            // If at any point we have no valid content, we are done.
            if (empty($content)) {
                break;
            }

            // --- Defensive Processing Loop ---
            foreach ($content as &$item) {
                // 1. Check if the item is a valid array. If not, skip it.
                if (!is_array($item)) {
                    continue;
                }

                $id = null;
                $type = null;

                if (!empty($item['anime_id'])) {
                    $id = $item['anime_id'];
                    $type = 'anime';
                } elseif (!empty($item['manga_id'])) {
                    $id = $item['manga_id'];
                    $type = 'manga';
                }

                // 2. If there's no ID, we can't process it. Skip it.
                if ($id === null) {
                    continue;
                }

                // 3. Get rich data, but anticipate failure.
                $infoModel = new InfoModel($type, $id);
                $infoData = $infoModel->getAllInfo();
                
                // If the scraper fails, $infoData will be empty. We'll add default values.
                $content2 = ['data' => $infoData ?: []];

                // 4. Safely add all your data, using null coalescing (??) to prevent errors
                $item['broadcast'] = $content2['data']['broadcast'] ?? "";
                $item['synopsis'] = $content2['data']['synopsis'] ?? "N/A";
                // ... continue this pattern for all scraped data ...
                $item['rank'] = $content2['data']['rank'] ?? "N/A";

                // ... (your existing logic with default values) ...
                
                // 5. Defensively handle progress calculation
                $item['progress_percent'] = 0;
                if (!empty($item['num_watched_episodes'])) {
                    $total_episodes = intval($item['anime_num_episodes'] ?? 0);
                    if ($total_episodes > 0) {
                        $item['progress_percent'] = round(($item['num_watched_episodes'] / $total_episodes) * 100, 2);
                    }
                } elseif (!empty($item['num_read_volumes'])) {
                    $total_volumes = intval($item['manga_num_volumes'] ?? 0);
                    if ($total_volumes > 0) {
                        $item['progress_percent'] = round(($item['num_read_volumes'] / $total_volumes) * 100, 2);
                    }
                }

                // 6. Defensively handle status counting
                $status = intval($item['status'] ?? 0);
                if ($status == 1) { $te_cwr++; $te_all++; }
                elseif ($status == 2) { $te_c++; $te_all++; }
                elseif ($status == 3) { $te_oh++; $te_all++; }
                elseif ($status == 4) { $te_d++; $te_all++; }
                elseif ($status == 6) { $te_ptwr++; $te_all++; }

                $item['total_entries_cwr'] = $te_cwr;
                $item['total_entries_c'] = $te_c;
                $item['total_entries_oh'] = $te_oh;
                $item['total_entries_d'] = $te_d;
                $item['total_entries_ptwr'] = $te_ptwr;
                $item['total_entries_all'] = $te_all;
                $item['\a'] = "-a";
            }
            unset($item); // Unset reference from foreach

            $data = array_merge($data, $content);

            // If we used the alternate URL, its job is done. Exit the loop.
            if ($use_alternate_url) {
                break;
            }

            // If we are here, we must have used the primary URL. Increment for next page.
            $offset += 300;
        }

        return $data;
    }
}