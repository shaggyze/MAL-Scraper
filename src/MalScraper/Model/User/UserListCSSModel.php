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
            'http' => [
                'ignore_errors' => true
            ]
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
            echo "DEBUG: Primary URL failed. Attempting alternate URL.\n";
            $alternate_url = 'https://shaggyze.website/maldb/userlist/'.$this->_user.'_'.$this->_type.'_'.$this->_status.'_'.$this->_genre.'.json';
            echo "DEBUG: Using alternate URL: " . $alternate_url . "\n";
            $content_json = @file_get_contents(htmlspecialchars_decode($alternate_url));
        }

        if ($content_json !== false) {
            $content = json_decode($content_json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "DEBUG: Error decoding JSON: " . json_last_error_msg() . "\n";
                $content = null;
            }
        } else {
            echo "DEBUG: Failed to retrieve content from all URLs.\n";
        }

        if ($use_alternate_url && isset($content['data']) && is_array($content['data'])) {
            $content = array_values($content['data']);
        }

		if ($content) {
            $count = count($content);
            for ($i = 0; $i < $count; $i++) {
            
                // --- FIX PART 1: Robust Guard Clause ---
                // Skip any entry that is not a valid array or is missing its core ID.
                if (!is_array($content[$i]) || (empty($content[$i]['anime_id']) && empty($content[$i]['manga_id']))) {
                    continue; 
                }

                // --- FIX PART 2: Handle Scraper Failure Gracefully ---
                // Initialize $content2 with a default structure.
                $content2 = ['data' => []];
                if (!empty($content[$i]['anime_id'])) {
                    $infoModel = new InfoModel('anime', $content[$i]['anime_id']);
                    $infoData = $infoModel->getAllInfo();
                    if ($infoData) {
                        $content2['data'] = $infoData;
                    }
                    if (empty($content[$i]['anime_title_eng'])) {$content[$i]['anime_title_eng'] = "N/A";}
                } else {
                    $infoModel = new InfoModel('manga', $content[$i]['manga_id']);
                    $infoData = $infoModel->getAllInfo();
                    if ($infoData) {
                        $content2['data'] = $infoData;
                    }
                    if (empty($content[$i]['manga_english'])) {$content[$i]['manga_english'] = "N/A";}
                }
                
                // Now, all the checks below will work safely even if InfoModel failed.
                if (!empty($content2['data']['broadcast'])) {
                    $content[$i]['broadcast'] = $content2['data']['broadcast'];
                } else {
                    $content[$i]['broadcast'] = "";
                }
                if (!empty($content2['data']['synopsis'])) {
                  $synopsis = preg_replace('/[\x0D]/', "", $content2['data']['synopsis']);
                  $synopsis = str_replace(array("\n", "\t", "\r"), "-a ", $synopsis);
                  $synopsis = str_replace('"', '-"', $synopsis);
                  $synopsis = str_replace("'", "-'", $synopsis);
                  $content[$i]['synopsis'] = $synopsis;
                } else {
                  $content[$i]['synopsis'] = "N/A";
                }

                // ... (rest of the data processing logic remains the same) ...

                if (!empty($content[$i]['num_watched_episodes'])) {
                    $total_episodes = intval($content[$i]['anime_num_episodes']);
                    if ($total_episodes > 0) {
                        $content[$i]['progress_percent'] = round(($content[$i]['num_watched_episodes'] / $total_episodes) * 100, 2);
                    } else {
                        $content[$i]['progress_percent'] = 0;
                    }
                } elseif (!empty($content[$i]['num_read_volumes'])) {
                    $total_volumes = intval($content[$i]['manga_num_volumes']);
                    if ($total_volumes > 0) {
                        $content[$i]['progress_percent'] = round(($content[$i]['num_read_volumes'] / $total_volumes) * 100, 2);
                    } else {
                        $content[$i]['progress_percent'] = 0;
                    }
                } else {
                    $content[$i]['progress_percent'] = 0;
                }

                // ... (the status counter logic remains the same) ...
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
                $content[$i]['total_entries_cwr'] = $te_cwr;
                $content[$i]['total_entries_c'] = $te_c;
                $content[$i]['total_entries_oh'] = $te_oh;
                $content[$i]['total_entries_d'] = $te_d;
                $content[$i]['total_entries_ptwr'] = $te_ptwr;
                $content[$i]['total_entries_all'] = $te_all;
                $content[$i]['\a'] = "-a";
            }

            $data = array_merge($data, $content);

            if ($use_alternate_url) {
                break;
            }

            $offset += 300;
		} else {
            break;
		}
	  }

        return $data;
    }
}