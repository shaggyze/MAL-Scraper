<?php

namespace MalScraper\Model\General;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * ProducerModel class.
 */
class ProducerModel extends MainModel
{
    // ... (Your existing __construct, __call, getAnimeImage, getAnimeId, getAnimeTitle are assumed to be working) ...
    // ... (I will include them for completeness but mark them as YOUR_WORKING_ORIGINAL if unchanged by me)

    /**
     * Either anime or manga.
     *
     * @var string
     */
    private $_type;

    /**
     * Either producer or genre.
     *
     * @var string
     */
    private $_type2;

    /**
     * Id of the producer.
     *
     * @var string|int
     */
    private $_id;

    /**
     * Page number.
     *
     * @var string|int
     */
    private $_page;

    public function __construct($type, $type2, $id, $page = 1, $parserArea = '#content')
    {
        $this->_type = $type;
        $this->_type2 = $type2;
        $this->_id = $id;
        $this->_page = $page;

        if ($type2 == 'producer') {
            if ($type == 'anime') {
                $this->_url = $this->_myAnimeListUrl.'/anime/producer/'.$id.'/?page='.$page;
            } else {
                $this->_url = $this->_myAnimeListUrl.'/manga/magazine/'.$id.'/?page='.$page;
            }
        } else {
            $this->_url = $this->_myAnimeListUrl.'/'.$type.'/genre/'.$id.'/?page='.$page;
        }
        $this->_parserArea = $parserArea;
        parent::errorCheck($this);
    }

