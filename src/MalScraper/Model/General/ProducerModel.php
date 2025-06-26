<?php

namespace MalScraper\Model\General;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;

/**
 * ProducerModel class.
 */
class ProducerModel extends MainModel
{
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

    /**
     * Default constructor.
     *
     * @param string     $type
     * @param string     $type2
     * @param string|int $id
     * @param string     $parserArea
     *
     * @return void
     */
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
        // If getAllInfo is the intended primary method.
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

    /**
     * Get anime image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeImage($each_anime) // YOUR WORKING ORIGINAL
    {
        // For safety, ensure find results exist before accessing methods/attributes
        $imageDiv = $each_anime->find('div[class=image]', 0);
        if ($imageDiv) {
            $imgTag = $imageDiv->find('img', 0);
            if ($imgTag && $imgTag->hasAttribute('data-src')) {
                return Helper::imageUrlCleaner($imgTag->getAttribute('data-src'));
            }
        }
        return '';
    }

    /**
     * Get anime id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $name_area
     *
     * @return string
     */
    private function getAnimeId($name_area) // YOUR WORKING ORIGINAL
    {
        $linkNode = $name_area->find('a', 0);
        if ($linkNode && isset($linkNode->href)) {
            $anime_id_parts = explode('/', $linkNode->href);
            // Ensure index 4 exists and is numeric
            if (isset($anime_id_parts[4]) && is_numeric($anime_id_parts[4])) {
                return $anime_id_parts[4];
            }
        }
        return '';
    }

    /**
     * Get anime title.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $name_area
     *
     * @return string
     */
    private function getAnimeTitle($name_area) // YOUR WORKING ORIGINAL
    {
        $node = $name_area->find('a', 0);
        return ($node && isset($node->plaintext)) ? trim($node->plaintext) : '';
    }

