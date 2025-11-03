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
    const debug = false; // Set to true to see debug echo output
    // Number of user list pages to fetch concurrently (Each page is 300 items)
    public static $LIST_CONCURRENCY_SIZE = 10; 
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
        $this->_url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset=0';
        $this->_parserArea = $parserArea;

        parent::errorCheck($this);
    }
	
    /**
     * Initializes a cURL handle with common options.
     *
     * @param string $url The URL to set.
     * @return resource The cURL handle.
     */
    private function initCurlHandle($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, htmlspecialchars_decode($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set a reasonable timeout
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        return $ch;
    }

    /**
     * Get user list info by concurrently fetching all pages and processing them.
     *
     * @return array
     */
    public function getAllInfo()
    {
        // --- Dynamic Concurrency Adjustment (FIXED) ---
        // We use self::$LIST_CONCURRENCY_SIZE instead of self::LIST_CONCURRENCY_SIZE
        if ($this->_user == '_All_') {
            self::$LIST_CONCURRENCY_SIZE = 75; 
            if (self::debug) {
                echo "UserListModel: Setting LIST_CONCURRENCY_SIZE to 75 for '_All_'.\n";
            }
        } else {
            if (self::debug) {
                echo "UserListModel: Using default LIST_CONCURRENCY_SIZE of 10.\n";
            }
        }
        // Renamed from $all_items to $data
        $data = [];
        $current_offset = 0;

        if (self::debug) {
            echo "--- UserListModel: Starting concurrent fetch (Batch Size: " . self::LIST_CONCURRENCY_SIZE . ") ---\n";
        }

        // --- 1. Concurrent Fetching Loop ---
        while (true) {
            $master_mh = curl_multi_init();
            $handles = [];
            $batch_found_new_data = false;
            $max_offset_in_batch = 0;

            // Prepare batch of concurrent requests
            for ($i = 0; $i < self::LIST_CONCURRENCY_SIZE; $i++) {
                $offset_to_fetch = $current_offset + ($i * self::OFFSET_STEP);
                $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset_to_fetch.'&status='.$this->_status.'&genre='.$this->_genre.'&order='.$this->_order;
                
                $ch = $this->initCurlHandle($url);
                curl_multi_add_handle($master_mh, $ch);
                $handles[$offset_to_fetch] = $ch;
                $max_offset_in_batch = $offset_to_fetch;
            }

            if (self::debug) {
                echo "UserListModel: Fetching batch starting at offset $current_offset (up to $max_offset_in_batch)...\n";
            }

            // Execute concurrent requests
            $running = null;
            do {
                curl_multi_exec($master_mh, $running);
            } while ($running > 0);
            
            $list_finished = false;

            // Process results
            foreach ($handles as $offset_fetched => $ch) {
                $response = curl_multi_getcontent($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_multi_remove_handle($master_mh, $ch);
                curl_close($ch);

                if ($http_code === 200 && !$curl_error && $response) {
                    $content = json_decode($response, true);
                    if (is_array($content) && count($content) > 0) {
                        // Merge into $data
                        $data = array_merge($data, $content);
                        $batch_found_new_data = true;
                        
                        if (self::debug) {
                            echo "UserListModel: SUCCESS offset $offset_fetched. Items: " . count($content) . "\n";
                        }

                        // If the page returned fewer than OFFSET_STEP, we are done
                        if (count($content) < self::OFFSET_STEP) {
                             $list_finished = true;
                        }

                    } else {
                        if (self::debug) {
                            echo "UserListModel: Empty JSON for offset $offset_fetched. Assuming end of list.\n";
                        }
                        // If the list is empty at this offset, assume the list ends here
                        $list_finished = true;
                    }
                } else {
                    if (self::debug) {
                        echo "UserListModel: FAILED fetch for offset $offset_fetched. HTTP: $http_code, Error: $curl_error\n";
                    }
                    // If the first page of the batch failed, we assume the list has ended
                    if ($offset_fetched == $current_offset) {
                       $list_finished = true;
                    }
                }
            }

            curl_multi_close($master_mh);
            
            // If the list is marked finished or we didn't find any data in the entire batch, break
            if ($list_finished || !$batch_found_new_data) {
                if (self::debug) {
                    echo "UserListModel: Batch finished or end of list reached. Total items: " . count($data) . "\n";
                }
                break;
            }
            
            // Move to the start of the next batch
            $current_offset += (self::LIST_CONCURRENCY_SIZE * self::OFFSET_STEP);
        }

        // --- 2. Item Processing Loop ---
        foreach ($data as &$item) {
            // Apply original item-level logic (Image cleaning/replacement)
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
        }
        unset($item); // Break the reference

        // --- 3. Single Return ---
        if (self::debug) echo "Finished processing all data.\n";
        return $data;
    }
}
