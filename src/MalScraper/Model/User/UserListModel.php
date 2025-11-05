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
    const LIST_CONCURRENCY_SIZE = 10; 
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
     * Get subdirectory number for image URL replacement.
     *
     * @param string $type
     * @param int $id
     *
     * @return string
     */
    public function get_subdirectory($type, $id)
	{
        $subdirectory_number = floor($id / 10000);
        $subdirectory_path = '../info/' . $type . '/' . $subdirectory_number . '/';

        return strval($subdirectory_number);
    }

    /**
     * Get user list.
     *
     * @return array
     */
    public function getAllInfo()
    {
        // Change concurrency size dynamically if fetching ALL users.
        if ($this->_user == '_All_') {
            // Significantly higher concurrency for the massive global list fetch
            $concurrency_size = 75; 
            if (self::debug) echo "UserListModel: Setting concurrency to $concurrency_size for _All_ user.\n";
        } else {
            $concurrency_size = self::LIST_CONCURRENCY_SIZE;
        }

        // --- 1. Concurrent Fetching Loop ---
        $data = []; // The final list array
        $current_offset = 0;
        $list_finished = false;
        
        while (!$list_finished) {
            $batch_found_new_data = false;
            $multi_handler = curl_multi_init();
            $curl_handles = [];

            // Prepare the batch of cURL handles
            for ($i = 0; $i < $concurrency_size; $i++) {
                $offset = $current_offset + ($i * self::OFFSET_STEP);
                $url = $this->_myAnimeListUrl . '/' . $this->_type . 'list/' . $this->_user . '/load.json?offset=' . $offset . '&status=' . $this->_status . '&genre=' . $this->_genre . '&order=' . $this->_order;

                if (self::debug) echo "UserListModel: Preparing cURL for offset: $offset\n";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, htmlspecialchars_decode($url));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                curl_multi_add_handle($multi_handler, $ch);
                // Store handle with offset as key to track which page returned which content
                $curl_handles[(string)$offset] = $ch; 
            }

            // Execute the batch concurrently
            $running = null;
            do {
                curl_multi_exec($multi_handler, $running);
                // Non-blocking loop control
                if ($running > 0) {
                    curl_multi_select($multi_handler); 
                }
            } while ($running > 0);

            // Process results from the batch
            foreach ($curl_handles as $offset_fetched => $ch) {
                $result = curl_multi_getcontent($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($multi_handler, $ch);
                curl_close($ch);

                if ($http_code === 200 && $result) {
                    $content = json_decode($result, true);

                    if ($content && is_array($content) && count($content) > 0) {
                        if (self::debug) echo "UserListModel: Successfully fetched " . count($content) . " items from offset $offset_fetched.\n";
                        $data = array_merge($data, $content);
                        $batch_found_new_data = true;
                    } else {
                        // If we fetched an empty array, the list has ended.
                        if ($offset_fetched == $current_offset) {
                           $list_finished = true;
                        }
                    }
                } else {
                    if (self::debug) echo "UserListModel: Failed to fetch offset $offset_fetched (HTTP: $http_code).\n";
                    // If the first page of the batch failed, we assume the list has ended
                    if ($offset_fetched == $current_offset) {
                       $list_finished = true;
                    }
                }
            }
            curl_multi_close($multi_handler);
            
            // If the list is marked finished or we didn't find any data in the entire batch, break
            if ($list_finished || !$batch_found_new_data) {
                if (self::debug) {
                    echo "UserListModel: Batch finished or end of list reached. Total items: " . count($data) . "\n";
                }
                break;
            }
            
            // Move to the start of the next batch
            $current_offset += ($concurrency_size * self::OFFSET_STEP);
        }

        // --- 2. Item Processing Loop (Cleanup/Image Replacement) ---
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

        // --- 3. Final PHP Array Return ---
        if (self::debug) echo "Finished processing all data.\n";

        
        // Return the final PHP array, allowing the caller (index.php) to encode it.
        return $data;
    }
}