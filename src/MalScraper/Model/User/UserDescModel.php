<?php

namespace MalScraper\Model\User;

use MalScraper\Model\User\UserListModel as UserList;

/**
 * UserDescModel class.
 */
class UserDescModel
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
            $this->_style = 'body[data-work="{type}"] #tags-{id}:after {font-family: Finger Paint; content: "{desc}";} .list-table .list-table-data:hover .data.title .link[href^="/{type}/{id}/"]::before { content:"I have not yet reviewed {title}."; }';
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
                $temp = str_replace(['{id}', '{desc}', '{title}'], [$c['anime_id'], $c['anime_desc'], $c['anime_title']], $this->_style);
            } else {
                $temp = str_replace(['{id}', '{desc}', '{title}'], [$c['manga_id'], $c['manga_desc'], $c['mange_title']], $this->_style);
            }
            $cover .= $temp."\n";
        }

        return $cover;
    }
}
