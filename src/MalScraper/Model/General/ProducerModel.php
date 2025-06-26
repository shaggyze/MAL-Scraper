<?php

namespace MalScraper\Model\General;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * ProducerModel class.
 */
class ProducerModel extends MainModel
{
    // ... (Your existing __construct, __call are assumed to be working) ...
    // ... (getAnimeImage, getAnimeId, getAnimeTitle are assumed to be working as they were) ...

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

    public function __construct($type, $type2, $id, $page = 1, $parserArea = '#contentWrapper')
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
        // Based on your HTML: div.image img
        $imageContainer = $each_anime->find('div.image', 0);
        if ($imageContainer) {
            $imgTag = $imageContainer->find('img', 0);
            if ($imgTag && $imgTag->hasAttribute('data-src')) {
                return Helper::imageUrlCleaner($imgTag->getAttribute('data-src'));
            } elseif ($imgTag && $imgTag->hasAttribute('src')) {
                return Helper::imageUrlCleaner($imgTag->getAttribute('src'));
            }
        }
        return '';
    }

    private function getAnimeId($name_area) // YOUR_WORKING_ORIGINAL (with safety)
    {
        // $name_area is div.title
        $linkNode = $name_area->find('div.title-text h2.h2_anime_title a.link-title', 0);
        if (!$linkNode) $linkNode = $name_area->find('a',0); // Fallback

        if ($linkNode && isset($linkNode->href)) {
            $anime_id_parts = explode('/', $linkNode->href);
            // URL: https://myanimelist.net/anime/199/Sen_to_Chihiro_no_Kamikakushi -> ID is parts[4]
            if (isset($anime_id_parts[4]) && is_numeric($anime_id_parts[4])) {
                return $anime_id_parts[4];
            }
        }
        return '';
    }

    private function getAnimeTitle($name_area) // YOUR_WORKING_ORIGINAL (with safety)
    {
        // $name_area is div.title
        $linkNode = $name_area->find('div.title-text h2.h2_anime_title a.link-title', 0);
         if (!$linkNode) $linkNode = $name_area->find('a',0); // Fallback

        return ($linkNode && isset($linkNode->plaintext)) ? trim($linkNode->plaintext) : '';
    }

    /**
     * Get producer/studio name.
     * $properties_node is $each_anime->find('div.synopsis div.properties', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $properties_node
     *
     * @return array
     */
    private function getAnimeProducer($properties_node) // UPDATED
    {
        $producer = [];
        if (!$properties_node || !is_object($properties_node)) {
            return $producer;
        }

        $targetCaption = ($this->_type == 'anime') ? 'Studio' : 'Authors'; // HTML uses "Studio"
        // For manga, it would be "Authors", this part needs testing if you use it for manga.
        
        foreach ($properties_node->find('div.property') as $propDiv) {
            $captionNode = $propDiv->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                foreach ($propDiv->find('span.item a') as $each_producer_link) {
                    $temp_prod = [];
                    $temp_prod['id'] = $this->getAnimeProducerId($each_producer_link);
                    $temp_prod['name'] = $this->getAnimeProducerName($each_producer_link);
                    if (!empty($temp_prod['id']) || !empty($temp_prod['name'])) {
                       $producer[] = $temp_prod;
                    }
                }
                break; 
            }
        }
        return $producer;
    }

    private function getAnimeProducerId($each_producer_link) // YOUR_ORIGINAL (adjusted for current HTML)
    {
        if (!is_object($each_producer_link) || empty($each_producer_link->href)) return '';
        $prod_id_href = $each_producer_link->href; // e.g., /anime/producer/21/Studio_Ghibli
        $prod_id_parts = explode('/', rtrim($prod_id_href, '/'));
        
        // Anime Producer: /anime/producer/ID/Name -> ID is parts[3]
        if ($this->_type == 'anime') {
            if (isset($prod_id_parts[3]) && $prod_id_parts[1] == 'anime' && $prod_id_parts[2] == 'producer' && is_numeric($prod_id_parts[3])) {
                return $prod_id_parts[3];
            }
        } else { // manga authors: /people/ID/Name -> ID is parts[2]
            if (isset($prod_id_parts[2]) && $prod_id_parts[1] == 'people' && is_numeric($prod_id_parts[2])) {
                return $prod_id_parts[2];
            }
             // Manga magazines: /manga/magazine/ID/Name -> ID is parts[3]
            if (isset($prod_id_parts[3]) && $prod_id_parts[1] == 'manga' && $prod_id_parts[2] == 'magazine' && is_numeric($prod_id_parts[3])) {
                 return $prod_id_parts[3];
            }
        }
        return '';
    }

    private function getAnimeProducerName($each_producer_link) // YOUR_ORIGINAL (with safety)
    {
        return (is_object($each_producer_link) && isset($each_producer_link->plaintext)) ? trim($each_producer_link->plaintext) : '';
    }

    /**
     * Get anime episode count.
     * $prodsrc_info_node is $each_anime->find('div.prodsrc div.info', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $prodsrc_info_node
     *
     * @return string
     */
    private function getAnimeEpisode($prodsrc_info_node) // UPDATED
    {
        if (!$prodsrc_info_node || !is_object($prodsrc_info_node)) {
            return '';
        }
        // HTML: <span class="item"><span>1 ep</span>, ...</span>
        $spans = $prodsrc_info_node->find('span.item');
        foreach ($spans as $span) {
            if (isset($span->plaintext) && strpos($span->plaintext, 'ep') !== false) {
                 // Try to get the child span's text first if it exists
                $childSpan = $span->find('span',0);
                $textToParse = ($childSpan && isset($childSpan->plaintext)) ? $childSpan->plaintext : $span->plaintext;

                if (preg_match('/(\d+)\s*ep/', $textToParse, $matches)) {
                    return $matches[1];
                }
            }
        }
        return '';
    }

    /**
     * Get anime source.
     * $properties_node is $each_anime->find('div.synopsis div.properties', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $properties_node
     *
     * @return string
     */
    private function getAnimeSource($properties_node) // UPDATED
    {
        if (!$properties_node || !is_object($properties_node)) {
            return '';
        }
        $targetCaption = 'Source'; // HTML uses "Source"
        
        foreach ($properties_node->find('div.property') as $propDiv) {
            $captionNode = $propDiv->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                $itemNode = $propDiv->find('span.item', 0);
                if ($itemNode && isset($itemNode->plaintext)) {
                    return trim($itemNode->plaintext);
                }
                break;
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
    private function getAnimeGenre($each_anime) // UPDATED
    {
        $genres = [];
        if (!$each_anime || !is_object($each_anime)) return $genres;
        // HTML: <div class="genres js-genre"> <div class="genres-inner js-genre-inner"> <span class="genre"> <a ...>GenreName</a> </span> ... </div> </div>
        $genre_container = $each_anime->find('div.genres.js-genre div.genres-inner', 0);
        if ($genre_container) {
            foreach ($genre_container->find('span.genre a') as $genre_link) {
                if (isset($genre_link->plaintext)) {
                    $genres[] = trim($genre_link->plaintext);
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
    private function getAnimeSynopsis($each_anime) // UPDATED
    {
        if (!$each_anime || !is_object($each_anime)) return '';
        // HTML: <div class="synopsis js-synopsis"> <p class="preline">...</p> ... </div>
        $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0);
        if ($synopsis_container) {
            $paragraph_node = $synopsis_container->find('p.preline', 0);
            if ($paragraph_node && isset($paragraph_node->plaintext)) {
                // Remove "Read more" button if it affects plaintext (unlikely here as it's separate)
                // The <button> is outside <p>, so its text won't be part of p.preline's plaintext
                $text = trim($paragraph_node->plaintext);
                return trim(preg_replace("/([\s])+/", ' ', $text));
            } elseif (isset($synopsis_container->plaintext)) { // Fallback to whole container if <p> not found
                 $text = trim($synopsis_container->plaintext); // This might include button text
                 // Attempt to remove button text if needed, though button is usually not part of plaintext of parent easily
                 return trim(preg_replace("/([\s])+/", ' ', $text));
            }
        }
        return '';
    }

    /**
     * Get anime licensor OR Manga Serialization.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string|array
     */
    private function getAnimeLicensor($each_anime) // UPDATED
    {
        // Licensors are not present in the provided HTML sample for this item.
        // If they were, they'd likely be in the div.properties.
        // Your original logic for data-licensors on a span.licensors:
        if ($this->_type == 'anime') {
            $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0);
            if ($synopsis_container) {
                $licensor_node = $synopsis_container->find('span.licensors', 0); 
                if ($licensor_node && $licensor_node->hasAttribute('data-licensors')) {
                    $licensor_attr = $licensor_node->getAttribute('data-licensors');
                    return array_filter(explode(',', $licensor_attr));
                }
            }
            return []; // No licensors in sample, default to empty array for anime
        } else { // Manga (Serialization) - also not in sample
            $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0);
            if ($synopsis_container) {
                $serialization_link = $synopsis_container->find('.serialization a', 0);
                if ($serialization_link && isset($serialization_link->plaintext)) {
                    return trim($serialization_link->plaintext);
                }
            }
            return ''; // No serialization in sample
        }
    }

    /**
     * Get anime type (Movie, TV, etc.).
     * $prodsrc_info_node is $each_anime->find('div.prodsrc div.info', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $prodsrc_info_node
     *
     * @return string
     */
    private function getAnimeType($prodsrc_info_node) // UPDATED
    {
        if (!$prodsrc_info_node || !is_object($prodsrc_info_node)) {
            return '';
        }
        // HTML: <span class="item">Movie, 2001</span>
        $itemSpans = $prodsrc_info_node->find('span.item');
        if (isset($itemSpans[0]) && isset($itemSpans[0]->plaintext)) {
            $full_text = $itemSpans[0]->plaintext; // "Movie, 2001"
            $parts = explode(',', $full_text);
            return trim($parts[0]); // "Movie"
        }
        return '';
    }

    /**
     * Get anime start year.
     * $prodsrc_info_node is $each_anime->find('div.prodsrc div.info', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $prodsrc_info_node
     *
     * @return string
     */
    private function getAnimeStart($prodsrc_info_node) // UPDATED
    {
        if (!$prodsrc_info_node || !is_object($prodsrc_info_node)) {
            return '';
        }
        // HTML: <span class="item">Movie, 2001</span>
        // Or sometimes directly from hidden span: <span style="display: none;" class="js-start_date">20010720</span>
        // Let's prioritize the hidden span if available as it's more precise
        $hiddenStartDate = $prodsrc_info_node->parent()->parent()->find('div.title span.js-start_date', 0); // Navigate up to find it
        if ($hiddenStartDate && isset($hiddenStartDate->plaintext)) {
            $dateYMD = trim($hiddenStartDate->plaintext); // "20010720"
            if (strlen($dateYMD) == 8) {
                // You can format this if needed, e.g., YYYY-MM-DD
                // return substr($dateYMD, 0, 4) . '-' . substr($dateYMD, 4, 2) . '-' . substr($dateYMD, 6, 2);
                return $dateYMD; // Return raw YYYYMMDD for now
            }
        }

        // Fallback to parsing from visible text
        $itemSpans = $prodsrc_info_node->find('span.item');
        if (isset($itemSpans[0]) && isset($itemSpans[0]->plaintext)) {
            $full_text = $itemSpans[0]->plaintext; // "Movie, 2001"
            $parts = explode(',', $full_text);
            if (isset($parts[1])) {
                return trim($parts[1]); // "2001"
            }
        }
        return '';
    }

    /**
     * Get anime score.
     * $info_area_node is $each_anime->find('.information', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeScore($info_area_node) // UPDATED
    {
        if (!$info_area_node || !is_object($info_area_node)) {
            return 'N/A';
        }
        // HTML: <div class="scormem-item score score-label score-8" title="Score"> <i ...></i>8.77 </div>
        // Or hidden: <span style="display: none;" class="js-score">8.77</span>
        
        // Prioritize hidden js-score as it's cleaner
        $hiddenScore = $info_area_node->parent()->find('div.title span.js-score', 0); // Navigate up
        if ($hiddenScore && isset($hiddenScore->plaintext)) {
            $score_text = trim($hiddenScore->plaintext);
            return (is_numeric($score_text) && (float)$score_text >= 0) ? $score_text : 'N/A';
        }

        // Fallback to visible score
        $scoreNode = $info_area_node->find('div.scormem-item.score', 0);
        if ($scoreNode && isset($scoreNode->plaintext)) {
            $score_text = trim($scoreNode->plaintext); // "8.77" (might have icon text too)
             // Extract number part
            if(preg_match('/(\d+\.?\d*)/', $score_text, $matches)){
                return $matches[1];
            }
        }
        return 'N/A';
    }

    /**
     * Get anime member count.
     * $info_area_node is $each_anime->find('.information', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeMember($info_area_node) // UPDATED
    {
        if (!$info_area_node || !is_object($info_area_node)) {
            return '0';
        }
        // HTML: <div class="scormem-item member" title="Members"> <i ...></i>2.0M </div>
        // Or hidden: <span style="display: none;" class="js-members">1957516</span>

        // Prioritize hidden js-members as it's the raw number
        $hiddenMembers = $info_area_node->parent()->find('div.title span.js-members', 0); // Navigate up
        if ($hiddenMembers && isset($hiddenMembers->plaintext)) {
            return trim(preg_replace('/[^\d]/', '', $hiddenMembers->plaintext));
        }
        
        // Fallback to visible members
        $memberNode = $info_area_node->find('div.scormem-item.member', 0);
        if ($memberNode && isset($memberNode->plaintext)) {
            $memberText = trim($memberNode->plaintext); // "2.0M" (might have icon text)
            if (preg_match('/([\d\.,]+(?:K|M)?)/i', $memberText, $matches)) {
                $countStr = strtoupper(str_replace(',', '', $matches[1]));
                $count = 0;
                if (strpos($countStr, 'M') !== false) {
                    $count = (float)str_replace('M', '', $countStr) * 1000000;
                } elseif (strpos($countStr, 'K') !== false) {
                    $count = (float)str_replace('K', '', $countStr) * 1000;
                } else {
                    $count = (float)$countStr;
                }
                return (string)(int)$count;
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
        // YOUR ORIGINAL SELECTOR FOR THE LIST OF ITEMS.
        // Based on your sample, each item starts with:
        // <div class="js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3" ...>
        // So, this selector should be correct for fetching the items.
        $anime_table = $this->_parser->find('js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3');
var_dump(count($anime_table));
        foreach ($anime_table as $each_anime) {
            if(!is_object($each_anime)) continue; 

            $result = [];

            // From your working code:
            $name_area = $each_anime->find('div.title', 0); // This gets <div class="title">

            // For producer, source: these are in div.synopsis > div.properties
            $properties_node = $each_anime->find('div.synopsis div.properties', 0);

            // For episode, type, aired year: these are in div.prodsrc > div.info
            $prodsrc_info_node = $each_anime->find('div.prodsrc div.info', 0);
            
            // For score, members: these are in div.information (which is $info_area in your old code)
            $info_area = $each_anime->find('div.information', 0);


            // These are working as per your statement (with safety checks)
            $result['image'] = ''; $result['id'] = ''; $result['title'] = ''; // Initialize
            if ($each_anime) $result['image'] = $this->getAnimeImage($each_anime);
            if ($name_area && is_object($name_area)) {
                $result['id'] = $this->getAnimeId($name_area);
                $result['title'] = $this->getAnimeTitle($name_area);
            }
var_dump($result['id']);
            if (empty($result['id'])) {
                continue;
            }

            // --- UNCOMMENTED AND USING UPDATED HELPERS BASED ON PROVIDED HTML ---
            $result['genre'] = $this->getAnimeGenre($each_anime);
            $result['synopsis'] = $this->getAnimeSynopsis($each_anime);
            $result['source'] = $this->getAnimeSource($properties_node); 

            if ($this->_type == 'anime') {
                $result['producer'] = $this->getAnimeProducer($properties_node); 
                $result['episode'] = $this->getAnimeEpisode($prodsrc_info_node);   
                $result['licensor'] = $this->getAnimeLicensor($each_anime); // Licensor not in sample, uses old logic
                $result['type'] = $this->getAnimeType($prodsrc_info_node); 
            } else { // Manga
                // Adapt these if using for manga, using anime logic as placeholder
                $result['author'] = $this->getAnimeProducer($properties_node); 
                $result['volume'] = $this->getAnimeEpisode($prodsrc_info_node);  
                $result['serialization'] = $this->getAnimeLicensor($each_anime); 
            }

            $result['airing_start'] = $this->getAnimeStart($prodsrc_info_node); 
            $result['member'] = $this->getAnimeMember($info_area);     
            $result['score'] = $this->getAnimeScore($info_area);       
            // --- END UNCOMMENTED ---

            $data[] = $result;
        }
        return $data;
    }
}