    /**
     * Get producer name.
     * $properties_container_node is assumed to be $each_anime->find('div.properties', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $properties_container_node
     *
     * @return array
     */
    private function getAnimeProducer($properties_container_node) // TARGET FOR UPDATE
    {
        $producer = [];
        if (!$properties_container_node || !is_object($properties_container_node)) {
            return $producer;
        }

        $targetCaption = ($this->_type == 'anime') ? 'Studios:' : 'Authors:';
        
        foreach ($properties_container_node->find('div.property') as $propDiv) {
            $captionNode = $propDiv->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                // Producers/authors are usually links within span.item
                foreach ($propDiv->find('span.item a') as $each_producer_link) {
                    $temp_prod = [];
                    // Using your original helper methods for ID and Name
                    $temp_prod['id'] = $this->getAnimeProducerId($each_producer_link);
                    $temp_prod['name'] = $this->getAnimeProducerName($each_producer_link);
                    if (!empty($temp_prod['id']) || !empty($temp_prod['name'])) { // Allow if at least one is found
                       $producer[] = $temp_prod;
                    }
                }
                break; // Found the studios/authors block
            }
        }
        return $producer;
    }

    /**
     * Get producer id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_producer_link (the <a> tag)
     *
     * @return string
     */
    private function getAnimeProducerId($each_producer_link) // YOUR ORIGINAL
    {
        if (!is_object($each_producer_link) || empty($each_producer_link->href)) return '';
        $prod_id_href = $each_producer_link->href;
        $prod_id_parts = explode('/', rtrim($prod_id_href, '/'));
        
        // MAL URL structures:
        // Anime Producer: /anime/producer/ID/Name
        // Manga Author:   /people/ID/Name
        if ($this->_type == 'anime') {
            if (isset($prod_id_parts[3]) && $prod_id_parts[1] == 'anime' && $prod_id_parts[2] == 'producer' && is_numeric($prod_id_parts[3])) {
                return $prod_id_parts[3];
            }
        } else { // manga
            if (isset($prod_id_parts[2]) && $prod_id_parts[1] == 'people' && is_numeric($prod_id_parts[2])) {
                return $prod_id_parts[2];
            }
            // Your original used $prod_id_parts[4] for manga, which implies a different structure
            // If it was for manga magazines (as "producer"): /manga/magazine/ID/Name
            if (isset($prod_id_parts[3]) && $prod_id_parts[1] == 'manga' && $prod_id_parts[2] == 'magazine' && is_numeric($prod_id_parts[3])) {
                 return $prod_id_parts[3];
            }
        }
        return ''; // Fallback
    }

    /**
     * Get producer name.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_producer_link (the <a> tag)
     *
     * @return string
     */
    private function getAnimeProducerName($each_producer_link) // YOUR ORIGINAL
    {
        return (is_object($each_producer_link) && isset($each_producer_link->plaintext)) ? trim($each_producer_link->plaintext) : '';
    }

    /**
     * Get anime episode.
     * $properties_container_node is assumed to be $each_anime->find('div.properties', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $properties_container_node
     *
     * @return string
     */
    private function getAnimeEpisode($properties_container_node) // TARGET FOR UPDATE
    {
        if (!$properties_container_node || !is_object($properties_container_node)) {
            return '';
        }

        $targetCaption = ($this->_type == 'anime') ? 'Episodes:' : 'Volumes:';
        // For manga, "Chapters:" might also be relevant if "Volumes:" isn't present.
        
        foreach ($properties_container_node->find('div.property') as $propDiv) {
            $captionNode = $propDiv->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                $itemNode = $propDiv->find('span.item', 0);
                if ($itemNode && isset($itemNode->plaintext)) {
                    $text = trim($itemNode->plaintext);
                    // Attempt to extract only the number from "XX eps" or "XX vols" or "XX"
                    if (preg_match('/^(\d+)/', $text, $matches)) {
                        return $matches[1];
                    }
                    return $text; // Return "Unknown" or other text as is
                }
                break; 
            }
        }
        // Your original logic was: $episode = $producer_area->find('div[class=eps]', 0)->plaintext;
        // The div.eps is usually not inside div.properties. It's often in a div.meta.
        // To attempt something similar if $properties_container_node was actually $each_anime:
        // $epsNode = $properties_container_node->find('div.meta span.eps', 0); // More common modern location for this old class
        // if ($epsNode && isset($epsNode->plaintext)) {
        //    return trim(str_replace(['eps', 'ep', 'vols', 'vol', '(', ')'], '', $epsNode->plaintext));
        // }
        return ''; // If not found in properties
    }

    /**
     * Get anime source.
     * $properties_container_node is assumed to be $each_anime->find('div.properties', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $properties_container_node
     *
     * @return string
     */
    private function getAnimeSource($properties_container_node) // TARGET FOR UPDATE
    {
        if (!$properties_container_node || !is_object($properties_container_node)) {
            return '';
        }

        $targetCaption = ($this->_type == 'anime') ? 'Source:' : 'Type:'; // For manga, "Type" (e.g., Manga, Manhwa) is analogous
        
        foreach ($properties_container_node->find('div.property') as $propDiv) {
            $captionNode = $propDiv->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                $itemNode = $propDiv->find('span.item', 0); // Source/Type is usually not a link
                if ($itemNode && isset($itemNode->plaintext)) {
                    return trim($itemNode->plaintext);
                }
                break;
            }
        }
        // Your original logic: $source = $producer_area->find('span[class=item]', 0)->plaintext;
        // This is too generic. It would just grab the first span.item in $properties_container_node.
        // If the above structured search fails, this generic grab is unlikely to be correct for "Source".
        return '';
    }

    /**
     * Get anime genre.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return array
     */
    private function getAnimeGenre($each_anime) // TARGET FOR UPDATE
    {
        $genres = [];
        // Modern MAL: div.genres.js-genre which has span.genre > a
        // Your original selector: $genre_area = $each_anime->find('div[class="genres-inner js-genre-inner"]', 0);
        $genre_container = $each_anime->find('div.genres.js-genre', 0); // Modern and common
        if (!$genre_container) {
            // Fallback to a structure that might match your original intent
             $genre_container = $each_anime->find('div.genres-inner.js-genre-inner', 0);
        }

        if ($genre_container) {
            // Genres are links, often inside a span.genre or directly as <a>
            $genre_links = $genre_container->find('span.genre a'); // Try specific first
            if (empty($genre_links)) {
                 $genre_links = $genre_container->find('a'); // Fallback to direct <a> tags
            }
             // Your original was 'span a', if the above two fail, this might be closer to what worked if structure was different.
            if (empty($genre_links)) {
                $genre_links = $genre_container->find('span a');
            }

            foreach ($genre_links as $each_genre_link) {
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
    private function getAnimeSynopsis($each_anime) // TARGET FOR UPDATE
    {
        // Your original selector: $synopsis_container = $each_anime->find('div[class="synopsis js-synopsis"]', 0);
        $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0); // Standard class for synopsis container
        
        if ($synopsis_container) {
            // Modern MAL often has the text within a div.text or span.preline
            $text_node = $synopsis_container->find('div.text', 0);
            if (!$text_node) {
                $text_node = $synopsis_container->find('span.preline', 0); // For older items
            }
            if (!$text_node) { // If neither of those, use the synopsis container itself for plaintext
                $text_node = $synopsis_container;
            }
            
            if ($text_node && isset($text_node->plaintext)) {
                // Remove "Read more" links if they exist within the text node
                foreach ($text_node->find('a') as $a_tag) {
                    if (isset($a_tag->plaintext) && strtolower(trim($a_tag->plaintext)) === 'read more') {
                        $a_tag->outertext = ''; // Remove the link by clearing its outer HTML
                    }
                }
                // Re-fetch plaintext after modification, as outertext='' changes the DOM
                $current_text = isset($text_node->plaintext) ? trim($text_node->plaintext) : '';
                return trim(preg_replace("/([\s])+/", ' ', $current_text));
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
    private function getAnimeLicensor($each_anime) // TARGET FOR UPDATE
    {
        if ($this->_type == 'anime') {
            // Modern MAL: Licensors usually listed in div.properties
            $properties_container = $each_anime->find('div.properties', 0);
            if ($properties_container) {
                foreach ($properties_container->find('div.property') as $propDiv) {
                    $captionNode = $propDiv->find('span.caption', 0);
                    if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == 'Licensors:') {
                        $licensors = [];
                        foreach ($propDiv->find('span.item a') as $licensorLink) { // Licensors are usually links
                            if (isset($licensorLink->plaintext)) $licensors[] = trim($licensorLink->plaintext);
                        }
                        if (!empty($licensors)) return array_filter($licensors); // Return if found here
                    }
                }
            }
            // Fallback to your original logic (data-licensors attribute):
            $licensorNode = $each_anime->find('div.synopsis.js-synopsis span.licensors', 0); // Your old: .licensors
            if ($licensorNode && $licensorNode->hasAttribute('data-licensors')) {
                $licensorAttr = $licensorNode->getAttribute('data-licensors');
                return array_filter(explode(',', $licensorAttr));
            }
            return []; // Default for anime if nothing found
        } else { // Manga (Serialization)
            // Modern MAL: Serialization usually listed in div.properties
            $properties_container = $each_anime->find('div.properties', 0);
            if ($properties_container) {
                foreach ($properties_container->find('div.property') as $propDiv) {
                    $captionNode = $propDiv->find('span.caption', 0);
                    if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == 'Serialization:') {
                        $itemNode = $propDiv->find('span.item a', 0); // Serialization usually a link
                        if (!$itemNode) $itemNode = $propDiv->find('span.item', 0); // Fallback if not a link
                        if ($itemNode && isset($itemNode->plaintext)) return trim($itemNode->plaintext);
                    }
                }
            }
            // Fallback to your original logic:
            // Your old selector was: $each_anime->find('div[class="synopsis js-synopsis"] .serialization a', 0)
            $serializationNode = $each_anime->find('div.synopsis.js-synopsis span.serialization a', 0); // Try span first
            if (!$serializationNode) { // Then try div as per your original comment
                 $serializationNode = $each_anime->find('div.synopsis.js-synopsis div.serialization a', 0);
            }
            return ($serializationNode && isset($serializationNode->plaintext)) ? trim($serializationNode->plaintext) : '';
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
    private function getAnimeType($info_area_node) // TARGET FOR UPDATE
    {
        if (!$info_area_node || !is_object($info_area_node)) {
            return '';
        }
        
        // Modern MAL: "Type: TV" etc. is in div.properties.
        // We need to check if $info_area_node contains div.properties, or if we need $each_anime.
        // Assuming $info_area_node is a general container that might include properties.
        $properties_container = $info_area_node->find('div.properties', 0);
        if ($properties_container) { // If properties are inside $info_area_node
             foreach ($properties_container->find('div.property') as $propDiv) {
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == 'Type:') {
                    $itemNode = $propDiv->find('span.item a', 0); // Type is often a link (e.g., to /topanime.php?type=tv)
                    if (!$itemNode) $itemNode = $propDiv->find('span.item', 0); // Fallback if not a link
                    if ($itemNode && isset($itemNode->plaintext)) {
                        return trim($itemNode->plaintext);
                    }
                    break; 
                }
            }
        }

        // Fallback to your original logic for type:
        $infoDiv = $info_area_node->find('div.info', 0); // Your original selector: .info
        if ($infoDiv && isset($infoDiv->plaintext)) {
            $type_parts = explode('-', $infoDiv->plaintext); // Your original processing
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
    private function getAnimeStart($info_area_node) // TARGET FOR UPDATE
    {
        if (!$info_area_node || !is_object($info_area_node)) {
            return '';
        }

        // Modern MAL: "Aired: Date" or "Published: Date" in div.properties
        $properties_container = $info_area_node->find('div.properties', 0);
        if ($properties_container) {
            $targetCaption = ($this->_type == 'anime') ? 'Aired:' : 'Published:';
            foreach ($properties_container->find('div.property') as $propDiv) {
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                    $itemNode = $propDiv->find('span.item', 0);
                    if ($itemNode && isset($itemNode->plaintext)) {
                        $dateText = trim($itemNode->plaintext);
                        // Get only start date if it's a range "Date1 to Date2"
                        $parts = explode(' to ', $dateText);
                        return trim($parts[0]);
                    }
                    break;
                }
            }
        }
        
        // Fallback to your original logic:
        $remainTimeNode = $info_area_node->find('div.info span.remain-time', 0); // Your original: .info .remain-time
        if ($remainTimeNode && isset($remainTimeNode->plaintext)) {
            return trim($remainTimeNode->plaintext);
        }
        return '';
    }

    /**
     * Get anime score. (Name changed from getAnimeMember to getAnimeScore as per your original commented out line)
     * $info_area is $each_anime->find('.information', 0);
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeScore($info_area_node) // TARGET FOR UPDATE
    {
        if (!$info_area_node || !is_object($info_area_node)) {
            return 'N/A';
        }

        // Modern MAL: score is in div.score.score-label, usually a direct child of the item card ($each_anime)
        // or its body, not necessarily deep inside a generic '.information' div.
        // Let's attempt to find it if $info_area_node IS the item card or a close parent.
        $scoreLabelNode = $info_area_node->find('div.score.score-label', 0);
        if ($scoreLabelNode && isset($scoreLabelNode->plaintext)) {
            $scoreText = trim($scoreLabelNode->plaintext);
            return (is_numeric($scoreText) && (float)$scoreText > 0) ? $scoreText : 'N/A';
        }

        // Fallback to your original logic:
        $scoreNode = $info_area_node->find('div.scormem span.score', 0); // Your original: .scormem .score
        if ($scoreNode && isset($scoreNode->plaintext)) {
            $score_text = trim($scoreNode->plaintext);
            return (is_numeric($score_text) && (float)$score_text > 0) ? $score_text : 'N/A';
        }
        return 'N/A';
    }

    /**
     * Get anime member count. (Name changed from getAnimeScore to getAnimeMember as per your original commented out line)
     * $info_area is $each_anime->find('.information', 0);
     *
     * @param \simplehtmldom_1_5\simple_html_dom $info_area_node
     *
     * @return string
     */
    private function getAnimeMember($info_area_node) // TARGET FOR UPDATE
    {
        if (!$info_area_node || !is_object($info_area_node)) {
            return '0';
        }
        
        // Modern MAL: members count in div.scormem span.member (similar to your original)
        // Your original logic:
        $memberNode = $info_area_node->find('div.scormem span[class^=member]', 0);
        if ($memberNode && isset($memberNode->plaintext)) {
            $memberText = trim($memberNode->plaintext);
            // Extract number from "1.23M members" or "1,234,567 users"
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
            // Fallback just stripping non-digits if K/M parsing fails
            return preg_replace('/[^\d]/', '', $memberText);
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
        // Guard for $this->_parser
        if (!$this->_parser || !is_object($this->_parser)) {
            $this->_error = ['error' => 'Parser not initialized or invalid.'];
            return $this->_error; // Return the error array directly
        }

        $data = [];
        // YOUR ORIGINAL SELECTOR FOR THE LIST OF ITEMS
        $anime_table = $this->_parser->find('div[class="js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3"]');

        // Fallback if your original selector returns nothing
        if (empty($anime_table) || !is_array($anime_table)) {
            $container = $this->_parser->find('div.js-categories-seasonal',0);
            if(!$container) $container = $this->_parser; // Fallback to whole parser area
            $anime_table = $container->find('div.seasonal-anime.js-seasonal-anime');
        }


        foreach ($anime_table as $each_anime) {
            if(!is_object($each_anime)) continue; // Ensure $each_anime is an object for find()

            $result = [];

            // YOUR ORIGINAL SUB-SELECTORS:
            $name_area = $each_anime->find('div[class=title]', 0);
            // $producer_area had a syntax error. Corrected to $properties_container.
            // This assumes $producer_area was meant to be the container of item properties.
            $properties_container = $each_anime->find('div.properties', 0); 
            $info_area = $each_anime->find('.information', 0);

            // These are working as per your statement
            $result['image'] = $this->getAnimeImage($each_anime);
            if ($name_area && is_object($name_area)) { // Added is_object check
                $result['id'] = $this->getAnimeId($name_area);
                $result['title'] = $this->getAnimeTitle($name_area);
            } else {
                $result['id'] = ''; // Default if name_area not found
                $result['title'] = '';
            }

            // If ID is empty, it's likely not a valid item or parsing failed for critical part, skip.
            if (empty($result['id'])) {
                continue;
            }

            // --- UNCOMMENTED AND USING UPDATED HELPERS ---
            $result['genre'] = $this->getAnimeGenre($each_anime);
            $result['synopsis'] = $this->getAnimeSynopsis($each_anime);
            $result['source'] = $this->getAnimeSource($properties_container); // Pass $properties_container

            if ($this->_type == 'anime') {
                $result['producer'] = $this->getAnimeProducer($properties_container); // Pass $properties_container
                $result['episode'] = $this->getAnimeEpisode($properties_container);   // Pass $properties_container
                $result['licensor'] = $this->getAnimeLicensor($each_anime); // Needs $each_anime
                $result['type'] = $this->getAnimeType($info_area); // Uses $info_area as per your original
            } else { // Manga
                $result['author'] = $this->getAnimeProducer($properties_container); // Reuses producer logic
                $result['volume'] = $this->getAnimeEpisode($properties_container);  // Reuses episode logic
                $result['serialization'] = $this->getAnimeLicensor($each_anime); // Needs $each_anime
            }

            $result['airing_start'] = $this->getAnimeStart($info_area); // Uses $info_area
            $result['member'] = $this->getAnimeMember($info_area);     // Uses $info_area
            $result['score'] = $this->getAnimeScore($info_area);       // Uses $info_area
            // --- END UNCOMMENTED ---

            $data[] = $result;
        }

        return $data;
    }
}