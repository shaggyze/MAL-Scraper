<?php

namespace MalScraper\Model\User;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;
use MalScraper\MalScraper;

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
        $this->_url = $this->_myAnimeListUrl.'/'.$type.'list/'.$user.'?status='.$status.'?genre='.$genre;
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
		$url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre;

		$content = json_decode(file_get_contents($url), true);
		$content2 = new MalScraper();

		if ($content) {
		  $count = count($content);
		  for ($i = 0; $i < $count; $i++) {
			if ($this->_type == 'anime') {
			  $content3 = $content2::getInfo('anime', $content[$i]['anime_id']);
			} else {
			  $content3 = $content2::getInfo('manga', $content[$i]['manga_id']);
		    }
			$content[$i]['synopsis'] = $content3['data']['synopsis'];
			$content[$i]['rank'] = $content3['data']['rank'];
			/*
			/*if (is_array($content[$i]['anime_studios'])) {
			  $content[$i]['anime_studios'] = implode(", ", $content[$i]['anime_studios']);
			} else {
			  $content[$i]['anime_studios'] = "";
			}
			if (is_array($content[$i]['anime_licensors'])) {
			  $content[$i]['anime_licensors'] = implode(", ", $content[$i]['anime_licensors']);
			} else {
			  $content[$i]['anime_licensors'] = "";
			}
			if (is_array($content[$i]['anime_season'])) {
			  $content[$i]['anime_season'] = implode(", ", $content[$i]['anime_season']);
			} else {
			  $content[$i]['anime_season'] = "";
			}
			if (is_array($content[$i]['manga_magazines'])) {
			  $content[$i]['manga_magazines'] = implode(", ", $content[$i]['manga_magazines']);
			} else {
			  $content[$i]['manga_magazines'] = "";
			}
			if (is_array($content[$i]['genres'])) {
			  $content[$i]['genres'] = implode(", ", $content[$i]['genres']);
			} else {
			  $content[$i]['genres'] = "";
			}
			if (is_array($content[$i]['demographics'])) {
			  $content[$i]['demographics'] = implode(", ", $content[$i]['demographics']);
			} else {
			  $content[$i]['demographics'] = "";
			}*/
			if (!empty($content[$i]['anime_image_path'])) {
			  $content[$i]['anime_image_path'] = Helper::imageUrlCleaner($content[$i]['anime_image_path']);
			} else {
			  $content[$i]['manga_image_path'] = Helper::imageUrlCleaner($content[$i]['manga_image_path']);
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
