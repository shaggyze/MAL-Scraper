<?php

namespace MalScraper\Model\User;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * UserListModel class.
 */
class UserListModel extends MainModel
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
     * @var int
     */
    private $_status;

    /**
     * Anime/manga genre.
     *
     * @var int
     */
    private $_genre;

    /**
     * Anime/manga order.
     *
     * @var int
     */
    private $_order;

    // --- CONCURRENCY CONSTANTS (Optimized for large lists, e.g., 3,000+ items) ---
    // Number of user list pages to fetch concurrently (Each page is 300 items)
    const LIST_CONCURRENCY_SIZE = 15; 
    // This constant is included for consistency but is not strictly used in this model, 
    // as it does not fetch item metadata like UserListCSSModel.php.
    const ITEM_CONCURRENCY_SIZE = 100; 
    // Max entries per page returned by MAL load.json endpoint
    const OFFSET_STEP = 300; 
    
    /**
     * Default constructor.
     *
     * @param string $user
     * @param string $type
     * @param int $status
     * @param int $genre
     * @param int $order
     * @param string $parserArea
     *
     * @return void
     */
    public function __construct($user, $type, $status, $genre, $order, $parserArea = '#content')
    {
        $this->_user = $user;
        $this->_type = $type;
        $this->_status = $status;
		$this->_genre = $genre;
		$this->_order = $order;
        $this->_url = $this->_myAnimeListUrl.'/'.$type.'list/'.$user;
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
     * Executes a batch of cURL handles concurrently using curl_multi_exec.
     *
     * @param array $handles An array of cURL handles.
     * @return array An associative array of results, keyed by the original array key.
     */
    private function _executeMultiCurl(array $handles): array
    {
        if (empty($handles)) {
            return [];
        }
        $mh = curl_multi_init();
        foreach ($handles as $key => $ch) {
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
            
            if ($http_code == 200 && $response) {
                $results[$key] = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                     $results[$key] = null; // Mark as failed if JSON decode failed
                }
            } else {
                $results[$key] = null; 
            }
            curl_multi_remove_handle($mh, $ch);
        }

        curl_multi_close($mh);
        return $results;
    }


    /**
     * Get user list.
     *
     * @return array
     */
    private function getAllInfo()
    {
        if (!extension_loaded('curl')) {
            // Reverting to synchronous file_get_contents if cURL is not available
            $data = [];
            $offset = 0;
            while (true) {
                $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre.'&order='.$this->_order;

                // Synchronous fetch
                $content = json_decode(file_get_contents(htmlspecialchars_decode($url)), true);
                
                if ($content) {
                    $count = count($content);
                    for ($i = 0; $i < $count; $i++) {
                        // Original image path cleaning/replacing logic
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
                    }

                    $data = array_merge($data, $content);
                    $offset += self::OFFSET_STEP;
                } else {
                    break;
                }
            }
            return $data;
        }

        // --- Asynchronous Logic (Aggressive Concurrency) ---
        
        $all_list_content = [];
        $current_offset = 0;
        $list_finished = false;

        while (!$list_finished) {
            $list_handles = [];
            
            // 1. Prepare a batch of concurrent list page requests
            for ($i = 0; $i < self::LIST_CONCURRENCY_SIZE; $i++) {
                $offset_to_fetch = $current_offset + ($i * self::OFFSET_STEP);
                $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset_to_fetch.'&status='.$this->_status.'&genre='.$this->_genre.'&order='.$this->_order;
                
                // Use the expected offset as the key
                $list_handles[$offset_to_fetch] = $this->_initializeCurlHandle($url);
            }
            
            // 2. Execute the batch concurrently
            $list_results = $this->_executeMultiCurl($list_handles);

            $batch_found_new_data = false;
            foreach($list_results as $offset_fetched => $content) {
                if (is_array($content) && !empty($content)) {
                    $all_list_content = array_merge($all_list_content, $content);
                    $batch_found_new_data = true;
                    
                    // If the fetched page was less than the maximum possible (300 items), we reached the end
                    if (count($content) < self::OFFSET_STEP) {
                         $list_finished = true;
                    }
                } else {
                    // If the very first page of the batch failed, we assume the list has ended
                    if ($offset_fetched == $current_offset) {
                       $list_finished = true;
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
        
        // --- Apply Item-Level Logic (Image Cleaning) ---
        
        $final_data = [];
        foreach ($all_list_content as $item) {
            // Apply original item-level logic
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
            $final_data[] = $item;
        }

        return $final_data;
    }
}
