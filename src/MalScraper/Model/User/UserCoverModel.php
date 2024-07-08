<?php

namespace MalScraper\Model\User;

use MalScraper\Model\User\UserListModel as UserList;
use MalScraper\Model\General\InfoModel as Info;

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
			if ($this->_type == 'anime') {
				$this->_style = '.data.image a[href^="/{type}/{anime_id}/"]:before{background-image:url("{anime_image_path}")!important}';
			} else {
				$this->_style = '.data.image a[href^="/{type}/{manga_id}/"]:before{background-image:url("{manga_image_path}")!important}';
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

        $cover = '';
        foreach ($list as $c) {
            if ($this->_type == 'anime') {
				$i = (new Info($this->_type, $this->$c['anime_id']))->getAllInfo();
				echo i;
                foreach ($i as $d) {
					$temp = str_replace(['{type}', '{synopsis}', '{status}', '{score}', '{tags}', '{is_rewatching}', '{num_watched_episodes}', '{created_at}', '{updated_at}', '{anime_title}', '{anime_title_eng}', '{anime_num_episodes}', '{anime_airing_status}', '{anime_id}', '{anime_studios}', '{anime_season}', '{anime_total_members}', '{anime_total_scores}', '{anime_score_val}', '{anime_score_diff}', '{anime_popularity}', '{has_episode_video}', '{has_promotion_video}', '{has_video}', '{video_url}', '{genres}', '{title_localized}', '{anime_url}', '{anime_image_path}', '{is_added_to_list}', '{anime_media_type_string}', '{anime_mpaa_rating_string}', '{start_date_string}', '{finish_date_string}', '{anime_start_date_string}', '{anime_end_date_string}', '{days_string}', '{storage_string}', '{priority_string}', '{notes}', '{editable_notes}'], ['anime', $d['data']['synopsis'], $c['status'], $c['score'], $c['tags'], $c['is_rewatching'], $c['num_watched_episodes'], $c['created_at'], $c['updated_at'], $c['anime_title'], $c['anime_title_eng'], $c['anime_num_episodes'], $c['anime_airing_status'], $c['anime_id'], $c['anime_studios'], $c['anime_season'], $c['anime_total_members'], $c['anime_total_scores'], $c['anime_score_val'], $c['anime_score_diff'], $c['anime_popularity'], $c['has_episode_video'], $c['has_promotion_video'], $c['has_video'], $c['video_url'], $c['genres'], $c['title_localized'], $c['anime_url'], $c['anime_image_path'], $c['is_added_to_list'], $c['anime_media_type_string'], $c['anime_mpaa_rating_string'], $c['start_date_string'], $c['finish_date_string'], $c['anime_start_date_string'], $c['anime_end_date_string'], $c['days_string'], $c['storage_string'], $c['priority_string'], $c['notes'], $c['editable_notes']], $this->_style);
				}
			} else {
				$i = (new Info($this->_type, $this->$c['manga_id']))->getAllInfo();
				echo $i;
				foreach ($i as $d) {
					$temp = str_replace(['{type}', '{synopsis}', '{id}', '{status}', '{score}', '{tags}', '{is_rereading}', '{num_read_chapters}', '{num_read_volumes}', '{created_at}', '{manga_title}', '{manga_english}', '{manga_num_chapters}', '{manga_num_volumes}', '{manga_publishing_status}', '{manga_id}', '{manga_magazines}', '{manga_total_members}', '{manga_total_scores}', '{manga_score_val}', '{manga_score_diff}', '{manga_popularity}', '{genres}', '{demographics}', '{title_localized}', '{manga_url}', '{manga_image_path}', '{is_added_to_list}', '{manga_media_type_string}', '{start_date_string}', '{finish_date_string}', '{manga_start_date_string}', '{manga_end_date_string}', '{days_string}', '{retail_string}', '{priority_string}', '{notes}', '{editable_notes}'], ['manga', $d['data']['synopsis'], $c['id'], $c['status'], $c['score'], $c['tags'], $c['is_rereading'], $c['num_read_chapters'], $c['num_read_volumes'], $c['created_at'], $c['manga_title'], $c['manga_english'], $c['manga_num_chapters'], $c['manga_num_volumes'], $c['manga_publishing_status'], $c['manga_id'], $c['manga_magazines'], $c['manga_total_members'], $c['manga_total_scores'], $c['manga_score_val'], $c['manga_score_diff'], $c['manga_popularity'], $c['genres'], $c['demographics'], $c['title_localized'], $c['manga_url'], $c['manga_image_path'], $c['is_added_to_list'], $c['manga_media_type_string'], $c['start_date_string'], $c['finish_date_string'], $c['manga_start_date_string'], $c['manga_end_date_string'], $c['days_string'], $c['retail_string'], $c['priority_string'], $c['notes'], $c['editable_notes']], $this->_style);
				}
			}
            $cover .= $temp."\n";
        }

        return $cover;
    }
}
