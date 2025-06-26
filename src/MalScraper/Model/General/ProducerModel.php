<?php

namespace MalScraper\Model\General;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * ProducerModel class.
 */
class ProducerModel extends MainModel
{
    private $_type;
    private $_type2;
    private $_id;
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

    // --- YOUR WORKING ORIGINAL FUNCTIONS (with added safety) ---
    private function getAnimeImage($each_anime)
    {
        if (!is_object($each_anime)) return '';
        // "Mononoke Hime" HTML: div.image img
        $imageDiv = $each_anime->find('div.image', 0);
        if ($imageDiv && is_object($imageDiv)) {
            $imgTag = $imageDiv->find('img', 0);
            if ($imgTag && is_object($imgTag)) {
                if ($imgTag->hasAttribute('data-src')) {
                    return Helper::imageUrlCleaner($imgTag->getAttribute('data-src'));
                } elseif ($imgTag->hasAttribute('src')) {
                    return Helper::imageUrlCleaner($imgTag->getAttribute('src'));
                }
            }
        }
        return '';
    }

    private function getAnimeId($name_area)
    {
        if (!$name_area || !is_object($name_area)) return '';
        // "Mononoke Hime" HTML: div.title > a
        $linkNode = $name_area->find('a', 0);
        if ($linkNode && is_object($linkNode) && isset($linkNode->href)) {
            $anime_id_parts = explode('/', $linkNode->href);
            if (isset($anime_id_parts[4]) && is_numeric($anime_id_parts[4])) {
                return $anime_id_parts[4];
            }
        }
        return '';
    }

    private function getAnimeTitle($name_area)
    {
        if (!$name_area || !is_object($name_area)) return '';
        $node = $name_area->find('a', 0);
        return ($node && is_object($node) && isset($node->plaintext)) ? trim($node->plaintext) : '';
    }