    public function __call($method, $arguments)
    {
        if ($this->_error) {
            return $this->_error;
        }
        if (empty($method) || $method === 'getAllInfo') {
            if (method_exists($this, 'getAllInfo')) {
                return $this->getAllInfo(...$arguments);
            }
        }
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        }
        return ['error' => "Method {$method} does not exist in " . __CLASS__];
    }

    private function getAnimeImage($each_anime) // YOUR_WORKING_ORIGINAL (with safety)
    {
        $imageDiv = $each_anime->find('div[class=image]', 0);
        if ($imageDiv) {
            $imgTag = $imageDiv->find('img', 0);
            if ($imgTag && $imgTag->hasAttribute('data-src')) {
                return Helper::imageUrlCleaner($imgTag->getAttribute('data-src'));
            } elseif ($imgTag && $imgTag->hasAttribute('src')) { // Fallback to src
                return Helper::imageUrlCleaner($imgTag->getAttribute('src'));
            }
        }
        return '';
    }

    private function getAnimeId($name_area) // YOUR_WORKING_ORIGINAL (with safety)
    {
        $linkNode = $name_area->find('a', 0);
        if ($linkNode && isset($linkNode->href)) {
            $anime_id_parts = explode('/', $linkNode->href);
            if (isset($anime_id_parts[4]) && is_numeric($anime_id_parts[4])) {
                return $anime_id_parts[4];
            }
        }
        return '';
    }

    private function getAnimeTitle($name_area) // YOUR_WORKING_ORIGINAL (with safety)
    {
        $node = $name_area->find('a', 0);
        return ($node && isset($node->plaintext)) ? trim($node->plaintext) : '';
    }

    /**
     * Get producer name.
     * $producer_info_container is likely $each_anime->find('.information', 0) or similar
     *
     * @param \simplehtmldom_1_5\simple_html_dom $producer_info_container
     *
     * @return array
     */
    private function getAnimeProducer($producer_info_container) // RESTORED & SIMPLIFIED
    {
        $producer = [];
        if (!$producer_info_container || !is_object($producer_info_container)) return $producer;

        // Your original logic targeted 'span[class=item]' directly under $producer_area.
        // Let's assume this span.item contains the links.
        $itemSpan = $producer_info_container->find('span[class=item]', 0); // Your specific selector
        // MAL often has "Studios: <a>Studio1</a>, <a>Studio2</a>"
        // A more common selector for studios/authors today is within a 'div.properties' block.
        // For simplicity, sticking to your pattern: find the *first* span.item and get links from it.
        // This is fragile if there are multiple span.item or if the links are not direct children.

        if ($itemSpan) { // If your specific span.item is found
            foreach ($itemSpan->find('a') as $each_producer_link) {
                $temp_prod = [];
                $temp_prod['id'] = $this->getAnimeProducerId($each_producer_link);
                $temp_prod['name'] = $this->getAnimeProducerName($each_producer_link);
                if (!empty($temp_prod['id']) || !empty($temp_prod['name'])) {
                    $producer[] = $temp_prod;
                }
            }
        } else {
            // Alternative: If "Studios:" or "Authors:" text exists, look for <a> tags after it.
            // This is complex to do simply without knowing the exact structure.
            // For now, if your original `span[class=item]` selector doesn't work, this will be empty.
            // A common structure for studios in a list item's `.information` block:
            // <span class="producer"><a>Studio Name</a></span> or <span class="studio"><a>Studio Name</a></span>
            $studioLinks = $producer_info_container->find('span.producer a, span.studio a, span.authors a'); // Common classes
            foreach ($studioLinks as $each_producer_link) {
                 $temp_prod = [];
                 $temp_prod['id'] = $this->getAnimeProducerId($each_producer_link);
                 $temp_prod['name'] = $this->getAnimeProducerName($each_producer_link);
                 if (!empty($temp_prod['id']) || !empty($temp_prod['name'])) {
                    $producer[] = $temp_prod;
                 }
            }
        }
        return $producer;
    }

    private function getAnimeProducerId($each_producer) // YOUR_ORIGINAL (with safety)
    {
        if (!is_object($each_producer) || empty($each_producer->href)) return '';
        $prod_id_href = $each_producer->href;
        $prod_id_parts = explode('/', rtrim($prod_id_href, '/'));
        
        if ($this->_type == 'anime') {
            if (isset($prod_id_parts[3]) && $prod_id_parts[1] == 'anime' && $prod_id_parts[2] == 'producer' && is_numeric($prod_id_parts[3])) {
                return $prod_id_parts[3];
            }
        } else { 
            if (isset($prod_id_parts[2]) && $prod_id_parts[1] == 'people' && is_numeric($prod_id_parts[2])) {
                return $prod_id_parts[2];
            }
             if (isset($prod_id_parts[3]) && $prod_id_parts[1] == 'manga' && $prod_id_parts[2] == 'magazine' && is_numeric($prod_id_parts[3])) {
                 return $prod_id_parts[3];
            }
            // Your original used $prod_id_parts[4] for manga, if that was specific:
            // if (isset($prod_id_parts[4]) && is_numeric($prod_id_parts[4])) return $prod_id_parts[4];
        }
        return '';
    }

    private function getAnimeProducerName($each_producer) // YOUR_ORIGINAL (with safety)
    {
        return (is_object($each_producer) && isset($each_producer->plaintext)) ? trim($each_producer->plaintext) : '';
    }

    /**
     * Get anime episode.
     * $producer_info_container is likely $each_anime->find('.information', 0) or similar
     *
     * @param \simplehtmldom_1_5\simple_html_dom $producer_info_container
     *
     * @return string
     */
    private function getAnimeEpisode($producer_info_container) // RESTORED & SIMPLIFIED
    {
        if (!$producer_info_container || !is_object($producer_info_container)) return '';
        // Your original logic: $episode = $producer_area->find('div[class=eps]', 0)->plaintext;
        // This 'div.eps' is often found in a 'div.meta' which might be inside '.information'
        $epsNode = $producer_info_container->find('div.eps, span.eps', 0); // Try div.eps or span.eps
        if ($epsNode && isset($epsNode->plaintext)) {
            $text = trim($epsNode->plaintext);
            // Remove "eps", "ep", "vols", "vol" and parentheses, then trim
            $cleaned_text = str_ireplace(['eps', 'ep', 'vols', 'vol', '(', ')'], '', $text);
            // Get only leading digits if any
            if (preg_match('/^(\d+)/', trim($cleaned_text), $matches)) {
                return $matches[1];
            }
            return trim($cleaned_text); // Return "Unknown" or other text as is
        }
        return '';
    }

    /**
     * Get anime source.
     * $producer_info_container is likely $each_anime->find('.information', 0) or similar
     *
     * @param \simplehtmldom_1_5\simple_html_dom $producer_info_container
     *
     * @return string
     */
    private function getAnimeSource($producer_info_container) // RESTORED & SIMPLIFIED
    {
        if (!$producer_info_container || !is_object($producer_info_container)) return '';
        // Your original logic: $source = $producer_area->find('span[class=item]', 0)->plaintext;
        // This is very generic. "Source" is often labeled.
        // Let's look for a span that might contain source information, often near other metadata.
        // A common class is 'source' or it might be the first non-numeric span in a 'meta' div.
        $sourceNode = $producer_info_container->find('span.source', 0);
        if ($sourceNode && isset($sourceNode->plaintext)) {
            return trim($sourceNode->plaintext);
        }
        // If not found by class, try a more general approach if your $producer_info_container
        // was indeed the $producer_area that held a generic 'span.item' for source.
        // This is a direct interpretation of your original if $producer_info_container is what you meant by $producer_area.
        $itemSpan = $producer_info_container->find('span[class=item]', 0);
        if ($itemSpan && isset($itemSpan->plaintext)) {
             // This might not be "source", could be anything. High risk of wrong data.
             // We need a way to distinguish this span.item as the source.
             // For now, returning it as per your original if nothing more specific found.
             // return trim($itemSpan->plaintext); // Commented out due to high ambiguity
        }

        // More reliable way: Look for "Source: Some Source" text pattern if it exists.
        // This is hard to do with simple find.
        // Modern MAL uses div.properties for this. If $producer_info_container is $each_anime:
        $properties = $producer_info_container->find('div.properties',0);
        if ($properties) {
            $targetCaption = ($this->_type == 'anime') ? 'Source:' : 'Type:';
            foreach ($properties->find('div.property') as $propDiv) {
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                    $itemNode = $propDiv->find('span.item', 0);
                    if ($itemNode && isset($itemNode->plaintext)) {
                        return trim($itemNode->plaintext);
                    }
                    break;
                }
            }
        }
        return '';
    }

    /**
     * Get anime genre.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return array
     */
    private function getAnimeGenre($each_anime) // RESTORED & SIMPLIFIED
    {
        $genres = [];
        if (!$each_anime || !is_object($each_anime)) return $genres;
        // Your original selector: $genre_area = $each_anime->find('div[class="genres-inner js-genre-inner"]', 0);
        // And then: $genre_area->find('span a')
        $genre_area = $each_anime->find('div.genres-inner.js-genre-inner', 0); // Using your classes
        if (!$genre_area) { // Fallback to a more common modern class
            $genre_area = $each_anime->find('div.genres.js-genre', 0);
        }

        if ($genre_area) {
            // Your original was 'span a'. Modern is often 'span.genre a' or just 'a'
            $links = $genre_area->find('span a'); // Your specific pattern
            if (empty($links)) {
                $links = $genre_area->find('a'); // Broader fallback
            }
            foreach ($links as $each_genre_link) {
                if (isset($each_genre_link->plaintext)) {
                    $genres[] = trim($each_genre_link->plaintext);
                }
            }
        }
        return $genres;
    }

    /**
     * Get anime synopsis.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeSynopsis($each_anime) // RESTORED & SIMPLIFIED
    {
        if (!$each_anime || !is_object($each_anime)) return '';
        // Your original selector: $synopsis_container = $each_anime->find('div[class="synopsis js-synopsis"]', 0);
        $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0); // Using your classes

        if ($synopsis_container && isset($synopsis_container->plaintext)) {
            // If the synopsis has a "Read more" link, its text might be included.
            // A simple approach is to take the plaintext and clean it.
            // More advanced would be to remove the 'Read more' <a> tag first.
            $textNode = $synopsis_container->find('div.text, span.preline', 0); // Common children
            if (!$textNode) $textNode = $synopsis_container; // Use container itself

            if ($textNode && isset($textNode->plaintext)) {
                 foreach ($textNode->find('a') as $a_tag) { // Remove 'Read more' links
                    if (isset($a_tag->plaintext) && strtolower(trim($a_tag->plaintext)) === 'read more') {
                        $a_tag->outertext = '';
                    }
                }
                $text = isset($textNode->plaintext) ? trim($textNode->plaintext) : '';
                return trim(preg_replace("/([\s])+/", ' ', $text));
            }
        }
        return '';
    }

    /**
     * Get anime licensor.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string|array
     */
    private function getAnimeLicensor($each_anime) // RESTORED & SIMPLIFIED
    {
        if (!$each_anime || !is_object($each_anime)) return ($this->_type == 'anime') ? [] : '';
        // Your original logic:
        if ($this->_type == 'anime') {
            // $each_anime->find('div[class="synopsis js-synopsis"] .licensors', 0)->getAttribute('data-licensors');
            $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0);
            if ($synopsis_container) {
                $licensor_node = $synopsis_container->find('.licensors', 0); // Your class '.licensors'
                if ($licensor_node && $licensor_node->hasAttribute('data-licensors')) {
                    $licensor_attr = $licensor_node->getAttribute('data-licensors');
                    return array_filter(explode(',', $licensor_attr));
                }
            }
            return [];
        } else { // Manga (Serialization)
            // $each_anime->find('div[class="synopsis js-synopsis"] .serialization a', 0);
            $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0);
            if ($synopsis_container) {
                // Your original was '.serialization a'. This could be 'span.serialization a' or 'div.serialization a'
                $serialization_link = $synopsis_container->find('.serialization a', 0);
                if ($serialization_link && isset($serialization_link->plaintext)) {
                    return trim($serialization_link->plaintext);
                }
            }
            return '';
        }
    }

    /**
     * Get anime type.
     * $info_area is $each_anime->find('.information', 0);
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeType($info_area_node) // RESTORED & SIMPLIFIED
    {
        if (!$info_area_node || !is_object($info_area_node)) return '';
        // Your original logic: $type = $info_area->find('.info', 0)->plaintext;
        $info_div = $info_area_node->find('div.info', 0); // Your class '.info'
        if ($info_div && isset($info_div->plaintext)) {
            $type_text = $info_div->plaintext;
            $type_parts = explode('-', $type_text); // Your original processing
            return trim($type_parts[0]);
        }
        return '';
    }

    /**
     * Get anime start.
     * $info_area is $each_anime->find('.information', 0);
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeStart($info_area_node) // RESTORED & SIMPLIFIED
    {
        if (!$info_area_node || !is_object($info_area_node)) return '';
        // Your original logic: $airing_start = $info_area->find('.info .remain-time', 0)->plaintext;
        $info_div = $info_area_node->find('div.info', 0);
        if ($info_div) {
            $remain_time_node = $info_div->find('span.remain-time', 0); // Your class '.remain-time'
            if ($remain_time_node && isset($remain_time_node->plaintext)) {
                return trim($remain_time_node->plaintext);
            }
        }
        return '';
    }

    /**
     * Get anime score.
     * $info_area is $each_anime->find('.information', 0);
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeScore($info_area_node) // RESTORED & SIMPLIFIED
    {
        if (!$info_area_node || !is_object($info_area_node)) return 'N/A';
        // Your original logic: $score = $info_area->find('.scormem .score', 0)->plaintext;
        $scormem_div = $info_area_node->find('div.scormem', 0); // Your class '.scormem'
        if ($scormem_div) {
            $score_node = $scormem_div->find('span.score', 0); // Your class '.score'
            if ($score_node && isset($score_node->plaintext)) {
                $score_text = trim($score_node->plaintext);
                return (is_numeric($score_text) && (float)$score_text > 0) ? $score_text : 'N/A';
            }
        }
        return 'N/A';
    }

    /**
     * Get anime member.
     * $info_area is $each_anime->find('.information', 0);
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeMember($info_area_node) // RESTORED & SIMPLIFIED
    {
        if (!$info_area_node || !is_object($info_area_node)) return '0';
        // Your original logic: $member = $info_area->find('.scormem span[class^=member]', 0)->plaintext;
        $scormem_div = $info_area_node->find('div.scormem', 0);
        if ($scormem_div) {
            $member_node = $scormem_div->find('span[class^=member]', 0); // Your attribute selector
            if ($member_node && isset($member_node->plaintext)) {
                $member_text = trim(str_replace(',', '', $member_node->plaintext));
                 if (preg_match('/([\d\.]+)\s*(K|M)?/i', $member_text, $matches)) {
                    $countVal = (float)str_replace(['K','M','k','m'], '', $matches[1]);
                    $suffix = isset($matches[2]) ? strtoupper($matches[2]) : '';
                    if ($suffix == 'M') $countVal *= 1000000;
                    if ($suffix == 'K') $countVal *= 1000;
                    return (string)(int)$countVal;
                }
                return preg_replace('/[^\d]/', '', $member_text); // Fallback
            }
        }
        return '0';
    }

    /**
     * Get all anime produced by the studio/producer.
     *
     * @return array
     */
    private function getAllInfo()
    {
        if (!$this->_parser || !is_object($this->_parser)) {
            $this->_error = ['error' => 'Parser not initialized or invalid.'];
            return $this->_error;
        }

        $data = [];
        // YOUR ORIGINAL SELECTOR FOR THE LIST OF ITEMS
        $anime_table = $this->_parser->find('div[class="js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3"]');

        foreach ($anime_table as $each_anime) {
            if(!is_object($each_anime)) continue; 

            $result = [];

            // YOUR ORIGINAL SUB-SELECTORS:
            $name_area = $each_anime->find('div[class=title]', 0);
            
            // CRITICAL: Correcting your $producer_area selector.
            // Original: $producer_area = $each_anime->find('div[class=property"]', 0); (Syntax error)
            // Assuming it was meant to be a general info container that might hold producer/source/episode info,
            // or perhaps it was meant to be the same as $info_area or $each_anime itself if these details
            // were direct children or in a known simple structure.
            // For this attempt, let's use '.information' as a common container for these,
            // similar to $info_area, if $producer_area was meant to be distinct.
            // If source/producer/episode are directly under $each_anime, then pass $each_anime.
            // Let's call it $details_container to avoid confusion with $info_area used for other fields.
            // Often, producer/source/episode are inside '.information' or a 'div.meta'
            $details_container = $each_anime->find('.information', 0); // A common container
            if (!$details_container) $details_container = $each_anime; // Fallback to item itself

            $info_area = $each_anime->find('.information', 0); // Your original selector for other fields


            // These are working as per your statement (with safety checks added)
            $result['image'] = ''; $result['id'] = ''; $result['title'] = ''; // Initialize
            if ($each_anime) $result['image'] = $this->getAnimeImage($each_anime);
            if ($name_area && is_object($name_area)) {
                $result['id'] = $this->getAnimeId($name_area);
                $result['title'] = $this->getAnimeTitle($name_area);
            }

            if (empty($result['id'])) {
                continue;
            }

            // --- UNCOMMENTED AND USING SIMPLIFIED HELPERS ---
            $result['genre'] = $this->getAnimeGenre($each_anime);
            $result['synopsis'] = $this->getAnimeSynopsis($each_anime);
            // Passing $details_container (derived from .information or $each_anime) to these
            $result['source'] = $this->getAnimeSource($details_container); 

            if ($this->_type == 'anime') {
                $result['producer'] = $this->getAnimeProducer($details_container); 
                $result['episode'] = $this->getAnimeEpisode($details_container);   
                $result['licensor'] = $this->getAnimeLicensor($each_anime); 
                $result['type'] = $this->getAnimeType($info_area); 
            } else { // Manga
                $result['author'] = $this->getAnimeProducer($details_container); 
                $result['volume'] = $this->getAnimeEpisode($details_container);  
                $result['serialization'] = $this->getAnimeLicensor($each_anime); 
            }

            $result['airing_start'] = $this->getAnimeStart($info_area); 
            $result['member'] = $this->getAnimeMember($info_area);     
            $result['score'] = $this->getAnimeScore($info_area);       
            // --- END UNCOMMENTED ---

            $data[] = $result;
        }
        return $data;
    }
}