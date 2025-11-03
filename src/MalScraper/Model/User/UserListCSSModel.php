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
     * Get user list. Always scrapes.
     *
     * @return array
     */
    public function getList()
    {
        // --- SCARAPING LOGIC (Synchronous) ---
        $data = [];
        $offset = 0;
        
        // Initialize cURL handle ONCE before the loop
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        while (true) {
            $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre;

            // Set URL for the current iteration
            curl_setopt($ch, CURLOPT_URL, htmlspecialchars_decode($url));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            if ($http_code !== 200 || $curl_error) {
                 break; // Stop fetching on error
            }

            $content = json_decode($response, true);
            
            if ($content) {
                $count = count($content);

                $te_cwr = 0;
                $te_c = 0;
                $te_oh = 0;
                $te_d = 0;
                $te_ptw = 0;
                $te_all = 0;

                for ($i = 0; $i < $count; $i++) {
                    
                    $item_ref = &$content[$i];
                    
                    // FIX: Ensure 'end_dates' exists (using null coalescing)
                    $item_ref['end_dates'] = $item_ref['end_dates'] ?? 'N/A';
                    
                    // FIX: Ensure 'manga_english' is set to null if missing to prevent "Undefined array key" warnings
                    if ($this->_type == 'manga') {
                        $item_ref['manga_english'] = $item_ref['manga_english'] ?? null;
                    }

                    if (!empty($item_ref['anime_title'])) {
                        $item_ref['anime_title'] = str_replace(['"', '[', ']'], '', $item_ref['anime_title']);
                    } else {
                        $item_ref['manga_title'] = str_replace(['"', '[', ']'], '', $item_ref['manga_title']);
                    }
                    
                    if (!empty($item_ref['anime_title_eng'])) {
                        $item_ref['anime_title_eng'] = str_replace(['"', '[', ']'], '', $item_ref['anime_title_eng']);
                    } else {
                        // This is now safe because we set it to null above if it was missing
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
                    
                    $item_ref['\a'] = "-a"; // Original field manipulation

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
                    
                    $data[] = $item_ref;
                }
                
                // Update final total entries count after the loop completes
                // Note: The loop below modifies the array entries after they've been added to $data, 
                // which is why the previous loop uses $item_ref = &$content[$i] and array_merge isn't used.

                foreach ($data as &$item) {
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


                if (count($content) < self::OFFSET_STEP) {
                    break;
                }
                $offset += self::OFFSET_STEP;
            } else {
                break;
            }
        }

        // Close cURL handle ONCE after the loop
        curl_close($ch); 
        
        return $data;
    }
}
