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

    // --- NEW CONCURRENCY CONSTANTS ---
    const debug = true;
    // Number of user list pages to fetch concurrently (Each page is 300 items)
    const LIST_CONCURRENCY_SIZE = 5; 
    // Number of individual item metadata URLs to fetch concurrently. 
    // This is the main speed bottleneck, set higher for max speed.
    const ITEM_CONCURRENCY_SIZE = 50; 
    // Max entries per page returned by MAL load.json endpoint
    const OFFSET_STEP = 300; 

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

        // Only recognize the primary scraping method
        if ($method === 'getList') {
            return call_user_func_array([$this, $method], $arguments);
        }

        return call_user_func_array([$this, $method], $arguments);
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
     * Fetches a single page of the user list.
     *
     * @param int $offset The offset for the load.json endpoint.
     * @return array|null Decoded JSON content or null on failure.
     */
    private function fetchListPage($offset)
    {
        $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre;
        
        $ch = $this->initCurlHandle($url);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code !== 200 || $curl_error || !$response) {
            if (self::debug) {
                echo "Failed to fetch list page at offset $offset. HTTP: $http_code, Error: $curl_error\n";
            }
            return null;
        }

        $content = json_decode($response, true);
        return is_array($content) ? $content : null;
    }

    /**
     * Fetches all user list pages concurrently.
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
            $max_offset = 0;

            // Prepare batch of concurrent requests
            for ($i = 0; $i < self::LIST_CONCURRENCY_SIZE; $i++) {
                $current_offset = $offset + ($i * self::OFFSET_STEP);
                $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$current_offset.'&status='.$this->_status.'&genre='.$this->_genre;
                
                $ch = $this->initCurlHandle($url);
                curl_multi_add_handle($master_mh, $ch);
                $handles[$current_offset] = $ch;
                $max_offset = $current_offset;
            }

            // Execute concurrent requests
            $running = null;
            do {
                curl_multi_exec($master_mh, $running);
            } while ($running > 0);
            
            $batch_size = 0;
            $items_in_batch = 0;

            // Process results
            foreach ($handles as $current_offset => $ch) {
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
                        $batch_size = max($batch_size, count($content));
                    } elseif (self::debug) {
                        echo "Empty or invalid JSON for offset $current_offset.\n";
                    }
                } elseif (self::debug) {
                    echo "Failed fetch for offset $current_offset. HTTP: $http_code, Error: $curl_error\n";
                }
            }

            curl_multi_close($master_mh);
            
            // If the last request in the batch returned fewer than OFFSET_STEP, we are done.
            if ($batch_size < self::OFFSET_STEP) {
                break;
            }

            // Move offset forward by the size of the batch we attempted
            $offset = $max_offset + self::OFFSET_STEP;

            // Avoid infinite loops if batch size is 0 but master loop condition is still met
            if ($items_in_batch === 0 && count($all_items) > 0) {
                break;
            }
        }
        
        return $all_items;
    }

    /**
     * Helper to fetch item metadata concurrently.
     *
     * @param array $urls Array of URLs to fetch.
     * @return array Array of responses indexed by original URL.
     */
    private function fetchMetadataConcurrent(array $urls)
    {
        if (empty($urls)) {
            return [];
        }

        $all_responses = [];
        $url_chunks = array_chunk($urls, self::ITEM_CONCURRENCY_SIZE, true);

        foreach ($url_chunks as $chunk) {
            $master_mh = curl_multi_init();
            $handles = [];

            foreach ($chunk as $url_key => $url) {
                $ch = $this->initCurlHandle($url);
                curl_multi_add_handle($master_mh, $ch);
                $handles[$url_key] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($master_mh, $running);
            } while ($running > 0);

            foreach ($handles as $url_key => $ch) {
                $response = curl_multi_getcontent($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($master_mh, $ch);
                curl_close($ch);

                if ($http_code === 200 && $response) {
                    $all_responses[$url_key] = $response;
                } elseif (self::debug) {
                    echo "Metadata fetch failed for URL index $url_key. HTTP: $http_code\n";
                }
            }
            curl_multi_close($master_mh);
        }

        return $all_responses;
    }

    /**
     * Gets metadata URL for a single item.
     *
     * @param array $item_ref Reference to the item data array.
     * @return string|null The URL or null.
     */
    private function getMetadataUrl(array &$item_ref)
    {
        $id = $item_ref['anime_id'] ?? $item_ref['manga_id'] ?? null;
        if (!$id) {
            return null;
        }
        $type = $this->_type;
        return "https://myanimelist.net/$type/$id/a_dummy_title_for_scraping";
    }

    /**
     * Parses the HTML content for item metadata (Synopsis, Rank, etc.).
     *
     * @param string $html HTML content of the item page.
     * @return array Extracted data.
     */
    private function parseItemMetadata($html)
    {
        $data = [];
        // Use DOMDocument and DOMXPath for robust parsing
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html); // Suppress HTML parsing warnings
        $xpath = new \DOMXPath($dom);

        // --- Extract Synopsis ---
        $synopsis_node = $xpath->query("//p[@itemprop='description']");
        if ($synopsis_node->length > 0) {
            $data['synopsis'] = trim($synopsis_node->item(0)->textContent);
        } else {
            // Alternative location for older pages or different layouts
            $synopsis_node = $xpath->query("//td[text()='Synopsis:']");
            if ($synopsis_node->length > 0 && $synopsis_node->item(0)->nextSibling) {
                $data['synopsis'] = trim($synopsis_node->item(0)->nextSibling->textContent);
            }
        }

        // --- Extract Rank ---
        // Find the "Ranked" text node and get its sibling content
        $rank_node = $xpath->query("//span[text()='Ranked:']");
        if ($rank_node->length > 0 && $rank_node->item(0)->parentNode) {
            $parent = $rank_node->item(0)->parentNode;
            $rank_value = trim(str_replace('Ranked:', '', $parent->textContent));
            $data['rank'] = preg_replace('/[^0-9\s]/', '', $rank_value); // Clean rank number
        }

        // --- Extract other common data points (Example) ---
        $data['genres'] = [];
        $genre_nodes = $xpath->query("//span[@itemprop='genre']");
        foreach ($genre_nodes as $node) {
            $data['genres'][] = $node->textContent;
        }

        return $data;
    }

    /**
     * Get user list, fetches all data and then metadata concurrently.
     *
     * @return array
     */
    public function getList()
    {
        $list_data = $this->fetchAllListPagesConcurrent();
        
        if (empty($list_data)) {
            return [];
        }

        $metadata_urls = [];
        foreach ($list_data as $index => &$item) {
            $url = $this->getMetadataUrl($item);
            if ($url) {
                // Use the list index to map back the response to the correct item
                $metadata_urls[$index] = $url; 
            }
        }
        unset($item); // Break the reference

        $metadata_responses = $this->fetchMetadataConcurrent($metadata_urls);

        // Process combined list and metadata
        $te_cwr = 0;
        $te_c = 0;
        $te_oh = 0;
        $te_d = 0;
        $te_ptw = 0;
        $te_all = 0;
        
        foreach ($list_data as $i => &$item_ref) {
            // Apply Metadata if available
            if (isset($metadata_responses[$i])) {
                $parsed_metadata = $this->parseItemMetadata($metadata_responses[$i]);
                $item_ref = array_merge($item_ref, $parsed_metadata);
            } else {
                 if (self::debug) {
                    echo "Missing metadata for index $i.\n";
                }
            }

            // --- Original Logic Applied ---
            
            // FIX 1: Ensure 'end_dates' exists (using null coalescing)
            $item_ref['end_dates'] = $item_ref['end_dates'] ?? 'N/A';
            
            // Title cleanup logic
            if (!empty($item_ref['anime_title'])) {
                $item_ref['anime_title'] = str_replace(['"', '[', ']'], '', $item_ref['anime_title']);
            } else {
                $item_ref['manga_title'] = str_replace(['"', '[', ']'], '', $item_ref['manga_title']);
            }
            
            // English Title cleanup logic (Where the error occurs)
            if (!empty($item_ref['anime_title_eng'])) {
                $item_ref['anime_title_eng'] = str_replace(['"', '[', ']'], '', $item_ref['anime_title_eng']);
            } else {
                // *** FIX APPLIED HERE ***
                // Using the null coalescing operator (?? '') handles both the 'Undefined array key' 
                // and the 'Passing null' deprecation by ensuring str_replace always receives a string.
                $item_ref['manga_english'] = str_replace(['"', '[', ']'], '', $item_ref['manga_english'] ?? ''); 
            }
            
            // Image Path cleanup logic
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
            
            $item_ref['\a'] = "-a"; // Original field manipulation

            // Status counting logic (Preserving user's logic)
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
                $te_ptw += 1;
                $te_all += 1;
            }
        }
        unset($item_ref); // Break the last reference

        // Update total entries count and progress percentage
        foreach ($list_data as &$item) {
            $item['total_entries_cwr'] = $te_cwr;
            $item['total_entries_c'] = $te_c;
            $item['total_entries_oh'] = $te_oh;
            $item['total_entries_d'] = $te_d;
            $item['total_entries_ptw'] = $te_ptw;
            $item['total_entries_all'] = $te_all;

            if ($this->_type == 'anime') {
                $te_ep = $item['anime_num_episodes'] ?? 1;
                $te_my_ep = $item['num_watched_episodes'] ?? 0;
            } else {
                $te_ep = $item['manga_num_chapters'] ?? 1;
                $te_my_ep = $item['num_read_chapters'] ?? 0;
            }

            // Protect against division by zero 
            $te_ep_safe = $te_ep > 0 ? $te_ep : 1;
            
            $item['progress'] = (int) (($te_my_ep / $te_ep_safe) * 100);
        }
        unset($item); // Break the reference

        return $list_data;
    }
}
