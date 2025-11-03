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

    // --- CONCURRENCY AND RETRY CONSTANTS ---
    const LIST_CONCURRENCY_SIZE = 15; 
    const ITEM_CONCURRENCY_SIZE = 100; 
    const OFFSET_STEP = 300; 

    // Retry Configuration
    const MAX_RETRIES = 3;
    const RETRY_DELAY_MS = 2000; // Base delay in milliseconds (2 seconds)

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
     * Default call.
     *
     * @param string $type
     * @param array  $id
     *
     * @return string|int
     */
    public function get_subdirectory($type, $id)
	{
        $subdirectory_number = floor($id / 10000);
        $subdirectory_path = '../info/' . $type . '/' . $subdirectory_number . '/';

        return strval($subdirectory_number);
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }

    /**
     * Executes a batch of cURL handles concurrently using curl_multi_exec with retry logic.
     *
     * @param array $handles An array of cURL handles keyed by their identifier (e.g., offset or index).
     * @return array An associative array of results, keyed by the original array key.
     */
    private function _executeMultiCurl(array $handles): array
    {
        if (empty($handles)) {
            return [];
        }

        $all_results = [];
        $retry_handles = $handles;

        for ($retry_attempt = 0; $retry_attempt <= self::MAX_RETRIES; $retry_attempt++) {
            
            if (empty($retry_handles)) {
                break; // All handles successfully completed
            }

            // Exponential Backoff Delay (except for the first attempt)
            if ($retry_attempt > 0) {
                // Wait 2s, 4s, 8s...
                $delay_ms = self::RETRY_DELAY_MS * pow(2, $retry_attempt - 1);
                usleep($delay_ms * 1000); 
                
                // Re-initialize handles for retry, necessary for cURL multi environment
                $new_retry_handles = [];
                foreach ($retry_handles as $key => $ch) {
                    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                    curl_close($ch);
                    $new_retry_handles[$key] = $this->_initializeCurlHandle($url);
                }
                $retry_handles = $new_retry_handles;
            }

            $mh = curl_multi_init();
            foreach ($retry_handles as $key => $ch) {
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
            
            $batch_failed_handles = [];
            foreach ($retry_handles as $key => $ch) {
                $response = curl_multi_getcontent($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);

                // Define success criteria
                $is_successful = $http_code == 200 && $response && json_decode($response, true) !== null;
                
                if ($is_successful) {
                    $all_results[$key] = json_decode($response, true);
                    // Remove successful handle from the retry list
                } else {
                    // Critical failure conditions (e.g., non-recoverable HTTP errors, cURL errors)
                    if ($retry_attempt === self::MAX_RETRIES) {
                        // Max retries reached, mark as failed permanently
                        $all_results[$key] = null;
                    } else {
                        // Queue for retry
                        $batch_failed_handles[$key] = $ch;
                    }
                }
                curl_multi_remove_handle($mh, $ch);
            }
            curl_multi_close($mh);

            // Set the remaining handles for the next retry loop
            $retry_handles = $batch_failed_handles; 
        }

        // Ensure any remaining failed handles (those that hit max retries) are marked null
        foreach ($retry_handles as $key => $ch) {
             if (!isset($all_results[$key])) {
                 $all_results[$key] = null;
             }
             curl_close($ch);
        }
        
        return $all_results;
    }


    /**
     * Get user list.
     *
     * @return array
     */
    public function getAllInfo()
    {
      if (!extension_loaded('curl')) {
          return ['error' => 'PHP cURL extension is required for asynchronous scraping.'];
      }
        
      $all_list_content = [];
      $current_offset = 0;
      $list_finished = false;

      // --- STAGE 1: Concurrent MAL User List Pages Fetching (LIST_CONCURRENCY_SIZE = 15) ---

      while (!$list_finished) {
          $list_handles = [];
          
          // 1. Prepare a batch of concurrent list page requests
          for ($i = 0; $i < self::LIST_CONCURRENCY_SIZE; $i++) {
              $offset_to_fetch = $current_offset + ($i * self::OFFSET_STEP);
              $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset_to_fetch.'&status='.$this->_status.'&genre='.$this->_genre;
              $list_handles[$offset_to_fetch] = $this->_initializeCurlHandle($url);
          }
          
          // 2. Execute the batch concurrently with retry logic
          $list_results = $this->_executeMultiCurl($list_handles);

          $batch_found_new_data = false;
          // Process the list results in order of offset fetched
          ksort($list_results); 

          foreach($list_results as $offset_fetched => $content) {
              if (is_array($content) && count($content) > 0) {
                  $all_list_content = array_merge($all_list_content, $content);
                  $batch_found_new_data = true;
                  
                  // If the fetched page was less than the maximum possible, we reached the end
                  if (count($content) < self::OFFSET_STEP) {
                       $list_finished = true;
                       break;
                  }
              } else {
                   // If the content is null or empty, and it's the expected next page, stop
                   if ($offset_fetched >= $current_offset) {
                       $list_finished = true;
                       break;
                   }
              }
          }
          
          // If the list is marked finished or we didn't find any data in the entire batch, break
          if ($list_finished || !$batch_found_new_data) {
              break;
          }
          
          // Move to the start of the next batch
          $current_offset += (self::LIST_CONCURRENCY_SIZE * self::OFFSET_STEP);
      }
      
      $total_list_entries = count($all_list_content);
      if ($total_list_entries === 0) {
          return [];
      }


      // --- STAGE 2: Concurrent Metadata Fetching for All Items (ITEM_CONCURRENCY_SIZE = 100) ---
      
      $item_handles = [];
      $metadata_results = [];
      
      // Process the list in batches for metadata fetching
      $total_batches = ceil($total_list_entries / self::ITEM_CONCURRENCY_SIZE);
      
      for ($batch_num = 0; $batch_num < $total_batches; $batch_num++) {
          
          $item_handles = [];
          $start_index = $batch_num * self::ITEM_CONCURRENCY_SIZE;
          $end_index = min($start_index + self::ITEM_CONCURRENCY_SIZE, $total_list_entries);

          // 1. Build the batch of item metadata URLs
          for ($i = $start_index; $i < $end_index; $i++) {
              $item = $all_list_content[$i];
              $id = !empty($item['anime_id']) ? $item['anime_id'] : $item['manga_id'];
              $t = !empty($item['anime_id']) ? 'anime' : 'manga';

              $subdirectory = $this->get_subdirectory($t, $id);
              //$url2 = 'https://shaggyze.website/maldb/info/' . $t . '/' . $subdirectory . '/' . $id . '.json';
              $url2 = 'https://shaggyze.website/msa/info?t=' . $t . '&id=' . $id;
              // Use the item's array index ($i) as the key to map the result back later
              $item_handles[$i] = $this->_initializeCurlHandle($url2);
          }

          // 2. Execute the batch concurrently with retry logic
          $batch_metadata_results = $this->_executeMultiCurl($item_handles);

          // 3. Merge the results into the main results array
          $metadata_results = array_merge($metadata_results, $batch_metadata_results);
      }
      
      // --- STAGE 3: Process Results and Apply Original Logic ---
      
      $data = [];
      $te_all = 0; $te_cwr = 0; $te_c = 0; $te_oh = 0; $te_d = 0; $te_ptwr = 0;

      foreach ($all_list_content as $i => $item) {
          $content2 = $metadata_results[$i]; // Metadata result for item $i
          
          // Original logic to handle content2/metadata
          if (is_array($content2)) {
              $data_meta = $content2['data'] ?? $content2;
          } else {
              $data_meta = []; 
          }
          
          // Reference to the item for easier modification
          $item_ref = $item; 
          
          // --- START: Original Logic Preserved (Mapping the Content2/Data back to Item) ---
          
          // Check for anime vs manga titles based on your original logic
          if (empty($item_ref['anime_id'])) {
			  if (isset($data_meta['title_german']) && $data_meta['title_german'] !== null) {
			    $item_ref['manga_title_de'] = str_replace(['"', '[', ']'], '', $data_meta['title_german']);
			  } else {
			    if ($item_ref['manga_english'] !== 'N/A') {
			      $item_ref['manga_title_de'] = $item_ref['manga_english'];
				} else {
				  $item_ref['manga_title_de'] = $item_ref['manga_title'];
				}
			  }
          } else {
			  if (isset($data_meta['title_german']) && $data_meta['title_german'] !== null) {
			    $item_ref['anime_title_de'] = str_replace(['"', '[', ']'], '', $data_meta['title_german']);
			  } else {
				if ($item_ref['anime_title_eng'] !== 'N/A') {
			      $item_ref['anime_title_de'] = $item_ref['anime_title_eng'];
				} else {
				  $item_ref['anime_title_de'] = $item_ref['anime_title'];
				}
			  }
          }
          if (empty($item_ref['anime_id'])) {
			  if (isset($data_meta['title_japanese']) && $data_meta['title_japanese'] !== null) {
			    $item_ref['manga_title_jp'] = str_replace(['"', '[', ']'], '', $data_meta['title_japanese']);
			  } else {
			    if ($item_ref['manga_english'] !== 'N/A') {
			      $item_ref['manga_title_jp'] = $item_ref['manga_english'];
				} else {
				  $item_ref['manga_title_jp'] = $item_ref['manga_title'];
				}
			  }
          } else {
			  if (isset($data_meta['title_japanese']) && $data_meta['title_japanese'] !== null) {
			    $item_ref['anime_title_jp'] = str_replace(['"', '[', ']'], '', $data_meta['title_japanese']);
			  } else {
				if ($item_ref['anime_title_eng'] !== 'N/A') {
			      $item_ref['anime_title_jp'] = $item_ref['anime_title_eng'];
				} else {
				  $item_ref['anime_title_jp'] = $item_ref['anime_title'];
				}
			  }
          }
          if (!empty($item_ref['anime_id'])) {
			  if ($item_ref['anime_title_eng'] == 'N/A') {
			    $item_ref['anime_title_eng'] = $item_ref['anime_title'];
			  }
          } else {
			  if ($item_ref['manga_english'] == 'N/A') {
			    $item_ref['manga_english'] = $item_ref['manga_title'];
			  }
          }
          if (!empty($item_ref['num_watched_episodes'])) {
			  if ($item_ref['anime_num_episodes'] !== 0) {
			    $item_ref['progress_percent'] = round(($item_ref['num_watched_episodes'] / $item_ref['anime_num_episodes']) * 100, 2);
			  } else {
			    $item_ref['progress_percent'] = 0;
			  }
          } elseif (!empty($item_ref['num_read_volumes'])) {
			  if ($item_ref['manga_num_volumes'] !== 0) {
			    $item_ref['progress_percent'] = round(($item_ref['num_read_volumes'] / $item_ref['manga_num_volumes']) * 100, 2);
			  } else {
			    $item_ref['progress_percent'] = 0;
			  }
          } else {
			  $item_ref['progress_percent'] = 0;
          }
          
          // The specific rank check:
          if (!empty($data_meta['rank'])) {
			  $item_ref['rank'] = $data_meta['rank'];
          } else {
			  $item_ref['rank'] = "N/A";
          }
          if (!empty($data_meta['broadcast'])) {
				$item_ref['broadcast'] = $data_meta['broadcast'];
			} else {
				$item_ref['broadcast'] = "";
			}
			if (!empty($data_meta['synopsis'])) {
			  $synopsis = preg_replace('/[\x0D]/', "", $data_meta['synopsis']);
			  $synopsis = str_replace(array("\n", "\t", "\r"), "-a ", $synopsis);
			  $synopsis = str_replace('"', '-"', $synopsis);
			  $synopsis = str_replace("'", "-'", $synopsis);
			  $item_ref['synopsis'] = $synopsis;
			} else {
			  $item_ref['synopsis'] = "N/A";
			}
			if (!empty($data_meta['duration'])) {
			  $episodes = intval($data_meta['episodes']);
			  $duration = intval(str_replace(' min. per ep.', '', $data_meta['duration']));
			  if ($episodes > 0 && $duration > 0) {
			    $item_ref['total_runtime'] = floor($episodes * $duration / 60) . 'h ' . ($episodes * $duration % 60) . 'm';
			  } else {
				$item_ref['total_runtime'] = 'N/A';
			  }
			} else {
				$item_ref['total_runtime'] = 'N/A';
			}
			if (!empty($data_meta['premiered'])) {
			  $item_ref['year'] = str_replace(['Winter ', 'Spring ', 'Summer ', 'Fall '], '', $data_meta['premiered']);
			} else {
			  if (!empty($data_meta['aired']['start'])) {
			    $item_ref['year'] = (int) substr($data_meta['aired']['start'], -4);
			  } else {
			    if (!empty($data_meta['published']['start'])) {
			      $item_ref['year'] = (int) substr($data_meta['published']['start'], -4);
			    } else {
			      $item_ref['year'] = 'N/A';
			    }
			  }
			}
			if (!empty($data_meta['genres'])) {
			  $genres = $data_meta['genres'];
			  $genreNames = '';
			  if (is_array($genres)) {
			    foreach ($genres as $genre) {
				  $genreNames .= $genre['name'] . ', ';
			    }
			  }
			  $item_ref['genres'] = rtrim($genreNames, ', ');
			} else {
			  if (!empty($data_meta['genre'])) {
			    $genres = $data_meta['genre'];
			    $genreNames = '';
				if (is_array($genres)) {
			      foreach ($genres as $genre) {
				    $genreNames .= $genre['name'] . ', ';
			      }
				}
			    $item_ref['genres'] = rtrim($genreNames, ', ');
			  } else {
			  $item_ref['genres'] = 'N/A';
			  }
			}
			if (!empty($data_meta['themes'])) {
			  $themes = $data_meta['themes'];
			  $themeNames = '';
			  if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			  }
			  $item_ref['themes'] = rtrim($themeNames, ', ');
			} else {
			  if (!empty($data_meta['theme'])) {
			    $themes = $data_meta['theme'];
			    $themeNames = '';
			    if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			    }
			    $item_ref['themes'] = rtrim($themeNames, ', ');
			  } else {
			    $item_ref['themes'] = 'N/A';
			  }
			}
			if (!empty($data_meta['demographic'])) {
			  $demographics = $data_meta['demographic'];
			  $demographicNames = '';
			  if (is_array($demographics)) {
			  foreach ($demographics as $demographic) {
				$demographicNames .= $demographic['name'] . ', ';
			  }
			  }
			  $item_ref['demographic'] = rtrim($demographicNames, ', ');
			} else {
			  $item_ref['demographic'] = 'N/A';
			}
			if (!empty($data_meta['serialization'])) {
			  $serializations = $data_meta['serialization'];
			  $serializationNames = '';
			  $serializations = !is_array($serializations) ? [] : $serializations;
			  foreach ($serializations as $serialization) {
				$serializationNames .= $serialization['name'] . ', ';
			  }
			  $item_ref['serialization'] = rtrim($serializationNames, ', ');
			} else {
			  $item_ref['serialization'] = 'N/A';
			}
			if (!empty($item_ref['manga_magazines'])) {
			  $mangamagazines = $item_ref['manga_magazines'];
			  $mangamagazineNames = '';
			  $mangamagazines = !is_array($mangamagazines) ? [] : $mangamagazines;
			  foreach ($mangamagazines as $mangamagazine) {
				$mangamagazineNames .= $mangamagazine['name'] . ', ';
			  }
			  $item_ref['manga_magazines'] = rtrim($mangamagazineNames, ', ');
			} else {
			  $item_ref['manga_magazines'] = 'N/A';
			}

          // Count totals
          if ($item_ref['status'] == 1) {
			  $te_cwr += 1;
			  $te_all += 1;
          } elseif ($item_ref['status'] == 2) {
			  $te_c += 1;
			  $te_all += 1;
          } elseif ($item_ref['status'] == 3) {
			  $te_oh += 1;
			  $te_all += 1;
          } elseif ($item_ref['status'] == 4) {
			  $te_d += 1;
			  $te_all += 1;
          } elseif ($item_ref['status'] == 6) {
			  $te_ptwr += 1;
			  $te_all += 1;
          }
          // --- END: Original Logic Preserved ---

          // Update titles with clean versions
          if (!empty($item_ref['anime_title'])) {
			  $item_ref['anime_title'] = str_replace(['"', '[', ']'], '', $item_ref['anime_title']);
			} else {
			  $item_ref['manga_title'] = str_replace(['"', '[', ']'], '', $item_ref['manga_title']);
			}
			if (!empty($item_ref['anime_title_eng'])) {
			  $item_ref['anime_title_eng'] = str_replace(['"', '[', ']'], '', $item_ref['anime_title_eng']);
			} else {
			  $item_ref['manga_english'] = str_replace(['"', '[', ']'], '', $item_ref['manga_english']);
			}
			if (!empty($item_ref['anime_image_path'])) {
			  $item_ref['anime_image_path'] = Helper::imageUrlCleaner($item_ref['anime_image_path']);
			} else {
			  $item_ref['manga_image_path'] = Helper::imageUrlCleaner($item_ref['manga_image_path']);
			}
			if (!empty($item_ref['anime_id'])) {
			  $item_ref['anime_image_path'] = Helper::imageUrlReplace($item_ref['anime_id'], 'anime', $item_ref['anime_image_path'], $this->_user);
			} else {
			  $item_ref['manga_image_path'] = Helper::imageUrlReplace($item_ref['manga_id'], 'manga', $item_ref['manga_image_path'], $this->_user);
			}
			$item_ref['\a'] = "-a";

          $data[] = $item_ref;
      }

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
