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
     * Default constructor.
     *
     * @param string $user
     * @param string $type
     * @param string $style
     * @param string $parserArea
     *
     * @return void
     */
    public function __construct($user, $type, $style)
    {
        $this->_user = $user;
        $this->_type = $type;
        if ($style) {
            $this->_style = $style;
        } else {
            $this->_style = '.data.image a[href^="/{type}/{id}/"]:before{background-image:url("{url}")!important}';
        }
    }

    /**
     * Get user cover.
     *
     * @return string
     */
    public function getAllInfo()
    {
        $list = (new UserList($this->_user, $this->_type, 7))->getAllInfo();

        $cover = '';
        foreach ($list as $c) {
            if ($this->_type == 'anime') {
                $temp = str_replace(['{type}', '{id}', '{url}'], ['anime', [$c['anime_id'], $c['anime_image_path']], $this->_style);
            } else {
                $temp = str_replace(['{type}', '{id}', '{url}'], ['manga', [$c['manga_id'], $c['manga_image_path']], $this->_style);
            }
            $cover .= $temp."\n";
        }

        return $cover;
    }
}
