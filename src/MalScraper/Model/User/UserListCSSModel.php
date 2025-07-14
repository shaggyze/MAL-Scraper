<?php

namespace MalScraper\Model\User;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;
use MalScraper\Model\General\InfoModel;

ini_set('max_execution_time', 20000);
ini_set('memory_limit', "2048M");
ini_set('max_file_size', 1000000000);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
            // No need for a DEBUG echo here as it's handled below
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

        // --- START OF FIX ---
        // This block unifies the data structure regardless of the source.
        if ($use_alternate_url && isset($content['data']) && is_array($content['data'])) {
            // If the alternate URL was used and has a 'data' key,
            // we take the 'data' part and convert it to a simple array.
            $content = array_values($content['data']);
        }
        // --- END OF FIX ---

		if ($content) {
		  $count = count($content);
		  for ($i = 0; $i < $count; $i++) {
            
            // This check is still good practice to prevent errors on any unexpectedly empty records.
            if (empty($content[$i]['anime_id']) && empty($content[$i]['manga_id'])) {
                continue; 
            }

			$content2 = [];
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
			if (!empty($content2['data']['duration'])) {
			  $episodes = intval($content2['data']['episodes']);
			  $duration = intval(str_replace(' min. per ep.', '', $content2['data']['duration']));
			  if ($episodes > 0 && $duration > 0) {
			    $content[$i]['total_runtime'] = floor($episodes * $duration / 60) . 'h ' . ($episodes * $duration % 60) . 'm';
			  } else {
				$content[$i]['total_runtime'] = 'N/A';
			  }
			} else {
				$content[$i]['total_runtime'] = 'N/A';
			}
			if (!empty($content2['data']['premiered'])) {
			  $content[$i]['year'] = str_replace(['Winter ', 'Spring ', 'Summer ', 'Fall '], '', $content2['data']['premiered']);
			} else {
			  if (!empty($content2['data']['aired']['start'])) {
			    $content[$i]['year'] = (int) substr($content2['data']['aired']['start'], -4);
			  } else {
			    if (!empty($content2['data']['published']['start'])) {
			      $content[$i]['year'] = (int) substr($content2['data']['published']['start'], -4);
			    } else {
			      $content[$i]['year'] = 'N/A';
			    }
			  }
			}
			if (!empty($content2['data']['genres'])) {
			  $genres = $content2['data']['genres'];
			  $genreNames = '';
			  if (is_array($genres)) {
			    foreach ($genres as $genre) {
				  $genreNames .= $genre['name'] . ', ';
			    }
			  }
			  $content[$i]['genres'] = rtrim($genreNames, ', ');
			} else {
			  if (!empty($content2['data']['genre'])) {
			    $genres = $content2['data']['genre'];
			    $genreNames = '';
				if (is_array($genres)) {
			      foreach ($genres as $genre) {
				    $genreNames .= $genre['name'] . ', ';
			      }
				}
			    $content[$i]['genres'] = rtrim($genreNames, ', ');
			  } else {
			  $content[$i]['genres'] = 'N/A';
			  }
			}
			if (!empty($content2['data']['themes'])) {
			  $themes = $content2['data']['themes'];
			  $themeNames = '';
			  if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			  }
			  $content[$i]['themes'] = rtrim($themeNames, ', ');
			} else {
			  if (!empty($content2['data']['theme'])) {
			    $themes = $content2['data']['theme'];
			    $themeNames = '';
			    if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			    }
			    $content[$i]['themes'] = rtrim($themeNames, ', ');
			  } else {
			    $content[$i]['themes'] = 'N/A';
			  }
			}
			if (!empty($content2['data']['demographic'])) {
			  $demographics = $content2['data']['demographic'];
			  $demographicNames = '';
			  if (is_array($demographics)) {
			  foreach ($demographics as $demographic) {
				$demographicNames .= $demographic['name'] . ', ';
			  }
			  }
			  $content[$i]['demographic'] = rtrim($demographicNames, ', ');
			} else {
			  $content[$i]['demographic'] = 'N/A';
			}
			if (!empty($content[$i]['anime_title'])) {
			  $content[$i]['anime_title'] = str_replace(['"', '[', ']'], '', $content[$i]['anime_title']);
			} else {
			  $content[$i]['manga_title'] = str_replace(['"', '[', ']'], '', $content[$i]['manga_title']);
			}
			if (!empty($content[$i]['anime_title_eng'])) {
			  $content[$i]['anime_title_eng'] = str_replace(['"', '[', ']'], '', $content[$i]['anime_title_eng']);
			} else {
			  $content[$i]['manga_english'] = str_replace(['"', '[', ']'], '', $content[$i]['manga_english']);
			}
			if (!empty($content[$i]['anime_id'])) {
			  if (isset($content2['data']['title_german']) && $content2['data']['title_german'] !== null) {
			    $content[$i]['anime_title_de'] = str_replace(['"', '[', ']'], '', $content2['data']['title_german']);
			  } else {
				if ($content[$i]['anime_title_eng'] !== 'N/A') {
			      $content[$i]['anime_title_de'] = $content[$i]['anime_title_eng'];
				} else {
				  $content[$i]['anime_title_de'] = $content[$i]['anime_title'];
				}
			  }
			} else {
			  if (isset($content2['data']['title_german']) && $content2['data']['title_german'] !== null) {
			    $content[$i]['manga_title_de'] = str_replace(['"', '[', ']'], '', $content2['data']['title_german']);
			  } else {
			    if ($content[$i]['manga_english'] !== 'N/A') {
			      $content[$i]['manga_title_de'] = $content[$i]['manga_english'];
				} else {
				  $content[$i]['manga_title_de'] = $content[$i]['manga_title'];
				}
			  }
			}
			if (!empty($content[$i]['anime_id'])) {
			  if (isset($content2['data']['title_japanese']) && $content2['data']['title_japanese'] !== null) {
			    $content[$i]['anime_title_jp'] = str_replace(['"', '[', ']'], '', $content2['data']['title_japanese']);
			  } else {
				if ($content[$i]['anime_title_eng'] !== 'N/A') {
			      $content[$i]['anime_title_jp'] = $content[$i]['anime_title_eng'];
				} else {
				  $content[$i]['anime_title_jp'] = $content[$i]['anime_title'];
				}
			  }
			} else {
			  if (isset($content2['data']['title_japanese']) && $content2['data']['title_japanese'] !== null) {
			    $content[$i]['manga_title_jp'] = str_replace(['"', '[', ']'], '', $content2['data']['title_japanese']);
			  } else {
			    if ($content[$i]['manga_english'] !== 'N/A') {
			      $content[$i]['manga_title_jp'] = $content[$i]['manga_english'];
				} else {
				  $content[$i]['manga_title_jp'] = $content[$i]['manga_title'];
				}
			  }
			}
			if (!empty($content[$i]['anime_id'])) {
			  if ($content[$i]['anime_title_eng'] == 'N/A') {
			    $content[$i]['anime_title_eng'] = $content[$i]['anime_title'];
			  }
			} else {
			  if ($content[$i]['manga_english'] == 'N/A') {
			    $content[$i]['manga_english'] = $content[$i]['manga_title'];
			  }
			}
			if (!empty($content[$i]['num_watched_episodes'])) {
			  if ($content[$i]['anime_num_episodes'] !== 0) {
			    $content[$i]['progress_percent'] = round(($content[$i]['num_watched_episodes'] / $content[$i]['anime_num_episodes']) * 100, 2);
			  } else {
			    $content[$i]['progress_percent'] = 0;
			  }
			} elseif (!empty($content[$i]['num_read_volumes'])) {
			  if ($content[$i]['manga_num_volumes'] !== 0) {
			    $content[$i]['progress_percent'] = round(($content[$i]['num_read_volumes'] / $content[$i]['manga_num_volumes']) * 100, 2);
			  } else {
			    $content[$i]['progress_percent'] = 0;
			  }
			} else {
			    $content[$i]['progress_percent'] = 0;
			}
			if (!empty($content2['data']['rank'])) {
			  $content[$i]['rank'] = $content2['data']['rank'];
			} else {
			  $content[$i]['rank'] = "N/A";
			}
			if (!empty($content2['data']['serialization'])) {
			  $serializations = $content2['data']['serialization'];
			  $serializationNames = '';
			  $serializations = !is_array($serializations) ? [] : $serializations;
			  foreach ($serializations as $serialization) {
				$serializationNames .= $serialization['name'] . ', ';
			  }
			  $content[$i]['serialization'] = rtrim($serializationNames, ', ');
			} else {
			  $content[$i]['serialization'] = 'N/A';
			}
			if (!empty($content['manga_magazines'])) {
			  $mangamagazines = $content['manga_magazines'];
			  $mangamagazineNames = '';
			  $mangamagazines = !is_array($mangamagazines) ? [] : $mangamagazines;
			  foreach ($mangamagazines as $mangamagazine) {
				$mangamagazineNames .= $mangamagazine['name'] . ', ';
			  error_log($mangamagazineNames . ' ' . $mangamagazine['name']);
			  }
			  $content[$i]['manga_magazines'] = rtrim($mangamagazineNames, ', ');
			} else {
			  $content[$i]['manga_magazines'] = 'N/A';
			}
			if (!empty($content[$i]['anime_image_path'])) {
			  $content[$i]['anime_image_path'] = Helper::imageUrlCleaner($content[$i]['anime_image_path']);
			} else {
			  $content[$i]['manga_image_path'] = Helper::imageUrlCleaner($content[$i]['manga_image_path']);
			}
			if (!empty($content[$i]['anime_id'])) {
			  $content[$i]['anime_image_path'] = Helper::imageUrlReplace($content[$i]['anime_id'], 'anime', $content[$i]['anime_image_path'], $this->_user);
			} else {
			  $content[$i]['manga_image_path'] = Helper::imageUrlReplace($content[$i]['manga_id'], 'manga', $content[$i]['manga_image_path'], $this->_user);
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
		  $content[$i]['total_entries_cwr'] = $te_cwr;
		  $content[$i]['total_entries_c'] = $te_c;
		  $content[$i]['total_entries_oh'] = $te_oh;
		  $content[$i]['total_entries_d'] = $te_d;
		  $content[$i]['total_entries_ptwr'] = $te_ptwr;
		  $content[$i]['total_entries_all'] = $te_all;
		  $content[$i]['\a'] = "-a";
		  }

		  $data = array_merge($data, $content);

		  $offset += 300;
		} else {
		  break;
		}
	  }

        return $data;
    }
}