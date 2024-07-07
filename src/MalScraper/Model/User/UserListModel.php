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
     * @var string
     */
    private $_status;

    /**
     * Default constructor.
     *
     * @param string $user
     * @param string $type
     * @param string $status
     * @param string $parserArea
     *
     * @return void
     */
    public function __construct($user, $type, $status, $parserArea = '#content')
    {
        $this->_user = $user;
        $this->_type = $type;
        $this->_status = $status;
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
     * Get user list.
     *
     * @return array
     */
    private function getAllInfo()
    {
        $data = [];
        $offset = 0;
        while (true) {
            $url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status;
			
            $content = json_decode(file_get_contents($url), true);
			
            if ($content) {
                /**
                $count = count($content);
                for ($i = 0; $i < $count; $i++) {
					$url2 = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status;
					$content2 = json_decode(file_get_contents($url2), true);
                    if (!empty($content[$i]['anime_title'])) {
                        $content[$i]['anime_desc'] = $content2[$i]['anime_desc']);
                    } else {
                        $content[$i]['manga_desc'] = $content2[$i]['manga_desc']);
                    }
                }
                */
                $data = array_merge($data, $content);

                $offset += 300;
            } else {
                break;
            }
        }

        return $data;
    }
}
