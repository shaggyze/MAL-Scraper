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
    public function __construct($user, $type, $style, $genre)
    {
        $this->_user = $user;
        $this->_type = $type;
		$this->_genre = $genre;
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
        $list = (new UserList($this->_user, $this->_type, 7, $this->_genre))->getAllInfo();

        $cover = '';
        foreach ($list as $c) {
            if ($this->_type == 'anime') {
                $temp = str_replace(['{type}', '{status}', '{score}', '{tags}', '{is_rewatching}', '{num_watched_episodes}', '{created_at}', '{updated_at}', '{anime_title}', '{anime_title_eng}', '{anime_num_episodes}', '{anime_airing_status}', '{anime_id}', '{anime_studios}', '{anime_season}', '{anime_total_members}', '{anime_total_scores}', '{anime_score_val}', '{anime_score_diff}', '{anime_popularity}', '{has_episode_video}', '{has_promotion_video}', '{has_video}', '{video_url}', '{genres}', '{title_localized}', '{anime_url}', '{anime_image_path}', '{is_added_to_list}', '{anime_media_type_string}', '{anime_mpaa_rating_string}', '{start_date_string}', '{finish_date_string}', '{anime_start_date_string}', '{anime_end_date_string}', '{days_string}', '{storage_string}', '{priority_string}', '{notes}', '{editable_notes}'], ['anime', $c['status'], $c['score'], $c['tags'], $c['is_rewatching'], $c['num_watched_episodes'], $c['created_at'], $c['updated_at'], $c['anime_title'], $c['anime_title_eng'], $c['anime_num_episodes'], $c['anime_airing_status'], $c['anime_id'], $c['anime_studios'], $c['anime_season'], $c['anime_total_members'], $c['anime_total_scores'], $c['anime_score_val'], $c['anime_score_diff'], $c['anime_popularity'], $c['has_episode_video'], $c['has_promotion_video'], $c['has_video'], $c['video_url'], $c['genres'], $c['title_localized'], $c['anime_url'], $c['anime_image_path'], $c['is_added_to_list'], $c['anime_media_type_string'], $c['anime_mpaa_rating_string'], $c['start_date_string'], $c['finish_date_string'], $c['anime_start_date_string'], $c['anime_end_date_string'], $c['days_string'], $c['storage_string'], $c['priority_string'], $c['notes'], $c['editable_notes']], $this->_style);
            } else {
                $temp = str_replace(['{type}', '{id}', '{url}'], ['manga', $c['manga_id'], $c['manga_image_path']], $this->_style);
            }
            $cover .= $temp."\n";
        }

        return $cover;
    }
}
