<?php

namespace MalScraper\Model\General;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * ReviewModel class.
 */
class ReviewModel extends MainModel
{
    /**
     * Id of the review.
     *
     * @var string|int
     */
    private $_id;

    /**
     * Either anime, manga.
     *
     * @var string
     */
    private $_type;

    /**
     * Default constructor.
     *
     * @param string|int $id
     * @param string     $parserArea
     *
     * @return void
     */
    public function __construct($id, $parserArea = '#content')
    {
        $this->_id = $id;
        $this->_url = $this->_myAnimeListUrl.'/reviews.php?id='.$id;
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
     * Get type.
     *
     * @return string
     */
    private function getType()
    {
        return $this->_type;
    }

    /**
     * Get id.
     *
     * @return string
     */
    private function getId()
    {
        return $this->_id;
    }

    /**
     * Get review source.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $top_area
     * @param \simplehtmldom_1_5\simple_html_dom $bottom_area
     *
     * @return array
     */
    private function getReviewSource($top_area, $bottom_area)
    {
        $source_area = $top_area->find('.mb4', 0);

        return [
            'type' => $this->getSourceType($source_area),
            'id'   => $this->getSourceId($source_area),
            'title'=> $this->getSourceTitle($source_area),
            /*'image'=> $this->getSourceImage($bottom_area),*/
        ];
    }

    /**
     * Get source type.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $source_area
     *
     * @return string
     */
    private function getSourceType($source_area)
    {
        $type = $source_area->find('small', 0)->plaintext;
        $type = str_replace(['(', ')'], '', $type);
        $this->_type = strtolower($type);

        return strtolower($type);
    }

    /**
     * Get source id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $source_area
     *
     * @return string
     */
    private function getSourceId($source_area)
    {
        $id = $source_area->find('a', 0)->href;
        $id = explode('/', $id);

        return $id[4];
    }

    /**
     * Get source title.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $source_area
     *
     * @return string
     */
    private function getSourceTitle($source_area)
    {
        $title = $source_area->find('a', 0)->plaintext;

        return trim($title);
    }

    /**
     * Get source image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $bottom_area
     *
     * @return string
     */
    private function getSourceImage($bottom_area)
    {
		$image_area = $bottom_area->find('.body', 0);
        $image = $image_area->find('img', 0)->getAttribute('data-src');

        return Helper::imageUrlCleaner($image);
    }

    /**
     * Get review id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $top_area
     *
     * @return string
     */
    private function getReviewUser($top_area)
    {
        $user = $top_area->find('.username', 0);

        return $user->find('a', 0)->plaintext;
    }

    /**
     * Get review image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $top_area
     *
     * @return string
     */
    private function getReviewImage($top_area)
    {
        $image_area = $top_area->find('.thumb-right', 0);
        $image = $image_area->find('a', 0)->find('img', 0)->getAttribute('data-src');

        return Helper::imageUrlCleaner($image);
    }

    /**
     * Get review helful.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $top_area
     *
     * @return string
     */
    private function getReviewHelpful($top_area)
    {
        $helpful = $top_area->find('.recommended', 0)->innertext;
        $helpful = str_replace('<i class="fas fa-star"></i>', '', $helpful);
		
        return trim($helpful);
    }

    /**
     * Get review date.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $top_area
     *
     * @return array
     */
    private function getReviewDate($top_area)
    {
        $date = $top_area->find('div div', 0);

        return [
            'date' => $date->plaintext,
            'time' => $date->title,
        ];
    }

    /**
     * Get review episode seen.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $top_area
     *
     * @return string
     */
    private function getReviewEpisode($top_area)
    {
        $episode = $top_area->find('div div', 1)->plaintext;
        $episode = str_replace(['episodes seen', 'chapters read'], '', $episode);

        return trim($episode);
    }

    /**
     * Get review score.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $bottom_area
     *
     * @return array
     */
    private function getReviewScore($bottom_area)
    {
        $score = [];
        $score_area = $bottom_area->find('table', 0);
        if ($score_area) {
            foreach ($score_area->find('tr') as $each_score) {
                $score_type = strtolower($each_score->find('td', 0)->plaintext);
                $score_value = $each_score->find('td', 1)->plaintext;
                $score[$score_type] = $score_value;
            }
        }

        return $score;
    }

    /**
     * Get review text.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $bottom_area
     *
     * @return string
     */
    private function getReviewText($bottom_area)
    {
        $text = $bottom_area->find('.body', 0)->plaintext;

        $text = str_replace('&lt;', '<', $text);

        return trim(preg_replace('/\h+/', ' ', $text));
    }

    /**
     * Get anime/mange review.
     *
     * @return array
     */
    private function getAllInfo()
    {
        $data = [];
        $review_area = $this->_parser->find('#content', 0);

        $top_area = $review_area->find('.review-element', 0);
        $bottom_area = $top_area->next_sibling();

        $data['id'] = $this->getId();
        $data['source'] = $this->getReviewSource($top_area, $bottom_area);
        $data['username'] = $this->getReviewUser($top_area);
        $data['image'] = $this->getReviewImage($top_area);
        $data['helpful'] = $this->getReviewHelpful($top_area);
        $data['date'] = $this->getReviewDate($top_area);
        if ($this->_type == 'anime') {
            $data['episode'] = $this->getReviewEpisode($top_area);
        } else {
            $data['chapter'] = $this->getReviewEpisode($top_area);
        }
        $data['score'] = $this->getReviewScore($bottom_area);
        $data['review'] = $this->getReviewText($bottom_area);

        return $data;
    }
}
