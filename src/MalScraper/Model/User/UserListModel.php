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

    // --- NEW CONCURRENCY CONSTANTS ---
    const debug = true; // Set to true to see debug echo output
    // Number of user list pages to fetch concurrently (Each page is 300 items)
    const LIST_CONCURRENCY_SIZE = 10; 
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Set a reasonable timeout
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        return $ch;
    }

    /**
     * Fetches all user list pages sequentially using cURL (Concurrency size 1).
     *
     * @return array All list items combined.
     */
    private function fetchAllListPagesConcurrent()
    {
        $all_items = [];
        $offset = 0;

        while (true) {
            $master_mh = curl_multi_init();
            $handles = [];

            // Prepare batch of concurrent requests (size 1)
            $current_offset = $offset;
            $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$current_offset.'&status='.$this->_status.'&genre='.$this->_genre.'&order='.$this->_order;
            
            $ch = $this->initCurlHandle($url);
            curl_multi_add_handle($master_mh, $ch);
            $handles[$current_offset] = $ch;

            // Execute concurrent request (only 1 handle, effectively sequential)
            $running = null;
            do {
                curl_multi_exec($master_mh, $running);
            } while ($running > 0);
            
            $items_in_batch = 0;
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
                        $all_items = array_merge($all_items, $content);
                        $items_in_batch += count($content);
                        
                        // If the page returned fewer than OFFSET_STEP, we are done
                        if (count($content) < self::OFFSET_STEP) {
                             $list_finished = true;
                        }

                    } elseif (self::debug) {
                        echo "Empty or invalid JSON for offset $offset_fetched.\n";
                        $list_finished = true;
                    }
                } else {
                    if (self::debug) {
                        echo "Failed fetch for offset $offset_fetched. HTTP: $http_code, Error: $curl_error\n";
                    }
                    $list_finished = true;
                }
            }

            curl_multi_close($master_mh);
            
            if ($list_finished) {
                break;
            }
            
            // Move offset forward by the page size
            $offset += self::OFFSET_STEP;

            // Safety break if needed
            if ($items_in_batch === 0 && count($all_items) > 0) {
                break;
            }
        }
        
        return $all_items;
    }


    /**
     * Get user list.
     *
     * @return array
     */
    private function getAllInfo()
    {
        $list_data = $this->fetchAllListPagesConcurrent();

        // Apply original item-level logic
        $final_data = [];
        foreach ($list_data as $item) {
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
