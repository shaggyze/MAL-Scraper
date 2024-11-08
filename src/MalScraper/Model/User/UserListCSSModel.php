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
    public function getAllInfo()
    {
      $data = [];
      $offset = 0;
	  while (true) {
		$url = $this->_myAnimeListUrl.'/'.$this->_type.'list/'.$this->_user.'/load.json?offset='.$offset.'&status='.$this->_status.'&genre='.$this->_genre;

		$content = json_decode(file_get_contents(htmlspecialchars_decode($url)), true);

		if ($content) {
		  $count = count($content);
		  for ($i = 0; $i < $count; $i++) {
			if (!empty($content[$i]['anime_id'])) {
			  $subdirectory = get_subdirectory('anime', $content[$i]['anime_id']);
			  $url1 = 'https://shaggyze.website/msa/info?t=anime&id=' . $content[$i]['anime_id'];
			  $url2 = 'https://shaggyze.website/info/anime/' . $subdirectory . '/' . $content[$i]['anime_id'] . '.json';
			  if (!filter_var($url2, FILTER_VALIDATE_URL) || !file_get_contents($url2)) {$url2 = $url1;}
			  $content2 = json_decode(file_get_contents(htmlspecialchars_decode($url2)), true);
			  if ($content[$i]['anime_title_eng'] == "") {$content[$i]['anime_title_eng'] = "N/A";}
			} else {
			  $subdirectory = get_subdirectory('manga', $content[$i]['manga_id']);
			  $url1 = 'https://shaggyze.website/msa/info?t=manga&id=' . $content[$i]['manga_id'];
			  $url2 = 'https://shaggyze.website/info/manga/' . $subdirectory . '/' . $content[$i]['manga_id'] . '.json';
			  if (!filter_var($url2, FILTER_VALIDATE_URL) || !file_get_contents($url2)) {$url2 = $url1;}
			  $content2 = json_decode(file_get_contents(htmlspecialchars_decode($url2)), true);
			  if ($content[$i]['manga_english'] == "") {$content[$i]['manga_english'] = "N/A";}
			}
			if (!empty($content2['data']['synopsis'])) {
			  $synopsis = preg_replace('/[\x0D]/', "", $content2['data']['synopsis']);
			  $synopsis = str_replace(array('nn', "\n", "\t", "\r"), "", $synopsis);
			  $synopsis = str_replace(['"', '—'], ' ', $synopsis);
			  $synopsis = str_replace("'", '', $synopsis);
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
			} else {
				$content[$i]['total_runtime'] = 'N/A';
			}
			if (!empty($content2['data']['premiered'])) {
			  $content[$i]['year'] = str_replace(['Winter ', 'Spring ', 'Summer ', 'Fall '], '', $content2['data']['premiered']);
			} else {
			  if (!empty($content2['data']['aired']['start'])) {
			    $content[$i]['year'] = (int) substr($content2['data']['aired']['start'], -4);
			  } else {
			    if (!empty($content2['data']['published']['start'])) {
			      $content[$i]['year'] = (int) substr($content2['data']['published']['start'], -4);
			    } else {
			      $content[$i]['year'] = 'N/A';
			    }
			  }
			}
			if (!empty($content2['data']['genres'])) {
			  $genres = $content2['data']['genres'];
			  $genreNames = '';
			  if (is_array($genres)) {
			    foreach ($genres as $genre) {
				  $genreNames .= $genre['name'] . ', ';
			    }
			  }
			  $content[$i]['genres'] = rtrim($genreNames, ', ');
			} else {
			  if (!empty($content2['data']['genre'])) {
			    $genres = $content2['data']['genre'];
			    $genreNames = '';
				if (is_array($genres)) {
			      foreach ($genres as $genre) {
				    $genreNames .= $genre['name'] . ', ';
			      }
				}
			    $content[$i]['genres'] = rtrim($genreNames, ', ');
			  } else {
			  $content[$i]['genres'] = 'N/A';
			  }
			}
			if (!empty($content2['data']['themes'])) {
			  $themes = $content2['data']['themes'];
			  $themeNames = '';
			  if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			  }
			  $content[$i]['themes'] = rtrim($themeNames, ', ');
			} else {
			  if (!empty($content2['data']['theme'])) {
			    $themes = $content2['data']['theme'];
			    $themeNames = '';
			    if (is_array($themes)) {
			    foreach ($themes as $theme) {
				  $themeNames .= $theme['name'] . ', ';
			    }
			    }
			    $content[$i]['themes'] = rtrim($themeNames, ', ');
			  } else {
			    $content[$i]['themes'] = 'N/A';
			  }
			}
			if (!empty($content2['data']['demographic'])) {
			  $demographics = $content2['data']['demographic'];
			  $demographicNames = '';
			  if (is_array($demographics)) {
			  foreach ($demographics as $demographic) {
				$demographicNames .= $demographic['name'] . ', ';
			  }
			  }
			  $content[$i]['demographic'] = rtrim($demographicNames, ', ');
			} else {
			  $content[$i]['demographic'] = 'N/A';
			}
			if (!empty($content[$i]['anime_title'])) {
			  $content[$i]['anime_title'] = str_replace(['"', '[', ']'], '', $content[$i]['anime_title']);
			} else {
			  $content[$i]['manga_title'] = str_replace(['"', '[', ']'], '', $content[$i]['manga_title']);
			}
			if (!empty($content[$i]['anime_title_eng'])) {
			  $content[$i]['anime_title_eng'] = str_replace(['"', '[', ']'], '', $content[$i]['anime_title_eng']);
			} else {
			  $content[$i]['manga_english'] = str_replace(['"', '[', ']'], '', $content[$i]['manga_english']);
			}
			if (!empty($content[$i]['num_watched_episodes'])) {
			  if ($content[$i]['anime_num_episodes'] !== 0) {
			    $content[$i]['progress_percent'] = round(($content[$i]['num_watched_episodes'] / $content[$i]['anime_num_episodes']) * 100, 2);
			  } else {
			    $content[$i]['progress_percent'] = 0;
			  }
			} elseif (!empty($content[$i]['num_read_volumes'])) {
			  if ($content[$i]['manga_num_volumes'] !== 0) {
			    $content[$i]['progress_percent'] = round(($content[$i]['num_read_volumes'] / $content[$i]['manga_num_volumes']) * 100, 2);
			  } else {
			    $content[$i]['progress_percent'] = 0;
			  }
			} else {
			    $content[$i]['progress_percent'] = 0;
			}
			if (!empty($content2['data']['rank'])) {
			  $content[$i]['rank'] = $content2['data']['rank'];
			} else {
			  $content[$i]['rank'] = "N/A";
			}
			if (!empty($content2['data']['serialization'])) {
			  $serializations = $content2['data']['serialization'];
			  $serializationNames = '';
			  $serializations = !is_array($serializations) ? [] : $serializations;
			  foreach ($serializations as $serialization) {
				$serializationNames .= $serialization['name'] . ', ';
			  }
			  $content[$i]['serialization'] = rtrim($serializationNames, ', ');
			} else {
			  $content[$i]['serialization'] = 'N/A';
			}
			if (!empty($content['manga_magazines'])) {
			  $mangamagazines = $content['manga_magazines'];
			  $mangamagazineNames = '';
			  $mangamagazines = !is_array($mangamagazines) ? [] : $mangamagazines;
			  foreach ($mangamagazines as $mangamagazine) {
				$mangamagazineNames .= $mangamagazine['name'] . ', ';
			  error_log($mangamagazineNames . ' ' . $mangamagazine['name']);
			  }
			  $content[$i]['manga_magazines'] = rtrim($mangamagazineNames, ', ');
			} else {
			  $content[$i]['manga_magazines'] = 'N/A';
			}
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
