<?php

namespace MalScraper\Model\User;

use MalScraper\Model\User\UserListCSSModel as UserListCSS;

/**
 * UserCoverModel class.
 */
class UserCSSModel
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
    public function __construct($user, $type="anime", $status="7", $genre=null, $style=null)
    {
        $this->_user = $user;
        $this->_type = $type;
        $this->_status = $status;
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
        $list = (new UserListCSS($this->_user, $this->_type, $this->_status, $this->_genre))->getAllInfo();

        $cover = 'No UserList';
		if (is_array($list)) {
			$cover = '';
			foreach ($list as $c) {
				if ($this->_type == 'anime') {
					$temp = str_replace(['{\a}', '{type}', '{status}', '{score}', '{tags}', '{is_rewatching}', '{num_watched_episodes}', '{created_at}', '{updated_at}', '{anime_title}', '{anime_title_eng}', '{synopsis}', '{rank}', '{total_runtime}', '{year}', '{themes}', '{anime_num_episodes}', '{anime_airing_status}', '{anime_id}'/*, '{anime_studios}', '{anime_licensors}', '{anime_season}'*/, '{anime_total_members}', '{anime_total_scores}', '{anime_score_val}', '{anime_score_diff}', '{anime_popularity}', '{has_episode_video}', '{has_promotion_video}', '{has_video}', '{video_url}', '{genres}', '{title_localized}', '{anime_url}', '{anime_image_path}', '{is_added_to_list}', '{anime_media_type_string}', '{anime_mpaa_rating_string}', '{start_date_string}', '{finish_date_string}', '{anime_start_date_string}', '{anime_end_date_string}', '{days_string}', '{storage_string}', '{priority_string}', '{notes}', '{editable_notes}'], [$c['\a'], 'anime', $c['status'], $c['score'], $c['tags'], $c['is_rewatching'], $c['num_watched_episodes'], $c['created_at'], $c['updated_at'], $c['anime_title'], $c['anime_title_eng'], $c['synopsis'], $c['rank'], $c['total_runtime'], $c['year'], $c['themes'], $c['anime_num_episodes'], $c['anime_airing_status'], $c['anime_id']/*, $c['anime_studios'], $c['anime_licensors'], $c['anime_season']*/, $c['anime_total_members'], $c['anime_total_scores'], $c['anime_score_val'], $c['anime_score_diff'], $c['anime_popularity'], $c['has_episode_video'], $c['has_promotion_video'], $c['has_video'], $c['video_url'], $c['genres'], $c['title_localized'], $c['anime_url'], $c['anime_image_path'], $c['is_added_to_list'], $c['anime_media_type_string'], $c['anime_mpaa_rating_string'], $c['start_date_string'], $c['finish_date_string'], $c['anime_start_date_string'], $c['anime_end_date_string'], $c['days_string'], $c['storage_string'], $c['priority_string'], $c['notes'], $c['editable_notes']], $this->_style);
				} else {
					$temp = str_replace(['{\a}', '{type}', '{id}', '{status}', '{score}', '{tags}', '{is_rereading}', '{num_read_chapters}', '{num_read_volumes}', '{created_at}', '{manga_title}', '{manga_english}', '{synopsis}', '{rank}', '{year}', '{themes}', '{serialization}', '{manga_num_chapters}', '{manga_num_volumes}', '{manga_publishing_status}', '{manga_id}', '{manga_magazines}', '{manga_total_members}', '{manga_total_scores}', '{manga_score_val}', '{manga_score_diff}', '{manga_popularity}', '{genres}', '{demographic}', '{title_localized}', '{manga_url}', '{manga_image_path}', '{is_added_to_list}', '{manga_media_type_string}', '{start_date_string}', '{finish_date_string}', '{manga_start_date_string}', '{manga_end_date_string}', '{days_string}', '{retail_string}', '{priority_string}', '{notes}', '{editable_notes}'], [$c['\a'], 'manga', $c['id'], $c['status'], $c['score'], $c['tags'], $c['is_rereading'], $c['num_read_chapters'], $c['num_read_volumes'], $c['created_at'], $c['manga_title'], $c['manga_english'], $c['synopsis'], $c['rank'], $c['year'], $c['themes'], $c['serialization'], $c['manga_num_chapters'], $c['manga_num_volumes'], $c['manga_publishing_status'], $c['manga_id'], $c['manga_magazines'], $c['manga_total_members'], $c['manga_total_scores'], $c['manga_score_val'], $c['manga_score_diff'], $c['manga_popularity'], $c['genres'], $c['demographic'], $c['title_localized'], $c['manga_url'], $c['manga_image_path'], $c['is_added_to_list'], $c['manga_media_type_string'], $c['start_date_string'], $c['finish_date_string'], $c['manga_start_date_string'], $c['manga_end_date_string'], $c['days_string'], $c['retail_string'], $c['priority_string'], $c['notes'], $c['editable_notes']], $this->_style);
				}
			$cover .= $temp."\n";
			}
		}
        return $cover;
    }
}
