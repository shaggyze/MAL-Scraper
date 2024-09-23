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
		$url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre;

		$content = json_decode(file_get_contents($url), true);

		if ($content) {
		  $count = count($content);
		  for ($i = 0; $i < $count; $i++) {
			if (!empty($content[$i]['anime_id'])) {
			  $subdirectory = get_subdirectory('anime', $content[$i]['anime_id']);
			  $url2 = 'https://shaggyze.website/info/anime/' . $subdirectory . '/' . $content[$i]['anime_id'] . '.json';
			  $content2 = json_decode(file_get_contents($url2), true);
			  if ($content[$i]['anime_title_eng'] == "") {$content[$i]['anime_title_eng'] = "N/A";}
			} else {
			  $subdirectory = get_subdirectory('manga', $content[$i]['manga_id']);
			  $url2 = 'https://shaggyze.website/info/manga/' . $subdirectory . '/' . $content[$i]['manga_id'] . '.json';
			  $content2 = json_decode(file_get_contents($url2), true);
			  if ($content[$i]['manga_english'] == "") {$content[$i]['manga_english'] = "N/A";}
			}
			if (!empty($content2['data']['synopsis'])) {
			  $synopsis = preg_replace('/[\x0D]/', "", $content2['data']['synopsis']);
			  $synopsis = str_replace(array('nn', "\n", "\t", "\r"), "", $synopsis);
			  $synopsis = str_replace('"', '', $synopsis);
			  $synopsis = str_replace("'", "", $synopsis);
			  $content[$i]['synopsis'] = $synopsis;
			} else {
			  $content[$i]['synopsis'] = "N/A";
			}
			if (!empty($content2['data']['duration'])) {
			  $episodes = intval($content2['data']['episodes']);
			  $duration = intval(str_replace(' min. per ep.', '', $content2['data']['duration']));
			  if ($episodes > 0 && $duration > 0) {
			    $content[$i]['total_runtime'] = floor($episodes * $duration / 60) . 'h ' . ($episodes * $duration % 60) . 'm';
			  } else {
				$content[$i]['total_runtime'] = 'N/A';
			  }
			}
			if (!empty($content2['data']['premiered'])) {
			  $content[$i]['year'] = str_replace(['Winter ', 'Spring ', 'Summer ', 'Fall '], '', $content2['data']['premiered']);
			} else {
			  $content[$i]['year'] = (int) explode(" ", $content2->data->aired->start)[2];
			}
			if (!empty($content[$i]['anime_title'])) {
			  $content[$i]['anime_title'] = str_replace(['"', '[', ']'], '', $content[$i]['anime_title']);
			} else {
			  $content[$i]['manga_title'] = str_replace(['"', '[', ']'], '', $content[$i]['manga_title']);
			}
			if (!empty($content[$i]['anime_title_eng'])) {
			  $content[$i]['anime_title_eng'] = str_replace(['"', '[', ']'], '', $content[$i]['anime_title_eng']);
			} else {
			  $content[$i]['manga_title_eng'] = str_replace(['"', '[', ']'], '', $content[$i]['manga_title_eng']);
			}
			if (!empty($content2['data']['rank'])) {
			  $content[$i]['rank'] = $content2['data']['rank'];
			} else {
			  $content[$i]['rank'] = "N/A";
			}
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
		  $content[$i]['\a'] = "-a";
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