    private function getAnimeProducerId($each_producer_link)
    {
        if (!is_object($each_producer_link) || empty($each_producer_link->href)) return '';
        $prod_id_href = $each_producer_link->href;
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
        }
        return '';
    }

    private function getAnimeProducerName($each_producer_link)
    {
        return (is_object($each_producer_link) && isset($each_producer_link->plaintext)) ? trim($each_producer_link->plaintext) : '';
    }
    // --- END OF YOUR WORKING ORIGINAL FUNCTIONS ---

    // --- HELPER FUNCTIONS FOR PREVIOUSLY COMMENTED-OUT FIELDS ---
    // --- Adapted for "Mononoke Hime" simpler card structure where possible ---
    // --- or gracefully returning empty if data isn't in that simple card ---

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return array
     */
    private function getAnimeProducer($each_anime) // Was line 142 problem
    {
        $producer = [];
        if (!is_object($each_anime)) return $producer;

        // "Mononoke Hime" sample doesn't list studios clearly in this card.
        // "Sen to Chihiro" sample had: div.synopsis div.properties > div.property (caption "Studio")
        $properties_node = $each_anime->find('div.synopsis div.properties', 0);
        
        if ($properties_node && is_object($properties_node)) { // Check if $properties_node was found
            $targetCaption = ($this->_type == 'anime') ? 'Studio' : 'Authors';
            foreach ($properties_node->find('div.property') as $propDiv) { // This was line 142
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && is_object($captionNode) && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                    foreach ($propDiv->find('span.item a') as $link) {
                        if (!is_object($link)) continue;
                        $temp_prod = [];
                        $temp_prod['id'] = $this->getAnimeProducerId($link);
                        $temp_prod['name'] = $this->getAnimeProducerName($link);
                        if (!empty($temp_prod['id']) || !empty($temp_prod['name'])) {
                           $producer[] = $temp_prod;
                        }
                    }
                    break; 
                }
            }
        }
        return $producer; // Will be empty if structure not found
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string
     */
    private function getAnimeEpisode($each_anime)
    {
        if (!is_object($each_anime)) return '';
        // "Mononoke Hime" sample does not have episode count in this card view.
        // "Sen to Chihiro" sample had: div.prodsrc div.info > span.item > span > "1 ep"
        $prodsrc_info_node = $each_anime->find('div.prodsrc div.info', 0);
        if ($prodsrc_info_node && is_object($prodsrc_info_node)) {
            $spans = $prodsrc_info_node->find('span.item');
            foreach ($spans as $span) {
                if (is_object($span) && isset($span->plaintext) && strpos($span->plaintext, 'ep') !== false) {
                    $childSpan = $span->find('span',0);
                    $textToParse = ($childSpan && is_object($childSpan) && isset($childSpan->plaintext)) ? $childSpan->plaintext : $span->plaintext;
                    if (preg_match('/(\d+)\s*ep/', $textToParse, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
        return ''; // Will be empty if structure not found
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string
     */
    private function getAnimeSource($each_anime) // Was line 207, 209 problem
    {
        if (!is_object($each_anime)) return '';
        // "Mononoke Hime" sample doesn't list source clearly in this card.
        // "Sen to Chihiro" sample had: div.synopsis div.properties > div.property (caption "Source")
        $properties_node = $each_anime->find('div.synopsis div.properties', 0);
        
        if ($properties_node && is_object($properties_node)) {
            $targetCaption = ($this->_type == 'anime') ? 'Source' : 'Type';
            foreach ($properties_node->find('div.property') as $propDiv) {
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && is_object($captionNode) && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                    $itemNode = $propDiv->find('span.item', 0);
                    if ($itemNode && is_object($itemNode) && isset($itemNode->plaintext)) { // LINE 207 was effectively here
                        return trim($itemNode->plaintext); // LINE 209 was this return
                    }
                    break; 
                }
            }
        }
        return ''; // Will be empty if structure not found
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return array
     */
    private function getAnimeGenre($each_anime) // Was line 223 problem
    {
        $genres = [];
        if (!is_object($each_anime)) return $genres;

        // "Mononoke Hime" sample has data-genre attribute, no text links.
        // "Sen to Chihiro" sample had: div.genres.js-genre > div.genres-inner > span.genre > a
        $genre_container_outer = $each_anime->find('div.genres.js-genre', 0); 

        if ($genre_container_outer && is_object($genre_container_outer)) {
            $genre_container_inner = $genre_container_outer->find('div.genres-inner', 0); 
            if ($genre_container_inner && is_object($genre_container_inner)) { // This was line 223 logic
                $links = $genre_container_inner->find('span.genre a');                 
                foreach ($links as $each_genre_link) {
                    if (is_object($each_genre_link) && isset($each_genre_link->plaintext)) {
                        $genres[] = trim($each_genre_link->plaintext);
                    }
                }
            }
        }
        return $genres; // Will be empty for Mononoke Hime type cards
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string
     */
    private function getAnimeSynopsis($each_anime) // Was line 278, 280 problem
    {
        if (!is_object($each_anime)) return '';
        // "Mononoke Hime" sample doesn't have synopsis in this card view.
        // "Sen to Chihiro" sample had: div.synopsis.js-synopsis > p.preline
        $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0);
        
        if ($synopsis_container && is_object($synopsis_container)) {
            $paragraph_node = $synopsis_container->find('p.preline', 0);
            if ($paragraph_node && is_object($paragraph_node) && isset($paragraph_node->plaintext)) { // LINE 278: Accessing plaintext
                $text = trim($paragraph_node->plaintext);
                return trim(preg_replace("/([\s])+/", ' ', $text)); // LINE 280: preg_replace
            }
        }
        return ''; // Will be empty if structure not found
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string|array
     */
    private function getAnimeLicensor($each_anime)
    {
        // This uses your original logic, which looks for specific structures.
        // Likely empty for "Mononoke Hime" sample.
        if (!is_object($each_anime)) return ($this->_type == 'anime') ? [] : '';
        if ($this->_type == 'anime') {
            $synopsisNode = $each_anime->find('div[class="synopsis js-synopsis"]', 0);
            if ($synopsisNode && is_object($synopsisNode)) {
                $licensorNode = $synopsisNode->find('.licensors', 0);
                if ($licensorNode && is_object($licensorNode) && $licensorNode->hasAttribute('data-licensors')) {
                    return array_filter(explode(',', $licensorNode->getAttribute('data-licensors')));
                }
            }
            return [];
        } else {
            $synopsisNode = $each_anime->find('div[class="synopsis js-synopsis"]', 0);
            if ($synopsisNode && is_object($synopsisNode)) {
                $serializationNode = $synopsisNode->find('.serialization a', 0);
                if ($serializationNode && is_object($serializationNode) && isset($serializationNode->plaintext)) {
                    return trim($serializationNode->plaintext);
                }
            }
            return '';
        }
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string
     */
    private function getAnimeType($each_anime)
    {
        if (!is_object($each_anime)) return '';
        // "Mononoke Hime" sample doesn't have type explicitly here. It's inferred.
        // "Sen to Chihiro" sample had: div.prodsrc div.info > span.item (first one like "Movie, 2001")
        $prodsrc_info_node = $each_anime->find('div.prodsrc div.info', 0);
        if ($prodsrc_info_node && is_object($prodsrc_info_node)) {
            $itemSpans = $prodsrc_info_node->find('span.item'); // Get all item spans
            if (isset($itemSpans[0]) && is_object($itemSpans[0]) && isset($itemSpans[0]->plaintext)) {
                $full_text = $itemSpans[0]->plaintext; // e.g., "Movie, 2001" or "TV, 2023"
                $parts = explode(',', $full_text);
                return trim($parts[0]); // "Movie" or "TV"
            }
        }
        return ''; // Will be empty if structure not found
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string
     */
    private function getAnimeStart($each_anime)
    {
        if (!is_object($each_anime)) return '';
        // "Mononoke Hime" sample: <span style="display: none;" class="js-start_date">19970712</span>
        $jsStartDate = $each_anime->find('span.js-start_date', 0);
        if ($jsStartDate && is_object($jsStartDate) && isset($jsStartDate->plaintext)) {
            return trim($jsStartDate->plaintext); // "19970712"
        }
        return ''; // Will be empty if not found
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string
     */
    private function getAnimeScore($each_anime)
    {
        if (!is_object($each_anime)) return 'N/A';
        // "Mononoke Hime" sample: <span style="display: none;" class="js-score">8.67</span>
        // Also: <div class="widget"><div class="stars">8.67...</div></div>
        $jsScore = $each_anime->find('span.js-score', 0);
        if ($jsScore && is_object($jsScore) && isset($jsScore->plaintext)) {
            $score = trim($jsScore->plaintext);
            return (is_numeric($score) && (float)$score >= 0) ? $score : 'N/A';
        }
        // Fallback to div.widget div.stars (less reliable due to possible icon text)
        $widget = $each_anime->find('div.widget', 0);
        if ($widget && is_object($widget)) {
            $starsNode = $widget->find('div.stars', 0);
            if ($starsNode && is_object($starsNode) && isset($starsNode->plaintext)) {
                if(preg_match('/(\d+\.?\d*)/', $starsNode->plaintext, $matches)){ // Extract numeric part
                    return $matches[1];
                }
            }
        }
        return 'N/A';
    }

    /**
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     * @return string
     */
    private function getAnimeMember($each_anime)
    {
        if (!is_object($each_anime)) return '0';
        // "Mononoke Hime" sample: <span style="display: none;" class="js-members">1330402</span>
        // Also: <div class="widget">...<div class="users">1,330,402...</div></div>
        $jsMembers = $each_anime->find('span.js-members', 0);
        if ($jsMembers && is_object($jsMembers) && isset($jsMembers->plaintext)) {
            return trim(preg_replace('/[^\d]/', '', $jsMembers->plaintext));
        }
        // Fallback to div.widget div.users
        $widget = $each_anime->find('div.widget', 0);
        if ($widget && is_object($widget)) {
            $usersNode = $widget->find('div.users', 0);
            if ($usersNode && is_object($usersNode) && isset($usersNode->plaintext)) {
                return trim(preg_replace('/[^\d]/', '', $usersNode->plaintext));
            }
        }
        return '0';
    }

    private function getAllInfo()
    {
        if (!$this->_parser || !is_object($this->_parser)) {
            $this->_error = ['error' => 'Parser not initialized or invalid.'];
            return $this->_error;
        }

        $data = [];
        // YOUR ORIGINAL WORKING SELECTOR FOR THE LIST OF ITEMS
        $anime_table = $this->_parser->find('div[class="js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3"]');

        foreach ($anime_table as $each_anime) {
            if(!is_object($each_anime)) continue; 

            $result = [];

            // YOUR ORIGINAL SUB-SELECTOR for name_area
            $name_area = $each_anime->find('div[class=title]', 0);
            
            // $producer_area was 'div[class=property"]' - this is invalid syntax.
            // We are now passing $each_anime to helpers that need more context.
            // $info_area was $each_anime->find('.information', 0)
            // For Mononoke Hime sample, '.information' doesn't exist at item root.
            // Score/Member helpers now take $each_anime directly.

            // These are working as per your statement
            $result['image'] = ''; $result['id'] = ''; $result['title'] = '';
            $result['image'] = $this->getAnimeImage($each_anime); // Uses $each_anime
            if ($name_area && is_object($name_area)) {
                $result['id'] = $this->getAnimeId($name_area);     // Uses $name_area
                $result['title'] = $this->getAnimeTitle($name_area); // Uses $name_area
            }

            if (empty($result['id'])) {
                continue; 
            }

            // --- UNCOMMENTED AND CALLING HELPERS ---
            // Most helpers now take $each_anime directly.
            $result['genre'] = $this->getAnimeGenre($each_anime);
            $result['synopsis'] = $this->getAnimeSynopsis($each_anime);
            $result['source'] = $this->getAnimeSource($each_anime);

            if ($this->_type == 'anime') {
                $result['producer'] = $this->getAnimeProducer($each_anime);
                $result['episode'] = $this->getAnimeEpisode($each_anime);
                $result['licensor'] = $this->getAnimeLicensor($each_anime); 
                $result['type'] = $this->getAnimeType($each_anime);
            } else { 
                $result['author'] = $this->getAnimeProducer($each_anime); 
                $result['volume'] = $this->getAnimeEpisode($each_anime);  
                $result['serialization'] = $this->getAnimeLicensor($each_anime); 
            }

            $result['airing_start'] = $this->getAnimeStart($each_anime);
            $result['member'] = $this->getAnimeMember($each_anime); // Takes $each_anime
            $result['score'] = $this->getAnimeScore($each_anime);   // Takes $each_anime
            
            $data[] = $result;
        }
        return $data;
    }
}