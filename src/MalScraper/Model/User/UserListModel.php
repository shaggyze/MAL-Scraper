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
     * Get user list.
     *
     * @return array
     */
    private function getAllInfo()
    {
      $data = [];
      $offset = 0;
	  while (true) {
		$url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre.'&order='.$this->_order;

		$content = json_decode(file_get_contents(htmlspecialchars_decode($url)), true);
		
		if ($content) {
		  $count = count($content);
		  for ($i = 0; $i < $count; $i++) {
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

		  $offset += 300;
		} else {
		  break;
		}
	  }

        return $data;
    }
}
