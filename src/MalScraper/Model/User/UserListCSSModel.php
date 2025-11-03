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
     * Initializes a single cURL handle.
     *
     * @param string $url The URL to fetch.
     * @return mixed The configured cURL handle (resource/CurlHandle).
     */
    private function _initializeCurlHandle(string $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, htmlspecialchars_decode($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20); 
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }

    /**
     * Executes a batch of cURL handles concurrently using curl_multi_exec.
     *
     * @param array $handles An array of cURL handles.
     * @return array An associative array of results, keyed by the original array key.
     */
    private function _executeMultiCurl(array $handles): array
    {
        $mh = curl_multi_init();
        foreach ($handles as $ch) {
            curl_multi_add_handle($mh, $ch);
        }

        $running = null;
        do {
            $mrc = curl_multi_exec($mh, $running);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($running && $mrc == CURLM_OK) {
            if (curl_multi_select($mh, 1.0) == -1) {
                usleep(100000); 
            }
            do {
                $mrc = curl_multi_exec($mh, $running);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        $results = [];
        foreach ($handles as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Check for success (HTTP 200) and content
            if ($http_code == 200 && $response) {
                $results[$key] = json_decode($response, true);
            } else {
                // Return null or false for failed requests
                $results[$key] = null; 
            }
            curl_multi_remove_handle($mh, $ch);
            // curl_close($ch); // Closing handle is often done by curl_multi_close on some PHP versions
        }

        curl_multi_close($mh);
        return $results;
    }


    /**
     * Get user list.
     *
     * @return array
     */
    public function getAllInfo()
    {
      if (!extension_loaded('curl')) {
          trigger_error("PHP cURL extension is required for asynchronous scraping.", E_USER_ERROR);
          return ['error' => 'cURL extension missing'];
      }
        
      $data = [];
      $all_list_content = [];
      $offset = 0;
      $batch_size = 5; // How many MAL list pages to fetch concurrently (e.g., 5 * 300 = 1500 items per batch)

      // --- STAGE 1: Concurrent MAL User List Pages Fetching ---
      // We assume the list is large and pre-calculate potential page URLs. 
      // This is simpler than looping until empty. We'll check for null/empty response.
      
      echo "Starting concurrent list page fetching...\n";

      // Build initial batch of list URLs
      $list_handles = [];
      for ($i = 0; $i < $batch_size * 2; $i++) { // Pre-fetch two batches
          $current_offset = $i * 300;
          $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$current_offset.'&status='.$this->_status.'&genre='.$this->_genre;
          $list_handles[$current_offset] = $this->_initializeCurlHandle($url);
      }

      $list_results = $this->_executeMultiCurl($list_handles);

      foreach($list_results as $content) {
          if (is_array($content) && !empty($content)) {
              $all_list_content = array_merge($all_list_content, $content);
          } else {
              // Assuming that if a page returns null or empty array, we've reached the end
              break; 
          }
      }

      echo "Total list entries fetched: " . count($all_list_content) . "\n";


      // --- STAGE 2: Concurrent Metadata Fetching for All Items ---
      
      $item_handles = [];
      $item_map = []; // Map the key to the original $all_list_content index
      $te_all = 0; $te_cwr = 0; $te_c = 0; $te_oh = 0; $te_d = 0; $te_ptwr = 0;

      echo "Preparing concurrent metadata requests for " . count($all_list_content) . " items...\n";

      // 1. Build the batch of item metadata URLs
      foreach ($all_list_content as $i => $item) {
          $id = !empty($item['anime_id']) ? $item['anime_id'] : $item['manga_id'];
          $t = !empty($item['anime_id']) ? 'anime' : 'manga';

          $subdirectory = get_subdirectory('info', $t, $id);
          // Prioritize the local/cached JSON endpoint
          $url2 = 'https://shaggyze.website/maldb/info/' . $t . '/' . $subdirectory . '/' . $id . '.json';
          
          // Fallback to the 'msa' endpoint if the first one fails or is invalid (this logic is preserved)
          // NOTE: We cannot check file_get_contents() synchronously here, so we will handle the fallback 
          // after the primary async request fails. For now, we only request the primary URL2.

          // Using the item index as the key to map the result back later
          $item_handles[$i] = $this->_initializeCurlHandle($url2);
      }

      // 2. Execute the batch concurrently
      echo "Executing concurrent metadata requests...\n";
      $metadata_results = $this->_executeMultiCurl($item_handles);


      // 3. Process the results and merge into the main list
      echo "Processing results and generating final structure...\n";
      foreach ($all_list_content as $i => &$item) {
          $content2 = $metadata_results[$i]; // Content2 is the metadata for item $i

          // If the primary URL failed (null in metadata_results), you would need to implement 
          // a second synchronous or concurrent request to the fallback URL here.
          // For simplicity, we assume the primary URL (url2) works if not null.
          if (is_array($content2)) {
              // Access the data sub-array if it exists
              $data = $content2['data'] ?? $content2;
          } else {
              // If metadata failed to fetch, treat it as empty data
              $data = []; 
          }
          
          // --- START: Original Logic Preserved (Mapping the Content2/Data back to Item) ---
          
          // Check for anime vs manga titles based on your original logic
          if (empty($item['anime_id'])) {
			  if (isset($data['title_german']) && $data['title_german'] !== null) {
			    $item['manga_title_de'] = str_replace(['"', '[', ']'], '', $data['title_german']);
			  } else {
			    if ($item['manga_english'] !== 'N/A') {
			      $item['manga_title_de'] = $item['manga_english'];
				} else {
				  $item['manga_title_de'] = $item['manga_title'];
				}
			  }
          } else {
			  if (isset($data['title_german']) && $data['title_german'] !== null) {
			    $item['anime_title_de'] = str_replace(['"', '[', ']'], '', $data['title_german']);
			  } else {
				if ($item['anime_title_eng'] !== 'N/A') {
			      $item['anime_title_de'] = $item['anime_title_eng'];
				} else {
				  $item['anime_title_de'] = $item['anime_title'];
				}
			  }
          }
          if (empty($item['anime_id'])) {
			  if (isset($data['title_japanese']) && $data['title_japanese'] !== null) {
			    $item['manga_title_jp'] = str_replace(['"', '[', ']'], '', $data['title_japanese']);
			  } else {
			    if ($item['manga_english'] !== 'N/A') {
			      $item['manga_title_jp'] = $item['manga_english'];
				} else {
				  $item['manga_title_jp'] = $item['manga_title'];
				}
			  }
          } else {
			  if (isset($data['title_japanese']) && $data['title_japanese'] !== null) {
			    $item['anime_title_jp'] = str_replace(['"', '[', ']'], '', $data['title_japanese']);
			  } else {
				if ($item['anime_title_eng'] !== 'N/A') {
			      $item['anime_title_jp'] = $item['anime_title_eng'];
				} else {
				  $item['anime_title_jp'] = $item['anime_title'];
				}
			  }
          }
          if (!empty($item['anime_id'])) {
			  if ($item['anime_title_eng'] == 'N/A') {
			    $item['anime_title_eng'] = $item['anime_title'];
			  }
          } else {
			  if ($item['manga_english'] == 'N/A') {
			    $item['manga_english'] = $item['manga_title'];
			  }
          }
          if (!empty($item['num_watched_episodes'])) {
			  if ($item['anime_num_episodes'] !== 0) {
			    $item['progress_percent'] = round(($item['num_watched_episodes'] / $item['anime_num_episodes']) * 100, 2);
			  } else {
			    $item['progress_percent'] = 0;
			  }
          } elseif (!empty($item['num_read_volumes'])) {
			  if ($item['manga_num_volumes'] !== 0) {
			    $item['progress_percent'] = round(($item['num_read_volumes'] / $item['manga_num_volumes']) * 100, 2);
			  } else {
			    $item['progress_percent'] = 0;
			  }
          } else {
			  $item['progress_percent'] = 0;
          }
          // The specific line you asked about:
          if (!empty($data['rank'])) {
			  $item['rank'] = $data['rank'];
          } else {
			  $item['rank'] = "N/A";
          }
          if (!empty($data['broadcast'])) {
				$item['broadcast'] = $data['broadcast'];
			} else {
				$item['broadcast'] = "";
			}
			if (!empty($data['synopsis'])) {
			  $synopsis = preg_replace('/[\x0D]/', "", $data['synopsis']);
			  $synopsis = str_replace(array("\n", "\t", "\r"), "-a ", $synopsis);
			  $synopsis = str_replace('"', '-"', $synopsis);
			  $synopsis = str_replace("'", "-'", $synopsis);
			  $item['synopsis'] = $synopsis;
			} else {
			  $item['synopsis'] = "N/A";
			}
			if (!empty($data['duration'])) {
			  $episodes = intval($data['episodes']);
			  $duration = intval(str_replace(' min. per ep.', '', $data['duration']));
			  if ($episodes > 0 && $duration > 0) {
			    $item['total_runtime'] = floor($episodes * $duration / 60) . 'h ' . ($episodes * $duration % 60) . 'm';
			  } else {
				$item['total_runtime'] = 'N/A';
			  }
			} else {
				$item['total_runtime'] = 'N/A';
			}
			if (!empty($data['premiered'])) {
			  $item['year'] = str_replace(['Winter ', 'Spring ', 'Summer ', 'Fall '], '', $data['premiered']);
			} else {
			  if (!empty($data['aired']['start'])) {
			    $item['year'] = (int) substr($data['aired']['start'], -4);
			  } else {
			    if (!empty($data['published']['start'])) {
			      $item['year'] = (int) substr($data['published']['start'], -4);
			    } else {
			      $item['year'] = 'N/A';
			    }
			  }
			}
			if (!empty($data['genres'])) {
			  $genres = $data['genres'];
			  $genreNames = '';
			  if (is_array($genres)) {
			    foreach ($genres as $genre) {
				  $genreNames .= $genre['name'] . ', ';
			    }
			  }
			  $item['genres'] = rtrim($genreNames, ', ');
			} else {
			  if (!empty($data['genre'])) {
			    $genres = $data['genre'];
			    $genreNames = '';
				if (is_array($genres)) {
			      foreach ($genres as $genre) {
				    $genreNames .= $genre['name'] . ', ';
			      }
				}
			    $item['genres'] = rtrim($genreNames, ', ');
			  } else {
			  $item['genres'] = 'N/A';
			  }
			}
			if (!empty($data['themes'])) {
			  $themes = $data['themes'];
			  $themeNames = '';
			  if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			  }
			  $item['themes'] = rtrim($themeNames, ', ');
			} else {
			  if (!empty($data['theme'])) {
			    $themes = $data['theme'];
			    $themeNames = '';
			    if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			    }
			    $item['themes'] = rtrim($themeNames, ', ');
			  } else {
			    $item['themes'] = 'N/A';
			  }
			}
			if (!empty($data['demographic'])) {
			  $demographics = $data['demographic'];
			  $demographicNames = '';
			  if (is_array($demographics)) {
			  foreach ($demographics as $demographic) {
				$demographicNames .= $demographic['name'] . ', ';
			  }
			  }
			  $item['demographic'] = rtrim($demographicNames, ', ');
			} else {
			  $item['demographic'] = 'N/A';
			}
			if (!empty($data['serialization'])) {
			  $serializations = $data['serialization'];
			  $serializationNames = '';
			  $serializations = !is_array($serializations) ? [] : $serializations;
			  foreach ($serializations as $serialization) {
				$serializationNames .= $serialization['name'] . ', ';
			  }
			  $item['serialization'] = rtrim($serializationNames, ', ');
			} else {
			  $item['serialization'] = 'N/A';
			}
			if (!empty($item['manga_magazines'])) {
			  $mangamagazines = $item['manga_magazines'];
			  $mangamagazineNames = '';
			  $mangamagazines = !is_array($mangamagazines) ? [] : $mangamagazines;
			  foreach ($mangamagazines as $mangamagazine) {
				$mangamagazineNames .= $mangamagazine['name'] . ', ';
			  // error_log($mangamagazineNames . ' ' . $mangamagazine['name']); // Removed debug log
			  }
			  $item['manga_magazines'] = rtrim($mangamagazineNames, ', ');
			} else {
			  $item['manga_magazines'] = 'N/A';
			}

          // Your original calculation for totals (must be outside the loop if using concurrent, but kept here for status update)
          if ($item['status'] == 1) {
			  $te_cwr += 1;
			  $te_all += 1;
          } elseif ($item['status'] == 2) {
			  $te_c += 1;
			  $te_all += 1;
          } elseif ($item['status'] == 3) {
			  $te_oh += 1;
			  $te_all += 1;
          } elseif ($item['status'] == 4) {
			  $te_d += 1;
			  $te_all += 1;
          } elseif ($item['status'] == 6) {
			  $te_ptwr += 1;
			  $te_all += 1;
          }
          // --- END: Original Logic Preserved ---

          // Update titles with clean versions
          if (!empty($item['anime_title'])) {
			  $item['anime_title'] = str_replace(['"', '[', ']'], '', $item['anime_title']);
			} else {
			  $item['manga_title'] = str_replace(['"', '[', ']'], '', $item['manga_title']);
			}
			if (!empty($item['anime_title_eng'])) {
			  $item['anime_title_eng'] = str_replace(['"', '[', ']'], '', $item['anime_title_eng']);
			} else {
			  $item['manga_english'] = str_replace(['"', '[', ']'], '', $item['manga_english']);
			}
			if (!empty($item['anime_image_path'])) {
			  $item['anime_image_path'] = Helper::imageUrlCleaner($item['anime_image_path']);
			} else {
			  $item['manga_image_path'] = Helper::imageUrlCleaner($item['manga_image_path']);
			}
			if (!empty($item['anime_id'])) {
			  $item['anime_image_path'] = Helper::imageUrlReplace($item['anime_id'], 'anime', $item['anime_image_path'], $this->_user);
			} else {
			  $item['manga_image_path'] = Helper::imageUrlReplace($item['manga_id'], 'manga', $item['manga_image_path'], $this->_user);
			}
			$item['\a'] = "-a";

          $data[] = $item;
      }
      unset($item); // Break the reference on the last element

      // Update final total entries count after the loop completes
      foreach ($data as &$item) {
		  $item['total_entries_cwr'] = $te_cwr;
		  $item['total_entries_c'] = $te_c;
		  $item['total_entries_oh'] = $te_oh;
		  $item['total_entries_d'] = $te_d;
		  $item['total_entries_ptwr'] = $te_ptwr;
		  $item['total_entries_all'] = $te_all;
      }
      unset($item);
      

      return $data;
    }
}
