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
     * @param string|int $page // Added page to constructor params for consistency
     * @param string     $parserArea
     *
     * @return void
     */
    public function __construct($type, $type2, $id, $page = 1, $parserArea = '#contentWrapper') // Changed default parserArea slightly to be more encompassing, but original #content should also work for list items.
    {
        $this->_type = $type;
        $this->_type2 = $type2;
        $this->_id = $id;
        $this->_page = $page;

        if ($type2 == 'producer') {
            if ($type == 'anime') {
                $this->_url = $this->_myAnimeListUrl.'/anime/producer/'.$id.'/?page='.$page;
            } else {
                // For manga, 'producer' often means 'magazine' or 'serialization' on MAL
                $this->_url = $this->_myAnimeListUrl.'/manga/magazine/'.$id.'/?page='.$page;
            }
        } else { // genre
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

        // Ensure that the getAllInfo method is called if no specific method is requested by the user
        // This behavior might depend on how your library is designed to be used.
        // If this class is only meant to have getAllInfo() as its public output,
        // then direct calls to private methods shouldn't happen from outside.
        // Assuming getAllInfo is the primary public method to get data.
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        } elseif ($method === 'getAllInfo' || empty($method)) { // Or a more explicit public method name
            return $this->getAllInfo();
        }

        // Fallback or error for undefined methods
        return 'Method '.$method.' not found.';
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
        $imageNode = $each_anime->find('div.image a.link-image img', 0);
        if ($imageNode) {
            $image = $imageNode->getAttribute('data-src') ?: $imageNode->getAttribute('src');
            return Helper::imageUrlCleaner($image);
        }
        return '';
    }

    /**
     * Get anime id.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $name_area (which is div.title)
     *
     * @return string
     */
    private function getAnimeId($name_area)
    {
        $linkNode = $name_area->find('a.link-title', 0);
        if ($linkNode) {
            $anime_id_href = $linkNode->href;
            if (preg_match('/\/'.preg_quote($this->_type, '/').'\/(\d+)\//', $anime_id_href, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    /**
     * Get anime title.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $name_area (which is div.title)
     *
     * @return string
     */
    private function getAnimeTitle($name_area)
    {
        $linkNode = $name_area->find('a.link-title', 0);
        return $linkNode ? trim($linkNode->plaintext) : '';
    }

    /**
     * Get anime producer/studios or manga authors.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return array
     */
    private function getAnimeProducer($each_anime)
    {
        $producers = [];
        $propertyNodes = $each_anime->find('div.information div.properties div.property'); // Search within information block
        $targetCaption = ($this->_type == 'anime') ? 'Studios:' : 'Authors:';

        foreach ($propertyNodes as $propertyNode) {
            $captionNode = $propertyNode->find('span.caption', 0);
            if ($captionNode && trim($captionNode->plaintext) == $targetCaption) {
                foreach ($propertyNode->find('span.item a') as $each_producer_link) {
                    $temp_prod = [];
                    $href = $each_producer_link->href;
                    if ($this->_type == 'anime' && preg_match('/\/producer\/(\d+)\//', $href, $matches)) {
                        $temp_prod['id'] = $matches[1];
                    } elseif ($this->_type == 'manga' && preg_match('/\/people\/(\d+)\//', $href, $matches)) {
                        $temp_prod['id'] = $matches[1];
                    } else {
                        // Fallback for other ID patterns if needed, e.g. /manga/magazine/ID/Name
                        if ($this->_type == 'manga' && $this->_type2 == 'producer' && preg_match('/\/magazine\/(\d+)\//', $href, $matches)) {
                            $temp_prod['id'] = $matches[1]; // If it's a link to another magazine
                        } else {
                            $parts = explode('/', rtrim($href, '/'));
                            $id_candidate = $parts[count($parts) - 2];
                            if(is_numeric($id_candidate)) $temp_prod['id'] = $id_candidate;
                        }
                    }
                    $temp_prod['name'] = trim($each_producer_link->plaintext);
                    if (!empty($temp_prod['id']) && !empty($temp_prod['name'])) {
                       $producers[] = $temp_prod;
                    }
                }
                break;
            }
        }
        return $producers;
    }

    /**
     * Get anime episode or manga volume count.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeEpisode($each_anime)
    {
        $epsNode = $each_anime->find('div.information div.meta span.eps', 0);
        if ($epsNode) {
            $episodeText = trim($epsNode->plaintext);
            // Extracts number from "X eps", "X vols", "(X eps)", etc.
            if (preg_match('/(\d+)/', $episodeText, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    /**
     * Get anime source (e.g., Manga, Original) or manga type.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeSource($each_anime)
    {
        $sourceNode = null;
        if ($this->_type == 'anime') {
            $sourceNode = $each_anime->find('div.information div.meta span.source', 0);
        } else {
             // For manga, type (Manga, Manhwa, etc.) is often the first non-numerical span in div.meta
            $metaSpans = $each_anime->find('div.information div.meta span.item'); // Often has "item" class
            foreach($metaSpans as $span) {
                $text = trim($span->plaintext);
                // Try to identify it's not episodes/volumes or a date
                if (!preg_match('/^\d+\s*(eps?|vols?\.?)$/i', $text) && !preg_match('/^\d{1,2}\s\w{3},\s\d{4}$/i', $text) && !is_numeric(str_replace(['(',')'], '', $text))) {
                    $sourceNode = $span;
                    break;
                }
            }
             // Fallback if specific type class like 'manga-type' isn't standard
            if (!$sourceNode) {
                $metaSpans = $each_anime->find('div.information div.meta span');
                if (count($metaSpans) > 0 && !preg_match('/^\d+/', trim($metaSpans[0]->plaintext))) {
                     // Check if first span text is not like "12 eps"
                    $potentialType = trim($metaSpans[0]->plaintext);
                    if (!preg_match('/^\d+\s*(eps?|vols?\.?)$/i', $potentialType)) {
                        $sourceNode = $metaSpans[0];
                    }
                }
            }
        }
        return $sourceNode ? trim($sourceNode->plaintext) : '';
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
        $genre_area = $each_anime->find('div.information div.genres.js-genre', 0);
        if ($genre_area) {
            foreach ($genre_area->find('a span') as $each_genre_span) { // Genres are often wrapped in <a><span>GenreName</span></a>
                $genres[] = trim($each_genre_span->plaintext);
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
        $synopsisNode = $each_anime->find('div.information div.synopsis div.pt4', 0); // Common pattern now
        if (!$synopsisNode) {
            $synopsisNode = $each_anime->find('div.information div.synopsis span.preline', 0);
        }
        if (!$synopsisNode) {
             $synopsisNode = $each_anime->find('div.information div.synopsis', 0);
        }

        if ($synopsisNode) {
            foreach ($synopsisNode->find('a') as $a) {
                if (strtolower(trim($a->plaintext)) === 'read more') {
                    $a->outertext = '';
                }
            }
            $synopsisText = trim($synopsisNode->plaintext);
            return trim(preg_replace("/([\s])+/", ' ', $synopsisText));
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
        if ($this->_type == 'anime') {
            $licensorNode = $each_anime->find('div.information div.synopsis span.licensors', 0);
            if ($licensorNode && $licensorNode->hasAttribute('data-licensors')) {
                $licensors = $licensorNode->getAttribute('data-licensors');
                return array_filter(array_map('trim', explode(',', $licensors)));
            }
            // Fallback: Check properties if not in synopsis
            $propertyNodes = $each_anime->find('div.information div.properties div.property');
            foreach ($propertyNodes as $propertyNode) {
                $captionNode = $propertyNode->find('span.caption', 0);
                if ($captionNode && trim($captionNode->plaintext) == 'Licensors:') {
                    $licensors = [];
                    foreach($propertyNode->find('span.item a') as $licensorLink) {
                        $licensors[] = trim($licensorLink->plaintext);
                    }
                    return array_filter($licensors);
                }
            }
            return [];
        } else { // Manga Serialization
            $propertyNodes = $each_anime->find('div.information div.properties div.property');
            foreach ($propertyNodes as $propertyNode) {
                $captionNode = $propertyNode->find('span.caption', 0);
                if ($captionNode && trim($captionNode->plaintext) == 'Serialization:') {
                    $serializationLink = $propertyNode->find('span.item a', 0);
                    return $serializationLink ? trim($serializationLink->plaintext) : ($propertyNode->find('span.item', 0) ? trim($propertyNode->find('span.item', 0)->plaintext) : '');
                }
            }
            return '';
        }
    }

    /**
     * Get anime type (e.g., TV, Movie).
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeType($each_anime)
    {
        $metaNode = $each_anime->find('div.information div.meta', 0);
        if ($metaNode) {
            // Stronger: Look for specific class names associated with types
            $typeSpecificNode = $metaNode->find('span.tv, span.ova, span.movie, span.ona, span.special, span.music', 0);
            if ($typeSpecificNode) return trim($typeSpecificNode->plaintext);

            // Fallback: iterate spans in meta and check for common type keywords if specific classes aren't found
            $spans = $metaNode->find('span.item'); // type, eps, source often have 'item'
            if (empty($spans)) $spans = $metaNode->find('span');

            foreach ($spans as $span) {
                $text = trim($span->plaintext);
                // MAL type names: TV, OVA, Movie, Special, ONA, Music
                if (in_array($text, ['TV', 'OVA', 'Movie', 'Special', 'ONA', 'Music'])) {
                    return $text;
                }
            }
        }
        return '';
    }

    /**
     * Get anime start date or manga publication date.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeStart($each_anime)
    {
        $propertyNodes = $each_anime->find('div.information div.properties div.property');
        $targetCaption = ($this->_type == 'anime') ? 'Aired:' : 'Published:';

        foreach ($propertyNodes as $propertyNode) {
            $captionNode = $propertyNode->find('span.caption', 0);
            if ($captionNode && trim($captionNode->plaintext) == $targetCaption) {
                $itemNode = $propertyNode->find('span.item', 0);
                return $itemNode ? trim($itemNode->plaintext) : '';
            }
        }
        // Fallback for airing date sometimes found in meta for currently airing anime
        if ($this->_type == 'anime') {
            $metaSpans = $each_anime->find('div.information div.meta span.item');
            foreach ($metaSpans as $span) {
                // Look for a date format like "Apr 2024 to ?" or "Apr 10, 2024"
                $text = trim($span->plaintext);
                if (preg_match('/^[A-Za-z]{3}\s\d{1,2},\s\d{4}/', $text) || preg_match('/^[A-Za-z]{3}\s\d{4}/', $text)) {
                    return $text;
                }
            }
        }
        return '';
    }

    /**
     * Get anime score.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeScore($each_anime)
    {
        $scoreNode = $each_anime->find('div.information div.score.score-label', 0); // Class is often 'score score-label'
        if ($scoreNode) {
            if ($scoreNode->hasAttribute('data-score')) { // This is more reliable if present
                $score = trim($scoreNode->getAttribute('data-score'));
                if (is_numeric($score) && $score > 0) return $score;
            }
            $scoreText = trim($scoreNode->plaintext);
            return (is_numeric($scoreText) && (float)$scoreText > 0) ? $scoreText : 'N/A';
        }
        return 'N/A';
    }

    /**
     * Get anime member count.
     *
     * @param \simplehtmldom_1_5\simple_html_dom $each_anime
     *
     * @return string
     */
    private function getAnimeMember($each_anime)
    {
        $memberNode = $each_anime->find('div.information div.scormem span.member', 0); // More specific: span with class 'member'
        if (!$memberNode) {
             $memberNode = $each_anime->find('div.information div.scormem span[class*="members"]', 0); // Broader if 'member' is not exact
        }
        if (!$memberNode) {
             $memberNode = $each_anime->find('div.information div.members', 0); // Even broader if scormem isn't there
        }


        if ($memberNode) {
            $memberText = trim($memberNode->plaintext);
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
     * Get all anime produced by the studio/producer or items in genre/magazine.
     *
     * @return array
     */
    public function getAllInfo() // Made public as it's the main data retrieval method
    {
        if ($this->_error) { // Check error from constructor
            return $this->_error;
        }
        if (!$this->_parser) { // Check if parser was initialized
            return ['error' => 'Parser not initialized.'];
        }

        $data = [];
        // This selector targets each individual anime/manga item card
        $anime_table = $this->_parser->find('div.seasonal-anime.js-seasonal-anime');

        if (empty($anime_table) && $this->_type2 == 'genre') {
            // Genres sometimes use a slightly different container for the list
            $anime_table = $this->_parser->find('div.js-categories-seasonal div.seasonal-anime');
        }
        // A more general fallback if the specific one fails for some producer pages
        if (empty($anime_table)) {
            $anime_table = $this->_parser->find('div[class*="seasonal-anime"][class*="js-seasonal-anime"]');
        }


        foreach ($anime_table as $each_anime) {
            $result = [];

            $name_area = $each_anime->find('div.information div.title', 0);
            if (!$name_area) {
                // If there's no title area, it's likely not a valid item, so skip.
                // This can happen if the selector picks up other divs by mistake.
                continue;
            }

            $result['id'] = $this->getAnimeId($name_area);
            // If ID is empty, it's likely a malformed item or an ad, skip.
            if (empty($result['id'])) {
                continue;
            }

            $result['title'] = $this->getAnimeTitle($name_area);
            $result['image'] = $this->getAnimeImage($each_anime);
            $result['genre'] = $this->getAnimeGenre($each_anime);
            $result['synopsis'] = $this->getAnimeSynopsis($each_anime);


            if ($this->_type == 'anime') {
                $result['type'] = $this->getAnimeType($each_anime);
                $result['episode'] = $this->getAnimeEpisode($each_anime);
                $result['producer'] = $this->getAnimeProducer($each_anime);
                $result['licensor'] = $this->getAnimeLicensor($each_anime);
                $result['source'] = $this->getAnimeSource($each_anime);
            } else { // Manga
                // For manga, 'source' usually means the type (Manga, Manhwa, etc.)
                $result['type'] = $this->getAnimeSource($each_anime);
                $result['volume'] = $this->getAnimeEpisode($each_anime); // episodes method handles volumes too
                $result['author'] = $this->getAnimeProducer($each_anime); // producer method handles authors
                $result['serialization'] = $this->getAnimeLicensor($each_anime); // licensor method handles serialization
            }

            $result['airing_start'] = $this->getAnimeStart($each_anime);
            $result['score'] = $this->getAnimeScore($each_anime);
            $result['member'] = $this->getAnimeMember($each_anime);

            $data[] = $result;
        }

        return $data;
    }
}