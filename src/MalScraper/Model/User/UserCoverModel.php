<?php

namespace MalScraper\Model\User;

use MalScraper\Model\User\UserListModel as UserList;

/**
 * UserCoverModel class.
 */
class UserCoverModel
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
     * CSS style.
     *
     * @var string
     */
    private $_style;

    /**
     * CSS genre.
     *
     * @var string
     */
    private $_genre;

    /**
     * Default constructor.
     *
     * @param string $user
     * @param string $type
     * @param string $style
     * @param string $genre
     * @param string $parserArea
     *
     * @return void
     */
    public function __construct($user, $type="anime", $style=null, $genre=null)
    {
        $this->_user = $user;
        $this->_type = $type;
		$this->_genre = $genre;
        if ($style) {
            $this->_style = $style;
        } else {
			if ($this->_type == 'anime') {
				$this->_style = '.data.image a[href^="/{type}/{anime_id}/"]:before{background-image:url("{anime_image_path}")}';
			} else {
				$this->_style = '.data.image a[href^="/{type}/{manga_id}/"]:before{background-image:url("{manga_image_path}")}';
			}
		}
    }

    /**
     * Get user cover.
     *
     * @return string
     */
    public function getAllInfo()
    {
        $list = (new UserList($this->_user, $this->_type, 7, $this->_genre))->getAllInfo();

        /*$cover = 'No UserList';*/
		if (is_array($list)) {
			$cover = '';
			foreach ($list as $c) {
				if ($this->_type == 'anime') {
					$temp = str_replace(['{type}', '{anime_id}', '{anime_image_path}'], ['anime', $c['anime_id'], $c['anime_image_path']], $this->_style);
				} else {
					$temp = str_replace(['{type}', '{manga_id}', '{manga_image_path}'], ['manga', $c['manga_id'], $c['manga_image_path']], $this->_style);
				}
			$cover .= $temp."\n";
			}
		}
        return $cover;
    }
}
