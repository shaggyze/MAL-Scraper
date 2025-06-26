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
        // Default to getAllInfo if no specific method or if "getAllInfo" is called
        if (empty($method) || $method === 'getAllInfo') {
            if (method_exists($this, 'getAllInfo')) {
                return $this->getAllInfo(...$arguments); // Pass arguments if any
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
        $imageDiv = $each_anime->find('div.image', 0); // "Mononoke Hime" HTML has div.image
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
        // "Sen to Chihiro" HTML: div.title > div.title-text > h2 > a
        $linkNode = $name_area->find('a', 0); // Simpler, direct <a> child of div.title
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
        $node = $name_area->find('a', 0); // Simpler, direct <a> child of div.title
        return ($node && is_object($node) && isset($node->plaintext)) ? trim($node->plaintext) : '';
    }

    private function getAnimeProducerId($each_producer_link)
    {
        if (!is_object($each_producer_link) || empty($each_producer_link->href)) return '';
        $prod_id_href = $each_producer_link->href;
        $prod_id_parts = explode('/', rtrim($prod_id_href, '/'));
        
        if ($this->_type == 'anime') {
            if (isset($prod_id_parts[3]) && ($prod_id_parts[1] ?? '') == 'anime' && ($prod_id_parts[2] ?? '') == 'producer' && is_numeric($prod_id_parts[3])) {
                return $prod_id_parts[3];
            }
        } else { 
            if (isset($prod_id_parts[2]) && ($prod_id_parts[1] ?? '') == 'people' && is_numeric($prod_id_parts[2])) {
                return $prod_id_parts[2];
            }
            if (isset($prod_id_parts[3]) && ($prod_id_parts[1] ?? '') == 'manga' && ($prod_id_parts[2] ?? '') == 'magazine' && is_numeric($prod_id_parts[3])) {
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


    // --- START: NEW PRIVATE HELPER METHOD FOR STUDIO DETAILS (from previous response) ---
    private function _getStudioPageDetails()
    {
        if (!$this->_parser || !is_object($this->_parser)) {
            return ['error_studio_details' => 'Parser not available for studio details.'];
        }

        $studioPageDetails = [];
        $studioPageDetails['studio_name'] = ''; // Initialize
        $studioPageDetails['logo_image_url'] = '';
        $studioPageDetails['info'] = [];
        $studioPageDetails['description'] = '';
        $studioPageDetails['available_at_links'] = [];
        $studioPageDetails['resource_links'] = [];


        $studioNameNode = $this->_parser->find('h1.title-name', 0);
        if ($studioNameNode && is_object($studioNameNode) && isset($studioNameNode->plaintext)) {
            $studioPageDetails['studio_name'] = trim($studioNameNode->plaintext);
        } else {
            $contentLeftForLogo = $this->_parser->find('div.content-left', 0);
            if ($contentLeftForLogo && is_object($contentLeftForLogo)) {
                $logoImgForName = $contentLeftForLogo->find('div.logo img', 0);
                if ($logoImgForName && is_object($logoImgForName) && $logoImgForName->hasAttribute('alt')) {
                     $studioPageDetails['studio_name'] = trim($logoImgForName->getAttribute('alt'));
                }
            }
        }

        $contentLeft = $this->_parser->find('div.content-left', 0);
        if (!$contentLeft || !is_object($contentLeft)) {
            // Attempt with ID if class not found and parserArea is broad
             if (($this->_parserArea === null || $this->_parserArea === 'body' || $this->_parserArea === '') && 
                 !($this->_parserArea === '#content' || $this->_parserArea === '.content-left')) {
                 $contentLeftById = $this->_parser->find('#contentLeft', 0);
                 if ($contentLeftById && is_object($contentLeftById)) $contentLeft = $contentLeftById;
            }
             if (!$contentLeft || !is_object($contentLeft)) { // Still not found
                 return $studioPageDetails; // Return partially filled or empty details
             }
        }

        $logoImg = $contentLeft->find('div.logo img', 0); // Simpler selector
        if ($logoImg && is_object($logoImg)) {
            if ($logoImg->hasAttribute('data-src')) {
                $studioPageDetails['logo_image_url'] = Helper::imageUrlCleaner($logoImg->getAttribute('data-src'));
            } elseif ($logoImg->hasAttribute('src')) {
                $studioPageDetails['logo_image_url'] = Helper::imageUrlCleaner($logoImg->getAttribute('src'));
            }
        }

        $studioInfo = [];
        $description = '';
        $detailsContainer = null;
        foreach ($contentLeft->find('div.mb16') as $mb16Div) {
            if (is_object($mb16Div) && !$mb16Div->find('div.js-sns-icon-container',0) && !$mb16Div->find('div.user-profile-sns',0) ) {
                $detailsContainer = $mb16Div;
                break;
            }
        }
        
        if ($detailsContainer && is_object($detailsContainer)) {
            foreach ($detailsContainer->find('div.spaceit_pad') as $spaceitPad) {
                if (!is_object($spaceitPad)) continue;
                $darkTextNode = $spaceitPad->find('span.dark_text', 0);
                if ($darkTextNode && is_object($darkTextNode) && isset($darkTextNode->plaintext)) {
                    $label = trim(rtrim($darkTextNode->plaintext, ':')); // Remove trailing colon
                    $originalOuterText = $darkTextNode->outertext; // Store original
                    $darkTextNode->outertext = ''; 
                    $value = trim($spaceitPad->plaintext);
                    $darkTextNode->outertext = $originalOuterText; // Restore (though not strictly necessary for scraping value)

                    if (!empty($value)) {
                        if ($label == 'Japanese') $studioInfo['japanese_name'] = $value;
                        elseif ($label == 'Established') $studioInfo['established_date'] = $value;
                        elseif ($label == 'Member Favorites') $studioInfo['member_favorites_count'] = trim(str_replace(',', '', $value));
                    }
                } else {
                    $descSpan = $spaceitPad->find('span', 0); 
                    if ($descSpan && is_object($descSpan) && isset($descSpan->plaintext)) {
                         $currentDesc = trim(preg_replace("/\s+/", " ", $descSpan->plaintext));
                         if (strlen($currentDesc) > strlen($description)) $description = $currentDesc; // Take the longest one
                    } elseif (isset($spaceitPad->plaintext) && strlen(trim($spaceitPad->plaintext)) > 50) {
                         $currentDesc = trim(preg_replace("/\s+/", " ", $spaceitPad->plaintext));
                         if (strlen($currentDesc) > strlen($description)) $description = $currentDesc;
                    }
                }
            }
        }
        $studioPageDetails['info'] = $studioInfo;
        $studioPageDetails['description'] = $description;

        $availableAtLinks = [];
        $availableAtSection = $contentLeft->find('div.user-profile-sns', 0);
        if ($availableAtSection && is_object($availableAtSection)) {
            foreach ($availableAtSection->find('a') as $link) {
                if (is_object($link) && isset($link->href) && isset($link->plaintext)) {
                    $name = trim($link->plaintext);
                    $url = trim($link->href);
                    if (!empty($name) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) $availableAtLinks[] = ['name' => $name, 'url' => $url];
                }
            }
        }
        $studioPageDetails['available_at_links'] = $availableAtLinks;

        $resourceLinks = [];
        $resourceHeader = null;
        foreach($contentLeft->find('h2') as $h2) {
            if (is_object($h2) && isset($h2->plaintext) && trim($h2->plaintext) == 'Resources') {
                $resourceHeader = $h2;
                break;
            }
        }
        if ($resourceHeader && is_object($resourceHeader)) {
            $resourceSection = $resourceHeader->next_sibling(); 
            while($resourceSection && is_object($resourceSection) && $resourceSection->tag !== 'div'){ // Find next div sibling
                $resourceSection = $resourceSection->next_sibling();
            }
            if ($resourceSection && is_object($resourceSection) && strpos($resourceSection->class ?? '', 'pb16') !== false) {
                // Links are <a> tags, directly within a <span>
                $links = $resourceSection->find('span a'); 
                if (empty($links)) $links = $resourceSection->find('a');

                foreach ($links as $link) {
                    if (is_object($link) && isset($link->href) && isset($link->plaintext)) {
                        $name = trim($link->plaintext);
                        $url = trim($link->href);
                        if (!empty($name) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) $resourceLinks[] = ['name' => $name, 'url' => $url];
                    }
                }
            }
        }
        $studioPageDetails['resource_links'] = $resourceLinks;
        
        return $studioPageDetails;
    }
    // --- END: NEW PRIVATE HELPER METHOD FOR STUDIO DETAILS ---


    // --- HELPER FUNCTIONS FOR ANIME LIST ITEMS (from previous response that worked for some fields) ---
    private function getAnimeProducer($each_anime)
    {
        $producer = [];
        if (!is_object($each_anime)) return $producer;
        $properties_node = $each_anime->find('div.synopsis div.properties', 0);
        if ($properties_node && is_object($properties_node)) {
            $targetCaption = ($this->_type == 'anime') ? 'Studio' : 'Authors';
            foreach ($properties_node->find('div.property') as $propDiv) {
                if(!is_object($propDiv)) continue;
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
        return $producer;
    }

    private function getAnimeEpisode($each_anime)
    {
        if (!is_object($each_anime)) return '';
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
        return '';
    }

    private function getAnimeSource($each_anime)
    {
        if (!is_object($each_anime)) return '';
        $properties_node = $each_anime->find('div.synopsis div.properties', 0);
        if ($properties_node && is_object($properties_node)) {
            $targetCaption = ($this->_type == 'anime') ? 'Source' : 'Type';
            foreach ($properties_node->find('div.property') as $propDiv) {
                if(!is_object($propDiv)) continue;
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && is_object($captionNode) && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                    $itemNode = $propDiv->find('span.item', 0);
                    if ($itemNode && is_object($itemNode) && isset($itemNode->plaintext)) {
                        return trim($itemNode->plaintext);
                    }
                    break; 
                }
            }
        }
        return '';
    }

    private function getAnimeGenre($each_anime)
    {
        $genres = [];
        if (!is_object($each_anime)) return $genres;
        $genre_container_outer = $each_anime->find('div.genres.js-genre', 0); 
        if ($genre_container_outer && is_object($genre_container_outer)) {
            $genre_container_inner = $genre_container_outer->find('div.genres-inner', 0); 
            if ($genre_container_inner && is_object($genre_container_inner)) {
                $links = $genre_container_inner->find('span.genre a');                 
                foreach ($links as $each_genre_link) {
                    if (is_object($each_genre_link) && isset($each_genre_link->plaintext)) {
                        $genres[] = trim($each_genre_link->plaintext);
                    }
                }
            }
        }
        return $genres;
    }

    private function getAnimeSynopsis($each_anime)
    {
        if (!is_object($each_anime)) return '';
        $synopsis_container = $each_anime->find('div.synopsis.js-synopsis', 0);
        if ($synopsis_container && is_object($synopsis_container)) {
            $paragraph_node = $synopsis_container->find('p.preline', 0);
            if ($paragraph_node && is_object($paragraph_node) && isset($paragraph_node->plaintext)) {
                $text = trim($paragraph_node->plaintext);
                return trim(preg_replace("/([\s])+/", ' ', $text));
            }
        }
        return '';
    }

    private function getAnimeLicensor($each_anime)
    {
        if (!is_object($each_anime)) return ($this->_type == 'anime') ? [] : '';
        if ($this->_type == 'anime') {
            $synopsisNode = $each_anime->find('div.synopsis.js-synopsis', 0); // More specific class
            if ($synopsisNode && is_object($synopsisNode)) {
                $licensorNode = $synopsisNode->find('span.licensors', 0); 
                if ($licensorNode && is_object($licensorNode) && $licensorNode->hasAttribute('data-licensors')) {
                    return array_filter(explode(',', $licensorNode->getAttribute('data-licensors')));
                }
            }
            return [];
        } else {
            $synopsisNode = $each_anime->find('div.synopsis.js-synopsis', 0);
            if ($synopsisNode && is_object($synopsisNode)) {
                $serializationNode = $synopsisNode->find('.serialization a', 0);
                if ($serializationNode && is_object($serializationNode) && isset($serializationNode->plaintext)) {
                    return trim($serializationNode->plaintext);
                }
            }
            return '';
        }
    }

    private function getAnimeType($each_anime)
    {
        if (!is_object($each_anime)) return '';
        $prodsrc_info_node = $each_anime->find('div.prodsrc div.info', 0);
        if ($prodsrc_info_node && is_object($prodsrc_info_node)) {
            $itemSpans = $prodsrc_info_node->find('span.item');
            if (isset($itemSpans[0]) && is_object($itemSpans[0]) && isset($itemSpans[0]->plaintext)) {
                $full_text = $itemSpans[0]->plaintext;
                $parts = explode(',', $full_text);
                return trim($parts[0]);
            }
        }
        return '';
    }

    private function getAnimeStart($each_anime)
    {
        if (!is_object($each_anime)) return '';
        $jsStartDate = $each_anime->find('span.js-start_date', 0);
        if ($jsStartDate && is_object($jsStartDate) && isset($jsStartDate->plaintext)) {
            return trim($jsStartDate->plaintext);
        }
        return '';
    }

    private function getAnimeScore($each_anime)
    {
        if (!is_object($each_anime)) return 'N/A';
        $jsScore = $each_anime->find('span.js-score', 0);
        if ($jsScore && is_object($jsScore) && isset($jsScore->plaintext)) {
            $score = trim($jsScore->plaintext);
            return (is_numeric($score) && (float)$score >= 0) ? $score : 'N/A';
        }
        $widget = $each_anime->find('div.widget', 0);
        if ($widget && is_object($widget)) {
            $starsNode = $widget->find('div.stars', 0);
            if ($starsNode && is_object($starsNode) && isset($starsNode->plaintext)) {
                if(preg_match('/(\d+\.?\d*)/', $starsNode->plaintext, $matches)){
                    return $matches[1];
                }
            }
        }
        return 'N/A';
    }

    private function getAnimeMember($each_anime)
    {
        if (!is_object($each_anime)) return '0';
        $jsMembers = $each_anime->find('span.js-members', 0);
        if ($jsMembers && is_object($jsMembers) && isset($jsMembers->plaintext)) {
            return trim(preg_replace('/[^\d]/', '', $jsMembers->plaintext));
        }
        $widget = $each_anime->find('div.widget', 0);
        if ($widget && is_object($widget)) {
            $usersNode = $widget->find('div.users', 0);
            if ($usersNode && is_object($usersNode) && isset($usersNode->plaintext)) {
                return trim(preg_replace('/[^\d]/', '', $usersNode->plaintext));
            }
        }
        return '0';
    }
    // --- END OF HELPER FUNCTIONS FOR ANIME LIST ITEMS ---


    /**
     * Main method to get combined studio details and list of works.
     * This should be the primary public method called for this model.
     */
    public function getAllInfo() // Changed to public
    {
        if ($this->_error) {
            return $this->_error;
        }
        if (!$this->_parser || !is_object($this->_parser)) {
            $this->_error = ['error' => 'Parser not initialized or invalid.'];
            return $this->_error;
        }

        $outputData = [];

        // 1. Get Studio Page Details
        $studioPageDetails = $this->_getStudioPageDetails();
        
        // Add studio details to the output data structure at the top level
        if (is_array($studioPageDetails)) {
            foreach ($studioPageDetails as $key => $value) {
                if ($key !== 'error_studio_details') { // Don't copy an error key if it exists
                     $outputData[$key] = $value;
                } elseif (!empty($value)) { // If there was an error fetching studio details, include it
                     $outputData['studio_details_error'] = $value;
                }
            }
        }


        // 2. Get List of Anime/Works
        $animeListData = [];
        // YOUR ORIGINAL WORKING SELECTOR FOR THE LIST OF ITEMS
        $anime_table = $this->_parser->find('div[class="js-anime-category-studio seasonal-anime js-seasonal-anime js-anime-type-all js-anime-type-3"]');

        if (is_array($anime_table)) { // Ensure $anime_table is an array before looping
            foreach ($anime_table as $each_anime) {
                if(!is_object($each_anime)) continue; 

                $result = [];
                // YOUR ORIGINAL SUB-SELECTOR for name_area
                $name_area = $each_anime->find('div[class=title]', 0);
                
                $result['image'] = ''; $result['id'] = ''; $result['title'] = ''; // Initialize
                $result['image'] = $this->getAnimeImage($each_anime);
                if ($name_area && is_object($name_area)) {
                    $result['id'] = $this->getAnimeId($name_area);
                    $result['title'] = $this->getAnimeTitle($name_area);
                }

                if (empty($result['id'])) {
                    continue; 
                }

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
                $result['member'] = $this->getAnimeMember($each_anime);
                $result['score'] = $this->getAnimeScore($each_anime);   
                
                $animeListData[] = $result;
            }
        }

        // Merge anime list into outputData using numeric keys for each anime item
        foreach ($animeListData as $index => $animeItem) {
            $outputData[(string)$index] = $animeItem;
        }
        // If $animeListData is empty and no studio details were found either,
        // $outputData might be empty. Consider if an empty "works" array is preferred.
        if (empty($animeListData) && !array_key_exists('0', $outputData) && count(array_filter(array_keys($outputData), 'is_numeric')) == 0) {
            // If no numeric keys (anime list items) were added, explicitly add an empty works array
            // if you want the "works" key even when empty, otherwise numeric keys are fine.
            // For merging directly, this isn't needed. If you wanted "works": [], you'd do:
            // $outputData['works'] = $animeListData;
        }


        return $outputData;
    }
}