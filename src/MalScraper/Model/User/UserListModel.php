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
		if ($content['errors']['message'] == 'invalid request'); { return }
		if ($content) {
		  $count = count($content);
		  for ($i = 0; $i < $count; $i++) {
			/*if (!empty($content[$i]['anime_id'])) {
			  $url2 = 'https://shaggyze.website/msa/info?t=anime&id=' . $content[$i]['anime_id'];
			  $content2 = json_decode(file_get_contents($url2), true);
			  $content[$i]['synopsis'] = $content2['data']['synopsis'];
			} else {
			  $url2 = 'https://shaggyze.website/msa/info?t=manga&id=' . $content[$i]['manga_id'];
			  $content2 = json_decode(file_get_contents($url2), true);
			  $content[$i]['synopsis'] = $content2['data']['synopsis'];
			}
			if (!empty($content[$i]['anime_id'])) {
			  $url2 = 'https://shaggyze.website/msa/info?t=anime&id=' . $content[$i]['anime_id'];
			  $content2 = json_decode(file_get_contents($url2), true);
			  $content[$i]['rank'] = $content2['data']['rank'];
			} else {
			  $url2 = 'https://shaggyze.website/msa/info?t=manga&id=' . $content[$i]['manga_id'];
			  $content2 = json_decode(file_get_contents($url2), true);
			  $content[$i]['rank'] = $content2['data']['rank'];
			}*/
			if (!empty($content[$i]['anime_image_path'])) {
			  $content[$i]['anime_image_path'] = Helper::imageUrlCleaner($content[$i]['anime_image_path']);
			} else {
			  $content[$i]['manga_image_path'] = Helper::imageUrlCleaner($content[$i]['manga_image_path']);
			}
			if (!empty($content[$i]['anime_id'])) {
			  $content[$i]['anime_image_path'] = Helper::imageUrlReplace($content[$i]['anime_id'], 'anime', $content[$i]['anime_image_path']);
			} else {
			  $content[$i]['manga_image_path'] = Helper::imageUrlReplace($content[$i]['manga_id'], 'manga', $content[$i]['manga_image_path']);
			}
			/*if (!empty($content[$i]['genres']) && is_array($content[$i]['genres'])) {
			  $content[$i]['genres'] = implode(", ", $content[$i]['genres']);
			} elseif (!empty($content[$i]['genres'])) {
			} else {
			  $content[$i]['genres'] = "";
			}*/
            if ($this->_type == 'anime') {
			  if (!empty($content[$i]['anime_studios']) && is_array($content[$i]['anime_studios'])) {
			    /*$content[$i]['anime_studios'] = implode(", ", $content[$i]['anime_studios']);*/
			  } else {
			    $content[$i]['anime_studios'] = "";
			  }
			  if (!empty($content[$i]['anime_licensors']) && is_array($content[$i]['anime_licensors'])) {
			    /*$content[$i]['anime_licensors'] = implode(", ", $content[$i]['anime_licensors']);*/
			  } else {
			    $content[$i]['anime_licensors'] = "";
			  }
			  if (!empty($content[$i]['anime_season']) && is_array($content[$i]['anime_season'])) {
			    /*$content[$i]['anime_season'] = implode(", ", $content[$i]['anime_season']);*/
			  } else {
			    $content[$i]['anime_season'] = "";
			  }
			} else {
			  if (!empty($content[$i]['manga_magazines']) && is_array($content[$i]['manga_magazines'])) {
			    /*$content[$i]['manga_magazines'] = implode(", ", $content[$i]['manga_magazines']);*/
			  } else {
			    $content[$i]['manga_magazines'] = "";
			  }
			  if (!empty($content[$i]['demographics']) && is_array($content[$i]['demographics'])) {
			    /*$content[$i]['demographics'] = implode(", ", $content[$i]['demographics']);*/
			  } else {
			    $content[$i]['demographics'] = "";
			  }
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
