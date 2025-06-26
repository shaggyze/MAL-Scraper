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
        if (empty($method) || $method === 'getAllInfo' && method_exists($this, 'getAllInfo')) {
            return $this->getAllInfo(...$arguments);
        }
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        }
        return ['error' => 'Method ' . $method . ' not found in ProducerModel.'];
    }

    /**
     * Get anime image.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeImage($each_anime)
    {
        // Current MAL structure for image in a list item
        $imageNode = $each_anime->find('div.image a.link-image img', 0);
        if ($imageNode) {
            $imageSrc = $imageNode->getAttribute('data-src') ?: $imageNode->getAttribute('src');
            return Helper::imageUrlCleaner($imageSrc);
        }
        // Fallback to your original selector if the above fails, though less likely for current MAL
        $originalImageNode = $each_anime->find('div[class=image]', 0);
        if ($originalImageNode) {
            $imgTag = $originalImageNode->find('img', 0);
            if ($imgTag) {
                 $imageSrc = $imgTag->getAttribute('data-src') ?: $imgTag->getAttribute('src');
                 return Helper::imageUrlCleaner($imageSrc);
            }
        }
        return '';
    }

    /**
     * Get anime id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $name_area (expected to be div.title)
     *
     * @return string
     */
    private function getAnimeId($name_area)
    {
        // $name_area is usually div.title or h2.h2_anime_title
        $linkNode = $name_area->find('a', 0); // The title link
        if ($linkNode && !empty($linkNode->href)) {
            // Updated to handle both /anime/ID/ and /manga/ID/
            if (preg_match('/\/'.preg_quote($this->_type, '/').'\/(\d+)\//', $linkNode->href, $matches)) {
                return $matches[1];
            }
            // Fallback to original logic if specific type isn't matched (less robust)
            $anime_id_parts = explode('/', rtrim($linkNode->href, '/'));
            // Typically ID is the second to last part if URL is /type/id/name
            if (count($anime_id_parts) >= 3 && is_numeric($anime_id_parts[count($anime_id_parts) - 2])) {
                 return $anime_id_parts[count($anime_id_parts) - 2];
            }
             // Your original logic (might be too specific if URL structure changed slightly)
            if (isset($anime_id_parts[4]) && is_numeric($anime_id_parts[4])) return $anime_id_parts[4];

        }
        return '';
    }

    /**
     * Get anime title.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $name_area (expected to be div.title)
     *
     * @return string
     */
    private function getAnimeTitle($name_area)
    {
        $linkNode = $name_area->find('a', 0);
        return $linkNode && isset($linkNode->plaintext) ? trim($linkNode->plaintext) : '';
    }

    /**
     * Get producer name (for anime) or author name (for manga).
     * The $producer_area parameter in your original code was div.property, which is too broad.
     * We should pass $each_anime and find the relevant properties within it.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return array
     */
    private function getAnimeProducer($each_anime) // Changed parameter to $each_anime
    {
        $output = [];
        $targetCaption = ($this->_type == 'anime') ? 'Studios:' : 'Authors:';
        $properties = $each_anime->find('div.properties div.property');

        foreach ($properties as $property) {
            $captionNode = $property->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                $itemNodes = $property->find('span.item a'); // Producers/Authors are links
                foreach ($itemNodes as $itemNode) {
                    $temp_item = [];
                    $href = isset($itemNode->href) ? $itemNode->href : '';
                    $id = '';

                    if ($this->_type == 'anime' && preg_match('/\/producer\/(\d+)\//', $href, $matches)) {
                        $id = $matches[1];
                    } elseif ($this->_type == 'manga' && preg_match('/\/people\/(\d+)\//', $href, $matches)) { // Authors are /people/
                        $id = $matches[1];
                    }

                    if($id && isset($itemNode->plaintext)){
                        $temp_item['id'] = $id;
                        $temp_item['name'] = trim($itemNode->plaintext);
                        $output[] = $temp_item;
                    }
                }
                break;
            }
        }
        return $output;
    }

    // getAnimeProducerId and getAnimeProducerName are effectively inlined into the new getAnimeProducer logic.
    // If you need them separate, they would take an $itemNode (the <a> tag) as parameter.

    /**
     * Get anime episode or manga volume/chapter count.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime (changed from $producer_area)
     *
     * @return string
     */
    private function getAnimeEpisode($each_anime) // Changed parameter
    {
        $targetCaption = ($this->_type == 'anime') ? 'Episodes:' : 'Volumes:';
        // Chapters might also be relevant for manga, sometimes labeled "Chapters:"

        $properties = $each_anime->find('div.properties div.property');
        foreach ($properties as $property) {
            $captionNode = $property->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                $itemNode = $property->find('span.item', 0);
                if ($itemNode && isset($itemNode->plaintext)) {
                    $text = trim($itemNode->plaintext);
                    // Extract just the number if possible, or return text like "Unknown"
                    if (is_numeric($text)) return $text;
                    if (preg_match('/^(\d+)/', $text, $matches)) return $matches[1];
                    return $text; // Handles "Unknown", etc.
                }
            }
        }
        // Fallback: original selector was based on $producer_area->find('div[class=eps]', 0)
        // This structure (div.eps) is less common now inside a generic "property" div.
        // It was more common as a direct child of the item or its info block.
        // Let's try finding it directly within $each_anime if properties method fails.
        $epsNode = $each_anime->find('div.meta span.eps', 0); // A common old location
        if ($epsNode && isset($epsNode->plaintext)) {
            $text = trim(str_replace(['eps', 'ep', 'vols', 'vol', '(', ')'], '', $epsNode->plaintext));
            return trim($text);
        }
        return '';
    }

    /**
     * Get anime source or manga type.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime (changed from $producer_area)
     *
     * @return string
     */
    private function getAnimeSource($each_anime) // Changed parameter
    {
        $targetCaption = ($this->_type == 'anime') ? 'Source:' : 'Type:'; // Manga has "Type: Manga/Manhwa"

        $properties = $each_anime->find('div.properties div.property');
        foreach ($properties as $property) {
            $captionNode = $property->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                $itemNode = $property->find('span.item', 0); // Source/Type is usually not a link
                if ($itemNode && isset($itemNode->plaintext)) {
                    return trim($itemNode->plaintext);
                }
            }
        }
        // Fallback: original was $producer_area->find('span[class=item]', 0)
        // This is too generic. If Source/Type is not in properties, it might be in div.meta.
        $metaSourceNode = $each_anime->find('div.meta span.source', 0); // for anime source
        if ($metaSourceNode && isset($metaSourceNode->plaintext)) return trim($metaSourceNode->plaintext);

        $metaTypeNode = $each_anime->find('div.meta span.type', 0); // for manga type
        if ($metaTypeNode && isset($metaTypeNode->plaintext)) return trim($metaTypeNode->plaintext);

        return '';
    }

    /**
     * Get anime genre.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return array
     */
    private function getAnimeGenre($each_anime)
    {
        $genres = [];
        // Current MAL: div.genres.js-genre contains span.genre > a > span.genre-name (or just a)
        // Your old selector: div[class="genres-inner js-genre-inner"] span a
        // Let's try a robust modern selector
        $genreLinks = $each_anime->find('div.genres.js-genre span.genre a');
        if (empty($genreLinks)) { // Fallback to simpler structure if the above is too specific
            $genreLinks = $each_anime->find('div.genres a');
        }

        foreach ($genreLinks as $genreLink) {
            if(isset($genreLink->plaintext)) {
                $genres[] = trim($genreLink->plaintext);
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
    private function getAnimeSynopsis($each_anime)
    {
        // Current MAL: div.synopsis div.text (sometimes span.preline for older items)
        // Your old selector: div[class="synopsis js-synopsis"]
        $synopsisContainer = $each_anime->find('div.synopsis.js-synopsis', 0);
        if ($synopsisContainer) {
            $textNode = $synopsisContainer->find('div.text', 0);
            if (!$textNode) { // Fallback
                $textNode = $synopsisContainer->find('span.preline', 0);
            }
            if (!$textNode) { // Further fallback to the container itself
                $textNode = $synopsisContainer;
            }

            if ($textNode && isset($textNode->plaintext)) {
                 // Remove "Read more" link text if it exists
                foreach ($textNode->find('a') as $a) {
                    if (isset($a->plaintext) && strtolower(trim($a->plaintext)) === 'read more') {
                        $a->outertext = ''; // Remove the link
                    }
                }
                $synopsisText = isset($textNode->plaintext) ? trim($textNode->plaintext) : ''; // Re-check plaintext after modification
                return trim(preg_replace("/([\s])+/", ' ', $synopsisText));
            }
        }
        return '';
    }

    /**
     * Get anime licensor or manga serialization.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string|array
     */
    private function getAnimeLicensor($each_anime)
    {
        $targetCaption = ($this->_type == 'anime') ? 'Licensors:' : 'Serialization:';
        $properties = $each_anime->find('div.properties div.property');

        foreach ($properties as $property) {
            $captionNode = $property->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                if ($this->_type == 'anime') {
                    $licensors = [];
                    foreach($property->find('span.item a') as $linkNode) {
                        if (isset($linkNode->plaintext)) $licensors[] = trim($linkNode->plaintext);
                    }
                    return array_filter($licensors);
                } else { // Manga Serialization
                    $itemNode = $property->find('span.item a', 0); // Serialization is usually a link
                     if (!$itemNode) $itemNode = $property->find('span.item', 0); // Fallback if not a link
                    return ($itemNode && isset($itemNode->plaintext)) ? trim($itemNode->plaintext) : '';
                }
            }
        }

        // Fallback for anime licensors from data-attribute (your original logic)
        if ($this->_type == 'anime') {
            $licensorNode = $each_anime->find('div.synopsis.js-synopsis span.licensors', 0); // Original: .licensors
            if ($licensorNode && $licensorNode->hasAttribute('data-licensors')) {
                $licensorData = $licensorNode->getAttribute('data-licensors');
                return array_filter(explode(',', $licensorData));
            }
        }
        return ($this->_type == 'anime') ? [] : '';
    }

    /**
     * Get anime type (TV, Movie etc.).
     * $info_area was originally $each_anime->find('.information', 0)
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime (changed from $info_area)
     *
     * @return string
     */
    private function getAnimeType($each_anime) // Changed parameter
    {
        // Type is now typically in properties
        $properties = $each_anime->find('div.properties div.property');
        foreach ($properties as $property) {
            $captionNode = $property->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == 'Type:') {
                $itemNode = $property->find('span.item a', 0); // Type is often a link now
                if (!$itemNode) $itemNode = $property->find('span.item', 0); // Fallback if not a link

                if ($itemNode && isset($itemNode->plaintext)) {
                    return trim($itemNode->plaintext);
                }
            }
        }
        // Fallback: original was $info_area->find('.info', 0)->plaintext and then explode
        // This structure is very old. Modern MAL is different.
        // Try finding it in div.meta if properties method fails.
        $metaSpans = $each_anime->find('div.meta span');
        foreach($metaSpans as $span) {
            if (isset($span->plaintext)) {
                $text = trim($span->plaintext);
                if (in_array($text, ['TV', 'OVA', 'Movie', 'Special', 'ONA', 'Music'])) return $text;
            }
        }
        return '';
    }

    /**
     * Get anime start date.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime (changed from $info_area)
     *
     * @return string
     */
    private function getAnimeStart($each_anime) // Changed parameter
    {
        $targetCaption = ($this->_type == 'anime') ? 'Aired:' : 'Published:';
        $properties = $each_anime->find('div.properties div.property');

        foreach ($properties as $property) {
            $captionNode = $property->find('span.caption', 0);
            if ($captionNode && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                $itemNode = $property->find('span.item', 0);
                if ($itemNode && isset($itemNode->plaintext)) {
                    // Date can be "MMM DD, YYYY to MMM DD, YYYY" or just "MMM DD, YYYY" or "YYYY to ?"
                    // We typically want the start date part.
                    $dateText = trim($itemNode->plaintext);
                    $parts = explode(' to ', $dateText);
                    return trim($parts[0]);
                }
            }
        }
        // Fallback: original was $info_area->find('.info .remain-time', 0)
        // This structure is old.
        $remainTimeNode = $each_anime->find('div.meta span.remain-time',0); // Old structure
        if($remainTimeNode && isset($remainTimeNode->plaintext)) return trim($remainTimeNode->plaintext);

        return '';
    }

    /**
     * Get anime score.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime (changed from $info_area)
     *
     * @return string
     */
    private function getAnimeScore($each_anime) // Changed parameter
    {
        // Current MAL: div.score.score-label
        $scoreNode = $each_anime->find('div.score.score-label', 0);
        if ($scoreNode && isset($scoreNode->plaintext)) {
            $scoreText = trim($scoreNode->plaintext);
            // Check if it's a valid number, MAL uses "N/A" sometimes
            if (is_numeric($scoreText) && (float)$scoreText > 0) {
                return $scoreText;
            }
        }
        // Fallback: original was from .scormem .score inside $info_area
        $scorememScoreNode = $each_anime->find('div.scormem span.score', 0); // Assuming .scormem is child of $each_anime
        if ($scorememScoreNode && isset($scorememScoreNode->plaintext)) {
             $scoreText = trim($scorememScoreNode->plaintext);
            if (is_numeric($scoreText) && (float)$scoreText > 0) {
                return $scoreText;
            }
        }
        return 'N/A'; // Default if not found or not a number
    }

    /**
     * Get anime member count.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime (changed from $info_area)
     *
     * @return string
     */
    private function getAnimeMember($each_anime) // Changed parameter
    {
        // Current MAL: div.scormem span.member
        $memberNode = $each_anime->find('div.scormem span.member', 0);
        if ($memberNode && isset($memberNode->plaintext)) {
            $memberText = trim($memberNode->plaintext);
            // Extract number, e.g., "1.2M members" -> "1200000"
            if (preg_match('/([\d,\.]+)\s*(K|M)?/i', $memberText, $matches)) {
                $count = str_replace(',', '', $matches[1]);
                $suffix = isset($matches[2]) ? strtoupper($matches[2]) : '';
                if ($suffix == 'M') {
                    $count = (float)$count * 1000000;
                } elseif ($suffix == 'K') {
                    $count = (float)$count * 1000;
                }
                return (string)(int)$count;
            }
        }
        // Fallback: original was $info_area->find('.scormem span[class^=member]', 0)
        // This is very similar to the above.
        return '0';
    }

    /**
     * Get all anime produced by the studio/producer.
     *
     * @return array
     */
    private function getAllInfo()
    {
        $data = [];
        // UPDATED: Modern MAL list items are usually div.seasonal-anime.js-seasonal-anime
        // Your original selector was: 'div[class="js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3"]'
        // This original selector might have been for a container OR for the items themselves if classes were combined.
        // Let's assume it was for the items, or find items within a common container.

        $items_container = $this->_parser->find('div.js-categories-seasonal', 0); // Common outer container
        if (!$items_container) {
            $items_container = $this->_parser; // Fallback to searching within the whole parsed area
        }
        $anime_table = $items_container->find('div.seasonal-anime.js-seasonal-anime');

        // If the above fails, try your original selector for $anime_table directly
        if (empty($anime_table)) {
             $anime_table = $this->_parser->find('div[class="js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3"]');
        }


        foreach ($anime_table as $each_anime) {
            $result = [];

            // These specific sub-selections for $name_area, $producer_area, $info_area
            // are based on an older MAL structure. The helper functions are now mostly
            // adapted to take $each_anime and find their targets within it based on current structure.
            // However, your original code used these, so let's try to maintain that where it makes sense
            // or pass $each_anime to the new helpers.

            $name_area = $each_anime->find('div.title', 0); // Title is usually in a div.title or h2.h2_anime_title
             if (!$name_area) $name_area = $each_anime->find('h2.h2_anime_title', 0);

            // $producer_area was $each_anime->find('div[class=property"]', 0);
            // This selector is problematic due to the unmatched quote. Assuming 'div.property' or 'div.properties'
            // Since new helpers take $each_anime, this specific $producer_area might not be needed as much.
            // $producer_area_for_old_logic = $each_anime->find('div.properties', 0); // Container for all properties

            // $info_area was $each_anime->find('.information', 0);
            // This is a very broad class. Many new helpers take $each_anime.
            // $info_area_for_old_logic = $each_anime->find('.information', 0);


            // These are working as per your statement
            $result['image'] = $this->getAnimeImage($each_anime); // Pass $each_anime
            if ($name_area) { // Guard against null $name_area
                $result['id'] = $this->getAnimeId($name_area);
                $result['title'] = $this->getAnimeTitle($name_area);
            } else {
                $result['id'] = '';
                $result['title'] = '';
            }

            // If ID is empty, it's likely not a valid item, skip.
            if (empty($result['id'])) {
                continue;
            }

            // UNCOMMENT AND USE THESE:
            $result['genre'] = $this->getAnimeGenre($each_anime);
            $result['synopsis'] = $this->getAnimeSynopsis($each_anime);
            $result['source'] = $this->getAnimeSource($each_anime); // Pass $each_anime

            if ($this->_type == 'anime') {
                $result['producer'] = $this->getAnimeProducer($each_anime); // Pass $each_anime
                $result['episode'] = $this->getAnimeEpisode($each_anime);   // Pass $each_anime
                $result['licensor'] = $this->getAnimeLicensor($each_anime);
                $result['type'] = $this->getAnimeType($each_anime);         // Pass $each_anime
            } else { // Manga
                $result['author'] = $this->getAnimeProducer($each_anime); // Uses the same logic as producers
                $result['volume'] = $this->getAnimeEpisode($each_anime);  // Uses the same logic as episodes
                $result['serialization'] = $this->getAnimeLicensor($each_anime); // Uses the same licensor logic for serialization
            }

            $result['airing_start'] = $this->getAnimeStart($each_anime); // Pass $each_anime
            $result['member'] = $this->getAnimeMember($each_anime);     // Pass $each_anime
            $result['score'] = $this->getAnimeScore($each_anime);       // Pass $each_anime

            $data[] = $result;
        }

        return $data;
    }
}