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
        debug_log("Starting getList (CSS) for user: {$this->_user}, type: {$this->_type}");
        
        // --- SCARAPING LOGIC (Synchronous) ---
        $data = [];
        $offset = 0;
        $ch = curl_init();
        
        while (true) {
            $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre;

            debug_log("Fetching offset: {$offset}, URL: {$url}");
            
            curl_setopt($ch, CURLOPT_URL, htmlspecialchars_decode($url));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $headers = [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            if ($http_code !== 200 || $curl_error) {
                 debug_log("cURL Error at offset {$offset}: HTTP {$http_code}, Error: {$curl_error}");
                 break; // Stop fetching on error
            }

            $content = json_decode($response, true);
            
            if ($content) {
                $count = count($content);
                debug_log("Successful fetch for offset {$offset}. Items found: {$count}");

                $te_cwr = 0;
                $te_c = 0;
                $te_oh = 0;
                $te_d = 0;
                $te_ptw = 0;
                $te_all = 0;

                for ($i = 0; $i < $count; $i++) {
                    // --- SAFETY CHECK: Ensure keys exist before accessing ---
                    if (!isset($content[$i]['end_dates'])) {
                        $content[$i]['end_dates'] = null;
                    }
                    if ($this->_type == 'anime' && !isset($content[$i]['anime_english'])) {
                         $content[$i]['anime_english'] = null;
                    }
                    if ($this->_type == 'manga' && !isset($content[$i]['manga_english'])) {
                         $content[$i]['manga_english'] = null;
                    }
                    
                    if ($content[$i]['end_dates'] === null) {
                        $content[$i]['end_dates'] = 'N/A';
                    }
                    
                    // --- NULL SAFETY FIX ---
                    // Use null coalescing operator (?? '') to ensure image paths are strings, not null.
                    if (!empty($content[$i]['anime_image_path'])) {
                        $content[$i]['anime_image_path'] = Helper::imageUrlCleaner($content[$i]['anime_image_path'] ?? '');
                    } else {
                        $content[$i]['manga_image_path'] = Helper::imageUrlCleaner($content[$i]['manga_image_path'] ?? '');
                    }
                    if (!empty($content[$i]['anime_id'])) {
                        $content[$i]['anime_image_path'] = Helper::imageUrlReplace($content[$i]['anime_id'], 'anime', $content[$i]['anime_image_path'] ?? '', $this->_user);
                    } else {
                        $content[$i]['manga_image_path'] = Helper::imageUrlReplace($content[$i]['manga_id'], 'manga', $content[$i]['manga_image_path'] ?? '', $this->_user);
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
                        $te_ptw += 1;
                        $te_all += 1;
                    }
                    
                    if ($this->_type == 'anime') {
                        $te_ep = $content[$i]['anime_num_episodes'];
                        $te_my_ep = $content[$i]['num_watched_episodes'];
                    } else {
                        $te_ep = $content[$i]['manga_num_chapters'];
                        $te_my_ep = $content[$i]['num_read_chapters'];
                    }

                    if ($te_ep == 0) {
                        $te_ep = 1;
                    }
                    $content[$i]['progress'] = (int) (($te_my_ep / $te_ep) * 100);
                    
                    $data[] = $content[$i];
                }

                if (count($content) < 300) {
                    debug_log("Fetch finished (less than 300 items) at offset: {$offset}");
                    break;
                }
                $offset += 300;
            } else {
                debug_log("Fetch finished (no content or invalid JSON) at offset: {$offset}");
                break;
            }
        }

        curl_close($ch);
        debug_log("getList (CSS) completed. Total items: " . count($data));
        return $data;
    }
}
