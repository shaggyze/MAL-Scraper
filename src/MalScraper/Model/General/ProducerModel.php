<?php

namespace MalScraper\Model\General;

use MalScraper\Helper\Helper;
use MalScraper\Model\MainModel;
use HtmlDomParser;

/**
 * ProducerModel class.
 */
class ProducerModel extends MainModel
{
    private $_type;
    private $_type2;
    private $_id;
    private $_page;

    // --- GENRE MAPPINGS ---
    private static $animeGenreMap = [
        "1" => "Action", "2" => "Adventure", "5" => "Avant Garde", "46" => "Award Winning", "28" => "Boys Love", "4" => "Comedy", "8" => "Drama", "10" => "Fantasy", "26" => "Girls Love", "47" => "Gourmet", "14" => "Horror", "7" => "Mystery", "22" => "Romance", "24" => "Sci-Fi", "36" => "Slice of Life", "30" => "Sports", "37" => "Supernatural", "41" => "Suspense", "9" => "Ecchi", "49" => "Erotica", "12" => "Hentai", "50" => "Adult Cast", "51" => "Anthropomorphic", "52" => "CGDCT", "53" => "Childcare", "54" => "Combat Sports", "81" => "Crossdressing", "55" => "Delinquents", "39" => "Detective", "56" => "Educational", "57" => "Gag Humor", "58" => "Gore", "35" => "Harem", "59" => "High Stakes Game", "13" => "Historical", "60" => "Idols (Female)", "61" => "Idols (Male)", "62" => "Isekai", "63" => "Iyashikei", "64" => "Love Polygon", "74" => "Love Status Quo", "65" => "Magical Sex Shift", "66" => "Mahou Shoujo", "17" => "Martial Arts", "18" => "Mecha", "67" => "Medical", "38" => "Military", "19" => "Music", "6" => "Mythology", "68" => "Organized Crime", "69" => "Otaku Culture", "20" => "Parody", "70" => "Performing Arts", "71" => "Pets", "40" => "Psychological", "3" => "Racing", "72" => "Reincarnation", "73" => "Reverse Harem", "21" => "Samurai", "23" => "School", "75" => "Showbiz", "29" => "Space", "11" => "Strategy Game", "31" => "Super Power", "76" => "Survival", "77" => "Team Sports", "78" => "Time Travel", "82" => "Urban Fantasy", "32" => "Vampire", "79" => "Video Game", "83" => "Villainess", "80" => "Visual Arts", "48" => "Workplace", "43" => "Josei", "15" => "Kids", "42" => "Seinen", "25" => "Shoujo", "27" => "Shounen"
    ];
    private static $mangaGenreMap = [
        "1" => "Action", "2" => "Adventure", "5" => "Avant Garde", "46" => "Award Winning", "28" => "Boys Love", "4" => "Comedy", "8" => "Drama", "10" => "Fantasy", "26" => "Girls Love", "47" => "Gourmet", "14" => "Horror", "7" => "Mystery", "22" => "Romance", "24" => "Sci-Fi", "36" => "Slice of Life", "30" => "Sports", "37" => "Supernatural", "45" => "Suspense", "9" => "Ecchi", "49" => "Erotica", "12" => "Hentai", "50" => "Adult Cast", "51" => "Anthropomorphic", "52" => "CGDCT", "53" => "Childcare", "54" => "Combat Sports", "44" => "Crossdressing", "55" => "Delinquents", "39" => "Detective", "56" => "Educational", "57" => "Gag Humor", "58" => "Gore", "35" => "Harem", "59" => "High Stakes Game", "13" => "Historical", "60" => "Idols (Female)", "61" => "Idols (Male)", "62" => "Isekai", "63" => "Iyashikei", "64" => "Love Polygon", "75" => "Love Status Quo", "65" => "Magical Sex Shift", "66" => "Mahou Shoujo", "17" => "Martial Arts", "18" => "Mecha", "67" => "Medical", "68" => "Memoir", "38" => "Military", "19" => "Music", "6" => "Mythology", "69" => "Organized Crime", "70" => "Otaku Culture", "20" => "Parody", "71" => "Performing Arts", "72" => "Pets", "40" => "Psychological", "3" => "Racing", "73" => "Reincarnation", "74" => "Reverse Harem", "21" => "Samurai", "23" => "School", "76" => "Showbiz", "29" => "Space", "11" => "Strategy Game", "31" => "Super Power", "77" => "Survival", "78" => "Team Sports", "79" => "Time Travel", "83" => "Urban Fantasy", "32" => "Vampire", "80" => "Video Game", "81" => "Villainess", "82" => "Visual Arts", "48" => "Workplace", "42" => "Josei", "15" => "Kids", "41" => "Seinen", "25" => "Shoujo", "27" => "Shounen"
    ];
    // --- END: GENRE MAPPINGS ---

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
        if ($this->_error) return $this->_error;
        if (empty($method) || $method === 'getAllInfo') {
            if (method_exists($this, 'getAllInfo')) return $this->getAllInfo(...$arguments);
        }
        if (method_exists($this, $method)) return call_user_func_array([$this, $method], $arguments);
        return ['error' => "Method {$method} does not exist in " . __CLASS__];
    }

    private function getAnimeImage($each_anime)
    {
        if (!is_object($each_anime)) return '';
        $imageDiv = $each_anime->find('div.image', 0);
        if ($imageDiv && is_object($imageDiv)) {
            $imgTag = $imageDiv->find('img', 0);
            if ($imgTag && is_object($imgTag)) {
                if ($imgTag->hasAttribute('data-src')) return Helper::imageUrlCleaner($imgTag->getAttribute('data-src'));
                if ($imgTag->hasAttribute('src')) return Helper::imageUrlCleaner($imgTag->getAttribute('src'));
            }
        }
        return '';
    }

    private function getAnimeId($name_area)
    {
        if (!$name_area || !is_object($name_area)) return '';
        // For anime cards: div.title > a
        // For manga cards: div.title > div.title-text > h2 > a
        $linkNode = $name_area->find('a', 0); 
        if ($linkNode && is_object($linkNode) && isset($linkNode->href)) {
            $id_parts = explode('/', $linkNode->href);
            // Universal for /anime/ID/... and /manga/ID/...
            if (isset($id_parts[3]) && in_array($id_parts[3], ['anime', 'manga']) && isset($id_parts[4]) && is_numeric($id_parts[4])) {
                return $id_parts[4];
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
            if (isset($prod_id_parts[3]) && ($prod_id_parts[1] ?? '') == 'anime' && ($prod_id_parts[2] ?? '') == 'producer' && is_numeric($prod_id_parts[3])) return $prod_id_parts[3];
        } else { 
            if (isset($prod_id_parts[2]) && ($prod_id_parts[1] ?? '') == 'people' && is_numeric($prod_id_parts[2])) return $prod_id_parts[2];
            if (isset($prod_id_parts[3]) && ($prod_id_parts[1] ?? '') == 'manga' && ($prod_id_parts[2] ?? '') == 'magazine' && is_numeric($prod_id_parts[3])) return $prod_id_parts[3];
        }
        return '';
    }

    private function getAnimeProducerName($each_producer_link)
    {
        return (is_object($each_producer_link) && isset($each_producer_link->plaintext)) ? trim($each_producer_link->plaintext) : '';
    }

    private function _getStudioPageDetails()
    {
        if (!$this->_parser || !is_object($this->_parser)) return ['error_studio_details' => 'Parser not available.'];
        $studioPageDetails = ['studio_name' => '','logo_image_url' => '','info' => [],'description' => '','available_at_links' => [],'resource_links' => []];
        $studioNameNode = $this->_parser->find('h1.title-name', 0);
        if ($studioNameNode && is_object($studioNameNode) && isset($studioNameNode->plaintext)) {
            $studioPageDetails['studio_name'] = trim($studioNameNode->plaintext);
        }
        $contentLeft = $this->_parser->find('div.content-left', 0);
        if (!$contentLeft || !is_object($contentLeft)) return $studioPageDetails;
        if (empty($studioPageDetails['studio_name'])) {
            $logoImgForName = $contentLeft->find('div.logo img', 0);
            if ($logoImgForName && is_object($logoImgForName) && $logoImgForName->hasAttribute('alt')) {
                 $studioPageDetails['studio_name'] = trim($logoImgForName->getAttribute('alt'));
            }
        }
        $logoImg = $contentLeft->find('div.logo img', 0);
        if ($logoImg && is_object($logoImg)) {
            $studioPageDetails['logo_image_url'] = Helper::imageUrlCleaner($logoImg->getAttribute('data-src') ?: $logoImg->getAttribute('src'));
        }
        $studioInfo = [];
        $description = '';
        foreach ($contentLeft->find('div.spaceit_pad') as $pad) {
            if (!is_object($pad)) continue;
            $darkTextNode = $pad->find('span.dark_text', 0);
            if ($darkTextNode && is_object($darkTextNode) && isset($darkTextNode->plaintext)) {
                $label_text = trim($darkTextNode->plaintext);
                $label_key = trim(rtrim($label_text, ':'));
                $value = trim(str_ireplace($label_text, '', trim($pad->plaintext)));
                if (!empty($value)) {
                    switch (strtolower($label_key)) {
                        case 'japanese': $studioInfo['japanese_name'] = $value; break;
                        case 'established': $studioInfo['established_date'] = $value; break;
                        case 'member favorites': $studioInfo['member_favorites_count'] = trim(str_replace(',', '', $value)); break;
                        case 'synonyms': $studioInfo['synonyms'] = array_map('trim', explode(',', $value)); break;
                    }
                }
            } elseif (empty($description)) {
                $currentDescCandidate = trim($pad->plaintext);
                if (strlen($currentDescCandidate) > 50) $description = trim(preg_replace("/\s+/", " ", $currentDescCandidate));
            }
        }
        $studioPageDetails['info'] = $studioInfo;
        $studioPageDetails['description'] = $description;
        $availableAtLinks = [];
        $availableAtSection = $contentLeft->find('div.user-profile-sns', 0);
        if ($availableAtSection && is_object($availableAtSection)) {
            foreach ($availableAtSection->find('a') as $link) {
                if (is_object($link) && isset($link->href) && isset($link->plaintext)) {
                    $name = trim($link->plaintext); $url = trim($link->href);
                    if (!empty($name) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) $availableAtLinks[] = ['name' => $name, 'url' => $url];
                }
            }
        }
        $studioPageDetails['available_at_links'] = $availableAtLinks;
        $resourceLinks = [];
        $resourceHeader = null;
        foreach($contentLeft->find('h2') as $h2) {
            if (is_object($h2) && isset($h2->plaintext) && trim($h2->plaintext) == 'Resources') {
                $resourceHeader = $h2; break;
            }
        }
        if ($resourceHeader && is_object($resourceHeader)) {
            $resourceSection = $resourceHeader->next_sibling(); 
            while($resourceSection && is_object($resourceSection) && $resourceSection->tag !== 'div') $resourceSection = $resourceSection->next_sibling();
            if ($resourceSection && is_object($resourceSection) && strpos($resourceSection->class ?? '', 'pb16') !== false) {
                $links = $resourceSection->find('span a');
                if (empty($links)) $links = $resourceSection->find('a');
                foreach ($links as $link) {
                    if (is_object($link) && isset($link->href) && isset($link->plaintext)) {
                        $name = trim($link->plaintext); $url = trim($link->href);
                        if (!empty($name) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) $resourceLinks[] = ['name' => $name, 'url' => $url];
                    }
                }
            }
        }
        $studioPageDetails['resource_links'] = $resourceLinks;
        return $studioPageDetails;
    }

    private function getAnimeProducer($each_anime)
    {
        $producer = [];
        if (!is_object($each_anime)) return $producer;
        $properties_node = $each_anime->find('div.properties', 0); // Simplified from div.synopsis div.properties
        if ($properties_node && is_object($properties_node)) {
            $targetCaption = ($this->_type == 'anime') ? 'Studio' : 'Author'; // Manga uses "Author"
            foreach ($properties_node->find('div.property') as $propDiv) {
                if(!is_object($propDiv)) continue;
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && is_object($captionNode) && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                    foreach ($propDiv->find('span.item a') as $link) {
                        if (!is_object($link)) continue;
                        $temp_prod = [];
                        $temp_prod['id'] = $this->getAnimeProducerId($link);
                        $temp_prod['name'] = $this->getAnimeProducerName($link);
                        if (!empty($temp_prod['id']) || !empty($temp_prod['name'])) $producer[] = $temp_prod;
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
        // For manga: <span class="item"><span class="volume js-volume">24</span> vol, <span class="chapter js-chapter">96</span> chp</span>
        if ($this->_type == 'manga') {
            $info_node = $each_anime->find('div.prodsrc div.info span.item', 2); // Third span.item
            if ($info_node && is_object($info_node)) {
                $volumeNode = $info_node->find('span.volume', 0);
                if ($volumeNode && is_object($volumeNode) && isset($volumeNode->plaintext)) return trim($volumeNode->plaintext);
            }
        } else { // For anime
            $prodsrc_info_node = $each_anime->find('div.prodsrc div.info', 0);
            if ($prodsrc_info_node && is_object($prodsrc_info_node)) {
                $spans = $prodsrc_info_node->find('span.item');
                foreach ($spans as $span) {
                    if (is_object($span) && isset($span->plaintext) && strpos($span->plaintext, 'ep') !== false) {
                        if (preg_match('/(\d+)\s*ep/', $span->plaintext, $matches)) return $matches[1];
                    }
                }
            }
        }
        return '';
    }

    private function getAnimeSource($each_anime)
    {
        if (!is_object($each_anime)) return '';
        $properties_node = $each_anime->find('div.properties', 0); // Simplified
        if ($properties_node && is_object($properties_node)) {
            $targetCaption = 'Source'; // Always "Source" for anime
            foreach ($properties_node->find('div.property') as $propDiv) {
                if(!is_object($propDiv)) continue;
                $captionNode = $propDiv->find('span.caption', 0);
                if ($captionNode && is_object($captionNode) && isset($captionNode->plaintext) && trim($captionNode->plaintext) == $targetCaption) {
                    $itemNode = $propDiv->find('span.item', 0);
                    if ($itemNode && is_object($itemNode) && isset($itemNode->plaintext)) return trim($itemNode->plaintext);
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
        $currentGenreMap = ($this->_type == 'anime') ? self::$animeGenreMap : self::$mangaGenreMap;
        if ($each_anime->hasAttribute('data-genre')) {
            $genre_ids_str = $each_anime->getAttribute('data-genre');
            if (!empty($genre_ids_str)) {
                $genre_ids = explode(',', $genre_ids_str);
                foreach ($genre_ids as $id) {
                    $trimmed_id = trim($id);
                    if (isset($currentGenreMap[$trimmed_id])) $genres[] = $currentGenreMap[$trimmed_id];
                }
            }
        }
        if (empty($genres)) {
            $genre_container = $each_anime->find('div.genres.js-genre div.genres-inner', 0); 
            if ($genre_container && is_object($genre_container)) {
                foreach ($genre_container->find('span.genre a') as $link) {
                    if (is_object($link) && isset($link->plaintext)) $genres[] = trim($link->plaintext);
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
                return trim(preg_replace("/([\s])+/", ' ', $paragraph_node->plaintext));
            }
        }
        return '';
    }

    private function getAnimeLicensor($each_anime) // For manga, this is Serialization
    {
        if (!is_object($each_anime)) return ($this->_type == 'anime') ? [] : '';
        if ($this->_type == 'anime') {
            $synopsisNode = $each_anime->find('div.synopsis.js-synopsis', 0);
            if ($synopsisNode && is_object($synopsisNode)) {
                $licensorNode = $synopsisNode->find('span.licensors', 0); 
                if ($licensorNode && is_object($licensorNode) && $licensorNode->hasAttribute('data-licensors')) {
                    return array_filter(explode(',', $licensorNode->getAttribute('data-licensors')));
                }
            }
            return [];
        } else { // Manga: Find Serialization
            $properties_node = $each_anime->find('div.properties', 0); // Serialization is in properties
            if ($properties_node && is_object($properties_node)) {
                foreach ($properties_node->find('div.property') as $propDiv) {
                    if(!is_object($propDiv)) continue;
                    $captionNode = $propDiv->find('span.caption', 0);
                    if ($captionNode && is_object($captionNode) && isset($captionNode->plaintext) && trim($captionNode->plaintext) == 'Serialization') {
                        $itemNode = $propDiv->find('span.item a', 0); // Usually a link
                        if ($itemNode && is_object($itemNode) && isset($itemNode->plaintext)) return trim($itemNode->plaintext);
                        break;
                    }
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
                return trim($parts[0]); // e.g., "Movie" or "Manga"
            }
        }
        return '';
    }

    private function getAnimeStart($each_anime)
    {
        if (!is_object($each_anime)) return '';
        $jsStartDate = $each_anime->find('span.js-start_date', 0); // Anime cards
        if ($jsStartDate && is_object($jsStartDate) && isset($jsStartDate->plaintext)) return trim($jsStartDate->plaintext);
        // For Manga cards: "Manga, 2004"
        $prodsrc_info_node = $each_anime->find('div.prodsrc div.info', 0);
        if ($prodsrc_info_node && is_object($prodsrc_info_node)) {
            $itemSpans = $prodsrc_info_node->find('span.item');
            if (isset($itemSpans[0]) && is_object($itemSpans[0]) && isset($itemSpans[0]->plaintext)) {
                $full_text = $itemSpans[0]->plaintext; // "Manga, 2004"
                $parts = explode(',', $full_text);
                if (isset($parts[1])) return trim($parts[1]); // "2004"
            }
        }
        return '';
    }

    private function getAnimeScore($each_anime)
    {
        if (!is_object($each_anime)) return 'N/A';
        $jsScore = $each_anime->find('span.js-score', 0); // For Anime cards
        if ($jsScore && is_object($jsScore) && isset($jsScore->plaintext)) {
            $score = trim($jsScore->plaintext);
            return (is_numeric($score) && (float)$score >= 0) ? $score : 'N/A';
        }
        // For Manga cards: div.information div.scormem-item.score
        $infoNode = $each_anime->find('div.information', 0);
        if ($infoNode && is_object($infoNode)) {
             $scoreNode = $infoNode->find('div.scormem-item.score', 0);
             if ($scoreNode && is_object($scoreNode) && isset($scoreNode->plaintext)) {
                 if(preg_match('/(\d+\.?\d*)/', $scoreNode->plaintext, $matches)) return $matches[1];
             }
        }
        // Fallback for Anime cards' visible score
        $widget = $each_anime->find('div.widget', 0);
        if ($widget && is_object($widget)) {
            $starsNode = $widget->find('div.stars', 0);
            if ($starsNode && is_object($starsNode) && isset($starsNode->plaintext)) {
                if(preg_match('/(\d+\.?\d*)/', $starsNode->plaintext, $matches)) return $matches[1];
            }
        }
        return 'N/A';
    }

    private function getAnimeMember($each_anime)
    {
        if (!is_object($each_anime)) return '0';
        $jsMembers = $each_anime->find('span.js-members', 0); // For Anime cards
        if ($jsMembers && is_object($jsMembers) && isset($jsMembers->plaintext)) {
            return trim(preg_replace('/[^\d]/', '', $jsMembers->plaintext));
        }
        // For Manga cards: div.information div.scormem-item.member
        $infoNode = $each_anime->find('div.information', 0);
        if ($infoNode && is_object($infoNode)) {
            $memberNode = $infoNode->find('div.scormem-item.member', 0);
            if ($memberNode && is_object($memberNode) && isset($memberNode->plaintext)) {
                $memberText = trim($memberNode->plaintext);
                if (preg_match('/([\d\.,]+(?:K|M)?)/i', $memberText, $matches)) {
                    $countStr = strtoupper(str_replace(',', '', $matches[1])); $count = 0;
                    if (strpos($countStr, 'M') !== false) $count = (float)str_replace('M', '', $countStr) * 1000000;
                    elseif (strpos($countStr, 'K') !== false) $count = (float)str_replace('K', '', $countStr) * 1000;
                    else $count = (float)$countStr;
                    return (string)(int)$count;
                }
            }
        }
        // Fallback for Anime cards' visible members
        $widget = $each_anime->find('div.widget', 0);
        if ($widget && is_object($widget)) {
            $usersNode = $widget->find('div.users', 0);
            if ($usersNode && is_object($usersNode) && isset($usersNode->plaintext)) {
                return trim(preg_replace('/[^\d]/', '', $usersNode->plaintext));
            }
        }
        return '0';
    }
    
    public function getAllInfo()
    {
        if ($this->_error) return $this->_error;
        if (!$this->_parser || !is_object($this->_parser)) {
            $this->_error = ['error' => 'Parser not initialized or invalid.']; return $this->_error;
        }

        $outputData = [];
        if ($this->_type == 'anime') { // Studio details are only for anime producer pages
            $studioPageDetails = $this->_getStudioPageDetails();
            if (is_array($studioPageDetails)) {
                foreach ($studioPageDetails as $key => $value) {
                    if ($key !== 'error_studio_details') $outputData[$key] = $value;
                    elseif (!empty($value)) $outputData['studio_details_error'] = $value;
                }
            }
        } else { // For manga, initialize with empty studio details
            $outputData = ['studio_name' => '','logo_image_url' => '','info' => [],'description' => '','available_at_links' => [],'resource_links' => []];
        }

        $animeListData = [];
        // Use a flexible selector for the list of items
        $anime_table = $this->_parser->find('div.seasonal-anime.js-seasonal-anime');

        if (is_array($anime_table)) {
            foreach ($anime_table as $each_anime) {
                if(!is_object($each_anime)) continue; 
                $result = [];
                // The title div is structured differently in each card type
                $name_area = $each_anime->find('div.title', 0);
                
                $result['id'] = ''; $result['title'] = ''; $result['image'] = '';
                if ($name_area && is_object($name_area)) {
                    $result['id'] = $this->getAnimeId($name_area);
                    $result['title'] = $this->getAnimeTitle($name_area);
                }
                $result['image'] = $this->getAnimeImage($each_anime);
                if (empty($result['id'])) continue; 

                // --- Rest of the fields ---
                $result['genre'] = $this->getAnimeGenre($each_anime);
                $result['synopsis'] = $this->getAnimeSynopsis($each_anime);
                $result['source'] = $this->getAnimeSource($each_anime);

                if ($this->_type == 'anime') {
                    $result['producer'] = $this->getAnimeProducer($each_anime);
                    $result['episode'] = $this->getAnimeEpisode($each_anime);
                    $result['licensor'] = $this->getAnimeLicensor($each_anime); 
                    $result['type'] = $this->getAnimeType($each_anime);
                } else { // Manga fields
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

        // Merge the anime/manga list into the output
        foreach ($animeListData as $index => $animeItem) {
            $outputData[(string)$index] = $animeItem;
        }
        
        return $outputData;
    }
}