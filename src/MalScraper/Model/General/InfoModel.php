<?php

namespace MalScraper\Model\General;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;
use HtmlDomParser;

/**
 * InfoModel class.
 */
class InfoModel extends MainModel
{
    /**
     * Type of info. Either anime or manga.
     *
     * @var string
     */
    private $_type;

    /**
     * Id of the anime or manga.
     *
     * @var string|int
     */
    private $_id;

    /**
     * Default constructor.
     *
     * @param string     $type
     * @param string|int $id
     * @param string     $parserArea
     *
     * @return void
     */
    public function __construct($type, $id, $parserArea = '#contentWrapper')
    {
        $this->_type = $type;
        $this->_id = $id;
        $this->_url = $this->_myAnimeListUrl.'/'.$type.'/'.$id;
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
     * Get anime/manga id.
     *
     * @return string
     */
    private function getId()
    {
        return $this->_id;
    }

    /**
     * Get anime/manga cover.
     *
     * @return string
     */
	private function getCover()
	{
		$animeImage = $this->_parser->find('.lazyload', 0);
		if (!$animeImage) {
			return 'N/A'; 
		}

		return Helper::imageUrlCleaner($animeImage->getAttribute('data-src'));
	}

/**
 * Get anime/manga covers.
 *
 * @return array|string
 */
private function getImages()
{
    // Find the anchor tag with the inner text "Pictures"
    $pics_anchor = null;
    foreach ($this->_parser->find('#horiznav_nav > ul > li > a') as $element) {
        if (trim($element->plaintext) === 'Pictures') {
            $pics_anchor = $element;
            break;
        }
    }

    if (!$pics_anchor) {
        return null;
    }

    $pics_url = $pics_anchor->href;
	if (strpos($pics_url, "https://myanimelist.net") !== 0) {
		$pics_url = "https://myanimelist.net" . $pics_url;
	}
    // Fetch the HTML content of the pics page
    $html = file_get_contents($pics_url);

    if ($html === false) {
        return null;
    }

    // Parse the fetched HTML
    $doc = HtmlDomParser::str_get_html($html); // Assuming you're using Simple HTML DOM Parser

    if (!$doc) {
        return null;
    }

    $image_urls = [];
    $image_elements = $doc->find('div.picSurround a'); // Find all 'a' tags inside 'div.picSurround'

    foreach ($image_elements as $element) {
        if (isset($element->href)) {
            $image_urls[] = $element->href;
        }
    }

    return $image_urls;
}

/**
 * Get anime/manga approved.
 *
 * @return array|string
 */
private function getApproved()
{
    // Find the anchor tag with the inner text "Add to My List"
	$approved = $this->_parser->find('div[class=addtolist-block] span', 0);
    if ((trim($approved->plaintext)) === 'This anime is pending approval.') {
        $approved = "false";
    } else {
		$approved = "true";
	}

	return $approved;
}

// Example usage (assuming you have a way to set $this->_parser)
/*
$parser = new simple_html_dom();
$parser->load_file('your_page_url.html'); // replace with you page url
$this->_parser = $parser;

$image_urls = $this->getPics();

if (is_array($image_urls)) {
    print_r($image_urls);
} else {
    echo $image_urls;
}
*/

    /**
     * Get anime/manga title.
     *
     * @return string
     */
	private function getTitle()
	{
		//$animeImage = $this->_parser->find('.lazyload', 0);
		//if (!$animeImage) {
		//	return 'N/A';
		//}
		//$title = trim($animeImage->alt);
		$title = trim($this->_parser->find('h1[class=title-name] strong', 0)->plaintext);

		return $title;
	}

    /**
     * Get anime/manga alternative titles.
     *
     * @return array
     */
	private function getTitle2($retTitle)
	{
		$title2 = [];
		$title = '';
		$h2Element = $this->_parser->find('h2', 0);
		$nextElement = $h2Element->next_sibling();
		while ($nextElement) {
			if ($nextElement->tag == 'h2') {
				break;
			} elseif ($nextElement->tag == 'div' && ($nextElement->class == 'spaceit_pad' || $nextElement->class == 'js-alternative-titles hide')) {
				$titleElements = $nextElement->find('span.dark_text');
				if (empty($titleElements)) {
					break;
				}
				foreach ($titleElements as $titleElement) {
					$language = trim($titleElement->innertext);
					$nextElement2 = $titleElement->parent();
					if ($nextElement2) {
						$title = trim($nextElement2->text());
						if (strpos($title, $language) === 0) {
							$title = trim(substr($title, strlen($language)));
						}
						$title2[$language] = $title;
						if ($retTitle == str_replace(':', '', $language)) {return $title;}
					} else {
						$title = 'N/A';
						$title2[$language] = $title;
					}
				}
			}
			if ($nextElement) {
				$nextElement = $nextElement->next_sibling();
			} else {
				break;
			}
		}

		if ($retTitle == "") {return $title2;}
	}

    /**
     * Get anime/manga promotional video.
     *
     * @return string
     */
    private function getVideo()
    {
        $video_area = $this->_parser->find('.video-promotion', 0);
        if ($video_area) {
            $video = $video_area->find('a', 0)->href;

            return Helper::videoUrlCleaner($video);
        }

        return '';
    }

    /**
     * Get anime/manga synopsis.
     *
     * @return string
     */
    private function getSynopsis()
    {
        $synopsis = $this->_parser->find('p[itemprop=description]', 0);
        if ($synopsis) {
            $synopsis = $synopsis->plaintext;

            return trim(preg_replace('/\n[^\S\n]*/', "\n", $synopsis));
        } else {
            $synopsis = $this->_parser->find('span[itemprop=description]', 0);
			if ($synopsis) {
				$synopsis = $synopsis->plaintext;
				return trim(preg_replace('/\n[^\S\n]*/', "\n", $synopsis));
			} else {
				return;
			}
        }
    }

    /**
     * Get anime/manga score.
     *
     * @return string
     */
    private function getScore()
    {
        $score = $this->_parser->find('div[class="fl-l score"]', 0)->plaintext;
        $score = trim($score);

        return $score != 'N/A' ? $score : null;
    }

    /**
     * Get number of user who give score.
     *
     * @return string
     */
    private function getVoter()
    {
        $voter = $this->_parser->find('div[class="fl-l score"]', 0)->getAttribute('data-user');

        return trim(str_replace(['users', 'user', ','], '', $voter));
    }

    /**
     * Get anime/manga rank.
     *
     * @return string
     */
    private function getRank()
    {
        $rank = $this->_parser->find('span[class="numbers ranked"] strong', 0)->plaintext;
        $rank = $rank != 'N/A' ? $rank : '';

        return str_replace('#', '', $rank);
    }

    /**
     * Get anime/manga popularity.
     *
     * @return string
     */
    private function getPopularity()
    {
        $popularity = $this->_parser->find('span[class="numbers popularity"] strong', 0)->plaintext;

        return str_replace('#', '', $popularity);
    }

    /**
     * Get number of user who watch/read the anime/manga.
     *
     * @return string
     */
    private function getMembers()
    {
        $member = $this->_parser->find('span[class="numbers members"] strong', 0)->plaintext;

        return str_replace(',', '', $member);
    }

    /**
     * Get number of user who favorite the anime/manga.
     *
     * @return string
     */
    private function getFavorite()
    {
        $favorite = $this->_parser->find('div[data-id=info2]', 0)->next_sibling()->next_sibling()->next_sibling();
        $favorite_title = $favorite->find('span', 0)->plaintext;
        $favorite = $favorite->plaintext;
        $favorite = trim(str_replace($favorite_title, '', $favorite));
        $favorite = str_replace(',', '', $favorite);

        return preg_replace("/([\s])+/", ' ', $favorite);
    }

    /**
     * Get anime/manga detail info.
     *
     * @return array
     */
    private function getOtherInfo()
    {
        $info = [];

        $more_info = $this->_parser->find('div.leftside', 0);
        $other_info = (count($more_info->find('h2')) > 2) ? $more_info->find('h2', 1) : $more_info->find('h2', 0);
        if ($other_info) {
            $next_info = $other_info->next_sibling();
            while (true) {
                $info_type = $next_info->find('span', 0)->plaintext;

                $clean_info_type = strtolower(str_replace(': ', '', $info_type));
                $clean_info_value = $this->getCleanInfo($info_type, $next_info);
                $clean_info_value = $this->getCleanerInfo1($clean_info_type, $clean_info_value);
                $clean_info_value = $this->getCleanerInfo2($next_info, $clean_info_type, $clean_info_value);

                $info[$clean_info_type] = $clean_info_value;

                $next_info = $next_info->next_sibling();
                if ($next_info->tag == 'h2' || $next_info->tag == 'br') {
                    break;
                }
            }
        }

        return $info;
    }

    /**
     * Get clean other info.
     *
     * @param string                             $info_type
     * @param \simplehtmldom_1_5\simple_html_dom $next_info
     *
     * @return string
     */
    private function getCleanInfo($info_type, $next_info)
    {
        $info_value = $next_info->plaintext;
        $clean_info_value = trim(str_replace($info_type, '', $info_value));
        $clean_info_value = preg_replace("/([\s])+/", ' ', $clean_info_value);

        return str_replace([', add some', '?', 'Not yet aired', 'Unknown'], '', $clean_info_value);
    }

    /**
     * Get cleaner other info.
     *
     * @param string $clean_info_type
     * @param string $clean_info_value
     *
     * @return string|array
     */
    private function getCleanerInfo1($clean_info_type, $clean_info_value)
    {
        if ($clean_info_type == 'published' || $clean_info_type == 'aired') {
            $start_air = $end_air = '';
            if ($clean_info_value != 'Not available') {
                $parsed_airing = explode(' to ', $clean_info_value);
                $start_air = ($parsed_airing[0] != '?') ? $parsed_airing[0] : '';
                if (count($parsed_airing) > 1) {
                    $end_air = ($parsed_airing[1] != '?') ? $parsed_airing[1] : '';
                }
            }

            $clean_info_value = [];
            $clean_info_value['start'] = $start_air;
            $clean_info_value['end'] = $end_air;
        }

        return $clean_info_value;
    }

    /**
     * Get cleaner other info.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $next_info
     * @param string                             $clean_info_type
     * @param string|array                       $clean_info_value
     *
     * @return string|array
     */
    private function getCleanerInfo2($next_info, $clean_info_type, $clean_info_value)
    {
        if ($clean_info_type == 'producers'
            || $clean_info_type == 'licensors'
            || $clean_info_type == 'studios'
            || $clean_info_type == 'genre'
            || $clean_info_type == 'genres'
            || $clean_info_type == 'theme'
            || $clean_info_type == 'themes'
			|| $clean_info_type == 'demographic'
			|| $clean_info_type == 'serialization'
            || $clean_info_type == 'authors'
        ) {
            $info_temp = [];
            $info_temp_index = 0;
            if ($clean_info_value != 'None found') {
                foreach ($next_info->find('a') as $each_info) {
                    $temp_id = explode('/', $each_info->href);
                    $info_temp[$info_temp_index]['id'] = $clean_info_type == 'authors' ? $temp_id[2] : $temp_id[3];
                    $info_temp[$info_temp_index]['name'] = trim($each_info->plaintext);
                    $info_temp_index++;
                }
            }

            return $info_temp;
        }

        return $clean_info_value;
    }

    /**
     * Get anime/manga relation.
     *
     * @return array
     */
    private function getRelated()
    {
        $related = [];
        $related_area = $this->_parser->find('.entries-tile', 0);
        if ($related_area) {
            foreach ($related_area->find('div[class^=content]') as $rel) {
				$rel_type = trim(preg_replace('/\([^)]+\)/', '', $rel->find('div[class^=relation]', 0)->plaintext));

				$each_rel = [];
                $each_rel_index = 0;
                $rel_anime = $rel->find('div[class^=title]', 0);
                foreach ($rel_anime->find('a') as $r) {
                    $each_rel[$each_rel_index] = $this->getRelatedDetail($r);
                    $each_rel_index++;
                }

                $related[$rel_type][] = $each_rel;
            }
        }

        $related_area2 = $this->_parser->find('.entries-table', 0);
		if ($related_area2) {
            foreach ($related_area2->find('tr') as $rel2) {
                $rel_type2 = trim(str_replace(': ', '', $rel2->find('td', 0)->plaintext));

                $each_rel2 = [];
                $each_rel_index2 = 0;
				foreach ($rel2->find('ul[class^=entries]') as $ra) {
					foreach ($ra->find('li') as $r2) {
						$each_rel2[$each_rel_index2] = $this->getRelatedDetail($r2->find('a', 0));
						$each_rel_index2++;
					}

					$related[$rel_type2][] = $each_rel2;
				}
            }
        }

        return $related;
    }

    /**
     * Get related detail.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $r
     *
     * @return array
     */
    private function getRelatedDetail($r)
    {
        $related = [];
        $rel_anime_link = $r->href;
        $separated_anime_link = explode('/', $rel_anime_link);

        $related['id'] = $separated_anime_link[4];
        $related['type'] = $separated_anime_link[3];
        $related['name'] = trim($r->plaintext);
		$related['url'] = $rel_anime_link;

        return $related;
    }

    /**
     * Get anime/manga character and its va.
     *
     * @return array
     */
    private function getCharacter()
    {
        $character = [];
        $char_index = 0;
        $character_area = $this->_parser->find('div[class^=detail-characters-list]', 0);
        if ($character_area) {
            $character_list = [
                $character_area->find('div[class*=fl-l]', 0),
                $character_area->find('div[class*=fl-r]', 0),
            ];
            foreach ($character_list as $character_side) {
                if ($character_side) {
                    foreach ($character_side->find('table[width=100%]') as $each_char) {
                        $char = $each_char->find('tr td', 1);
                        $va = $each_char->find('table td', 0);

                        $character[$char_index]['id'] = $this->getStaffId($char);
                        $character[$char_index]['name'] = $this->getStaffName($char);
                        $character[$char_index]['role'] = $this->getStaffRole($char);
                        $character[$char_index]['image'] = $this->getStaffImage($each_char);

                        $character[$char_index]['va_id'] = $character[$char_index]['va_name'] = '';
                        $character[$char_index]['va_role'] = $character[$char_index]['va_image'] = '';

                        if ($va) {
                            $character[$char_index]['va_id'] = $this->getStaffId($va);
                            $character[$char_index]['va_name'] = $this->getStaffName($va, true);
                            $character[$char_index]['va_role'] = $this->getStaffRole($va);
                            $character[$char_index]['va_image'] = $this->getStaffImage($each_char, true);
                        }

                        $char_index++;
                    }
                }
            }
        }

        return $character;
    }

    /**
     * Get anime/manga staff involved.
     *
     * @return array
     */
    private function getStaff()
    {
        $staff = [];
        $staff_index = 0;
        $staff_area = $this->_parser->find('div[class^=detail-characters-list]', 1);
        if ($staff_area) {
            $staff_list = [
                $staff_area->find('div[class*=fl-l]', 0),
                $staff_area->find('div[class*=fl-r]', 0),
            ];
            foreach ($staff_list as $staff_side) {
                if ($staff_side) {
                    foreach ($staff_side->find('table[width=100%]') as $each_staff) {
                        $st = $each_staff->find('tr td', 1);

                        $staff[$staff_index]['id'] = $this->getStaffId($st);
                        $staff[$staff_index]['name'] = $this->getStaffName($st);
                        $staff[$staff_index]['role'] = $this->getStaffRole($st);
                        $staff[$staff_index]['image'] = $this->getStaffImage($each_staff);

                        $staff_index++;
                    }
                }
            }
        }

        return $staff;
    }

    /**
     * Get staff id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $st
     *
     * @return string
     */
    private function getStaffId($st)
    {
        $staff_id = $st->find('a', 0)->href;
        $staff_id = explode('/', $staff_id);

        return $staff_id[4];
    }

    /**
     * Get staff name.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $st
     * @param bool                               $va (Optional)
     *
     * @return string
     */
    private function getStaffName($st, $va = false)
    {
        if ($va) {
            return $st->find('a', 0)->plaintext;
        }

        return trim(preg_replace('/\s+/', ' ', $st->find('a', 0)->plaintext));
    }

    /**
     * Get staff role.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $st
     *
     * @return string
     */
    private function getStaffRole($st)
    {
        return trim($st->find('small', 0)->plaintext);
    }

    /**
     * Get staff image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_staff
     * @param bool                               $va         (Optional)
     *
     * @return string
     */
    private function getStaffImage($each_staff, $va = false)
    {
        if ($va) {
            $staff_image = $each_staff->find('table td', 1)->find('img', 0)->getAttribute('data-src');
        } else {
            $staff_image = $each_staff->find('tr td', 0)->find('img', 0)->getAttribute('data-src');
        }

        return Helper::imageUrlCleaner($staff_image);
    }

    /**
     * Get anime/manga opening and ending song.
     *
     * @return array
     */
    private function getSong()
    {
        $song = [];
        $song_area = $this->_parser->find('div[class*="theme-songs js-theme-songs opnening"]', 0);
        if ($song_area) {
            foreach ($song_area->find('td') as $each_song) {
				$each_song = $each_song->plaintext;
				$each_song = trim(preg_replace('/\s+/', ' ', $each_song));
				if (strpos($each_song, ' by ') !== false) {
					$song['openings'][] = $each_song;
				}
            }
        }

        $song_area = $this->_parser->find('div[class*="theme-songs js-theme-songs ending"]', 0);
        if ($song_area) {
            foreach ($song_area->find('td') as $each_song) {
				$each_song = $each_song->plaintext;
				$each_song = trim(preg_replace('/\s+/', ' ', $each_song));
				if (strpos($each_song, ' by ') !== false) {
					$song['endings'][] = $each_song;
				}
            }
        }

        return $song;
    }

    /**
     * Get anime/manga review.
     *
     * @return array
     */
    private function getReview()
    {
        $review = [];
        $review_area = $this->_parser->find('div[class*="review-element js-review-element"]');
        foreach ($review_area as $each_review) {
            $tmp = [];

            $tmp['id'] = $this->getReviewId($each_review->find('div[class="open"]', 0));
            $tmp['username'] = $this->getReviewUser($each_review->find('div[class="username"]', 0));
            $tmp['image'] = $this->getReviewImage($each_review->find('div[class="thumb"]', 0));
            $tmp['date'] =  $this->getReviewDate($each_review->find('div[class="update_at"]', 0));
            if ($this->_type == 'anime') {
                $tmp['episode'] = $this->getReviewEpisode($each_review->find('div[class="tag preliminary"]', 0));
            } else {
                $tmp['chapter'] = $this->getReviewEpisode($each_review->find('div[class="tag preliminary"]', 0));
            }
            $tmp['score'] = $this->getReviewScore($each_review->find('span[class="num"]', 0));
            $tmp['review'] = $this->getReviewText($each_review->find('div[class="text"]', 0));

            $review[] = $tmp;
        }

        return $review;
    }

    /**
     * Get review user.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $id
     *
     * @return string
     */
    private function getReviewId($id)
    {
        $id = $id->find('a', 0)->href;
        $id = explode('?id=', $id);

        return $id[1];
    }

    /**
     * Get review id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $user
     *
     * @return string
     */
    private function getReviewUser($user)
    {
        return trim($user->plaintext);
    }

    /**
     * Get review image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $image
     *
     * @return string
     */
    private function getReviewImage($image)
    {
        $image = $image->find('img', 0)->getAttribute('data-src');

        return Helper::imageUrlCleaner($image);
    }

    /**
     * Get review date.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $date
     *
     * @return array
     */
    private function getReviewDate($date)
    {
        return [
            'date' => $date->plaintext,
            'time' => $date->title,
        ];
    }

    /**
     * Get review episode seen.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $episode
     *
     * @return string
     */
    private function getReviewEpisode($episode)
    {
		if ($episode) {
			$episode = $episode->find('span', 0)->plaintext;
			$episode = str_replace(['(', ' eps', ')'], '', $episode);

			return trim($episode);
		}

        return '';
    }

    /**
     * Get review score.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $score
     *
     * @return array
     */
    private function getReviewScore($score)
    {
        $score = $score->plaintext;

        return trim($score);
    }

    /**
     * Get review text.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $text
     *
     * @return string
     */
    private function getReviewText($text)
    {
        $text = str_replace('&lt;', '<', $text->plaintext);

        return trim(preg_replace('/\h+/', ' ', $text));
    }

    /**
     * Get anime/manga recommendation.
     *
     * @return array
     */
    private function getRecommendation()
    {
        $recommendation = [];
        $recommendation_area = $this->_type == 'anime' ? $this->_parser->find('#anime_recommendation', 0) : $this->_parser->find('#manga_recommendation', 0);
        if ($recommendation_area) {
            foreach ($recommendation_area->find('li.btn-anime') as $each_recom) {
                $tmp = [];

                $tmp['id'] = $this->getRecomId($each_recom);
                $tmp['name'] = $this->getRecomTitle($each_recom);
                $tmp['image'] = $this->getRecomImage($each_recom);
                $tmp['user'] = $this->getRecomUser($each_recom);

                $recommendation[] = $tmp;
            }
        }

        return $recommendation;
    }

    /**
     * Get recommendation id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_recom
     *
     * @return string
     */
    private function getRecomId($each_recom)
    {
        $id = $each_recom->find('a', 0)->href;
        $id = explode('/', $id);
        $id = explode('-', $id[5]);
        if ($id[0] == $this->_id) {
            return $id[1];
        } else {
            return $id[0];
        }
    }

    /**
     * Get recommendation title.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_recom
     *
     * @return string
     */
    private function getRecomTitle($each_recom)
    {
        return $each_recom->find('span', 0)->plaintext;
    }

    /**
     * Get recommendation image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_recom
     *
     * @return string
     */
    private function getRecomImage($each_recom)
    {
        $image = $each_recom->find('img', 0)->getAttribute('data-src');

        return Helper::imageUrlCleaner($image);
    }

    /**
     * Get recommendation user.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_recom
     *
     * @return string
     */
    private function getRecomUser($each_recom)
    {
        $user = $each_recom->find('.users', 0)->plaintext;
        $user = str_replace(['Users', 'User'], '', $user);

        return trim($user);
    }

    /**
     * Get anime/manga external.
     *
     * @return array
     */
    private function getExternal()
    {
        $external = [];
		$external_index = 0;
		$external_area = $this->_parser->find('div.leftside', 0);
        if ($external_area) {
		    foreach ($external_area->find('.external_links') as $each_external) {
				foreach ($each_external->find('a') as $each_link) {
					if (trim($each_link->plaintext) == 'More links') {
					} else {
						$external[$external_index]['name'] = trim($each_link->plaintext);
						$external[$external_index]['url'] = $each_link->href;
						$external_index++;
					}
				}
			}

            return $external;
        }
    }

    /**
     * Get anime/manga streaming.
     *
     * @return array
     */
    private function getStreaming()
    {
        $streaming = [];
		$streaming_index = 0;
		$streaming_area = $this->_parser->find('div.leftside', 0);
        if ($streaming_area) {
		    foreach ($streaming_area->find('.broadcast') as $each_streaming) {
				foreach ($each_streaming->find('a') as $each_link) {
					if (trim($each_link->plaintext) == 'More links') {
					} else {
						$streaming[$streaming_index]['name'] = trim($each_link->plaintext);
						$streaming[$streaming_index]['url'] = $each_link->href;
						$streaming_index++;
					}
				}
			}

            return $streaming;
        }
    }

    /**
     * Get anime/manga all information.
     *
     * @return array
     */
    private function getAllInfo()
    {
        $data = [
            'id'             => $this->getId(),
            'cover'          => $this->getCover(),
			'images'         => $this->getImages(),
			'approved'       => $this->getApproved(),
            'title'          => $this->getTitle(),
            'titles'         => $this->getTitle2(""),
            'title_english'  => $this->getTitle2("English"),
            'title_japanese' => $this->getTitle2("Japanese"),
            'title_synonyms' => $this->getTitle2("Synonyms"),
            'title_german'   => $this->getTitle2("German"),
            'title_spanish'  => $this->getTitle2("Spanish"),
            'title_french'   => $this->getTitle2("French"),
            'video'          => $this->getVideo(),
            'synopsis'       => $this->getSynopsis(),
            'score'          => $this->getScore(),
            'scored_by'      => $this->getVoter(),
            'rank'           => $this->getRank(),
            'popularity'     => $this->getPopularity(),
            'members'        => $this->getMembers(),
            'favorites'      => $this->getFavorite(),
        ];

        $data = array_merge($data, $this->getOtherInfo());

        $data2 = [
            'relations'       => $this->getRelated(),
            'characters'      => $this->getCharacter(),
            'staff'           => $this->getStaff(),
            'songs'           => $this->getSong(),
            'reviews'         => $this->getReview(),
            'recommendations' => $this->getRecommendation(),
            'external'        => $this->getExternal(),
			'streaming'       => $this->getStreaming(),
        ];
 
        $data = array_merge($data, $data2);

        return $data;
    }
}
