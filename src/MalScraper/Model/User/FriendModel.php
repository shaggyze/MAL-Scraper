<?php

namespace MalScraper\Model\User;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * FriendModel class.
 */
class FriendModel extends MainModel
{
    /**
     * Username.
     *
     * @var string
     */
    private $_user;

    /**
     * Default constructor.
     *
     * @param string $user
     * @param string $parserArea
     *
     * @return void
     */
    public function __construct($user, $page = 1, $parserArea = '#content')
    {
        $this->_user = $user;
        $this->_url = $this->_myAnimeListUrl.'/profile/'.$user.'/friends?p='.$page;
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
     * Get username.
     *
     * @return string
     */
    private function getUsername()
    {
        return $this->_user;
    }

    /**
     * Get friend image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $f
     *
     * @return string
     */
    private function getImage($f)
    {
        return Helper::imageUrlCleaner($f->find('a img', 0)->getAttribute('data-src'));
    }

    /**
     * Get friend name.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $f
     *
     * @return string
     */
    private function getName($f)
    {
        $name_temp = $f->find('a', 0)->href;
        $name_temp = explode('/', $name_temp);

        return $name_temp[4];
    }

    /**
     * Get friend last online.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $f
     *
     * @return string
     */
    private function getLastOnline($f)
    {
        $last_online = $f->find('.fn-grey2', 0);

        return trim($last_online->plaintext);
    }

    /**
     * Get friend since.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $f
     *
     * @return string
     */
    private function getFriendSince($f)
    {
        $friend_since = $f->find('.fn-grey2', 0)->next_sibling();
        $friend_since = str_replace('Friends since', '', $friend_since->plaintext);

        return trim($friend_since);
    }

    /**
     * Get user friend list.
     *
     * @return array
     */
    private function getAllInfo()
    {
        $friend = [];
        $parent_area = $this->_parser->find('mt4 mb8', 0);
		if ($parent_area) {$parent_area = $parent_area->plaintext;}
		$friend_area = $this->_parser->find('.boxlist-container', 0);
		error_log($parent_area);
        if ($friend_area) {
			if ($parent_area == 'Next') {
				$friend['has_next_page'] = 'true';
			} else {
				$friend['has_next_page'] = 'false';
			}
            foreach ($friend_area->find('.boxlist') as $f) {
				$f_dump = [];
                $g = $f->find('.di-tc', 0);
                $h = $f->find('.data', 0);
				
                $f_dump['image'] = $this->getImage($g);
                $f_dump['username'] = $this->getName($h);
				$f_dump['url'] = 'https://myanimelist.net/profile/'.$this->getName($h);
                $f_dump['last_online'] = $this->getLastOnline($h);
                $f_dump['friend_since'] = $this->getFriendSince($h);

                $friend[] = $f_dump;
            }
        }

        return $friend;
    }
}
