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
    
    // --- UTILITY FUNCTIONS ---
    
    /**
     * Check if status is a number.
     *
     * @return bool
     */
    public function isStatusNumeric()
    {
        return is_numeric($this->_status);
    }

    /**
     * Get subdirectory number for file path.
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
        // Use a dynamic concurrency size if the list is being scraped in full
        $concurrency_size = self::LIST_CONCURRENCY_SIZE;

        // --- Concurrency Override for _All_ List ---
        // Since const cannot be changed, we use a local variable based on the constant
        // for the default, and override if the condition is met.
        if ($this->_user == '_All_') {
            $concurrency_size = 75; // Set higher concurrency for the _All_ special user
            if (self::debug) echo "UserListModel: Setting concurrency to 75 for _All_ user.\n";
        }
        
        $data = [];
        $current_offset = 0;
        $all_list_content = [];
        $list_finished = false;

        // --- 1. Concurrent Fetching Loop ---
        while (!$list_finished) {
            $multi_handler = curl_multi_init();
            $curl_handles = [];
            $batch_found_new_data = false;

            if (self::debug) echo "UserListModel: Starting batch at offset {$current_offset} with concurrency {$concurrency_size}.\n";

            // Prepare the cURL handles for the current batch
            for ($i = 0; $i < $concurrency_size; $i++) {
                $offset_to_fetch = $current_offset + ($i * self::OFFSET_STEP);
                $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset_to_fetch.'&status='.$this->_status.'&genre='.$this->_genre.'&order='.$this->_order;

                $curl_handles[$offset_to_fetch] = curl_init();
                curl_setopt($curl_handles[$offset_to_fetch], CURLOPT_URL, htmlspecialchars_decode($url));
                curl_setopt($curl_handles[$offset_to_fetch], CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl_handles[$offset_to_fetch], CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($curl_handles[$offset_to_fetch], CURLOPT_TIMEOUT, 30);
                curl_multi_add_handle($multi_handler, $curl_handles[$offset_to_fetch]);
                
                if (self::debug) echo "UserListModel: Request added for offset {$offset_to_fetch}.\n";
            }

            // Execute all requests in the batch concurrently
            do {
                $status = curl_multi_exec($multi_handler, $running);
                if ($running) {
                    curl_multi_select($multi_handler, 1.0); // Wait for activity
                }
            } while ($running > 0 && $status === CURLM_OK);

            // Process results from the batch
            foreach ($curl_handles as $offset_fetched => $handle) {
                $content = curl_multi_getcontent($handle);
                $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($multi_handler, $handle);

                if ($http_code == 200 && $content) {
                    $json_content = json_decode($content, true);

                    if ($json_content && count($json_content) > 0) {
                        if (self::debug) echo "UserListModel: Successfully fetched and parsed {$offset_fetched} with " . count($json_content) . " items.\n";
                        $all_list_content = array_merge($all_list_content, $json_content);
                        $batch_found_new_data = true;
                    } else {
                        // Empty response means the list has ended
                        if ($offset_fetched == $current_offset) {
                            $list_finished = true;
                            if (self::debug) echo "UserListModel: Empty response at first page of batch {$current_offset}. List finished.\n";
                        }
                    }
                } else {
                    if (self::debug) echo "UserListModel: Request failed for offset {$offset_fetched} (HTTP: {$http_code}).\n";
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
                    echo "UserListModel: Batch finished or end of list reached. Total list pages fetched.\n";
                }
                break;
            }
            
            // Move to the start of the next batch
            $current_offset += ($concurrency_size * self::OFFSET_STEP);
        }

        // --- 2. Item Processing Loop ---
        foreach ($all_list_content as $item) {
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
            $data[] = $item;
        }

        // --- 3. Single Return ---
        if (self::debug) echo "Finished processing all data.\n";
        return $data;
    }
}