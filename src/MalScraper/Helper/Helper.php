<?php

namespace MalScraper\Helper;
ini_set("log_errors", TRUE);
ini_set("error_log", "error_log");

/**
 *	Helper class.
 */
class Helper
{
    /**
     * Convert return result into easy-to-read result.
     *
     * @param string|array $response
     *
     * @return string|array
     */
    public static function toResponse($response)
    {
        switch ($response) {
            case 400:
                return 'Search query needs at least 3 letters';
            case 403:
                return 'Private user list';
            case 404:
                return 'Page not found';
            default:
                return $response;
        }
    }

    /**
     * Convert return result into http response.
     *
     * @param string|array $response
     *
     * @return string
     */
    public static function response($response)
    {
        $result = [];
        if (is_numeric($response)) {
            header('HTTP/1.1 '.$response);
            $result['status'] = $response;
            $result['status_message'] = self::toResponse($response);
            $result['data'] = [];
        } else {
            header('HTTP/1.1 '. 200);
            $result['status'] = 200;
            $result['status_message'] = 'Success';
            $result['data'] = self::superEncode($response);
        }

        $json_response = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $json_response = str_replace('\\\\', '', $json_response);

        return $json_response;
    }

    /**
     * Convert characters to UTF-8.
     *
     * @param array|string $array
     *
     * @return array|string
     */
    private static function superEncode($array)
    {
        if (is_array($array) && !empty($array)) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $array[$key] = self::superEncode($value);
                } else {
					if (!is_null($value)) {
						$array[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
					}
                }
            }
        }

        return $array;
    }

    /**
     * Get top anime code.
     *
     * @return array
     */
    public static function getTopAnimeType()
    {
        return [
            '',
            'airing',
            'upcoming',
            'tv',
            'movie',
            'ova',
            'special',
            'bypopularity',
            'favorite',
        ];
    }

    /**
     * Get top manga code.
     *
     * @return array
     */
    public static function getTopMangaType()
    {
        return [
            '',
            'manga',
            'novel',
            'oneshots',
            'doujin',
            'manhwa',
            'manhua',
            'bypopularity',
            'favorite',
        ];
    }

    /**
     * Get current season.
     *
     * @return string
     */
    public static function getCurrentSeason()
    {
        $currentMonth = date('m');

        if ($currentMonth >= '01' && $currentMonth < '04') {
            return 'winter';
        }
        if ($currentMonth >= '04' && $currentMonth < '07') {
            return 'spring';
        }
        if ($currentMonth >= '07' && $currentMonth < '10') {
            return 'summer';
        }

        return 'fall';
    }

    /**
     * Clean image URL.
     *
     * @param string $str
     *
     * @return string
     */
    public static function imageUrlCleaner($str)
    {
        preg_match('/(questionmark)|(qm_50)/', $str, $temp_image);
        $str = $temp_image ? '' : $str;
        $str = str_replace(['v.jpg', '.jpg'], '.jpg', $str);
        $str = str_replace('_thumb.jpg', '.jpg', $str);
        $str = str_replace('userimages/thumbs', 'userimages', $str);
        $str = preg_replace('/r\/\d{1,3}x\d{1,3}\//', '', $str);
        $str = preg_replace('/\?.+/', '', $str);
        $str = str_replace('.jpg', 'l.jpg', $str);

        return $str;
    }

    /**
     * Replace image URL.
     *
     * @param string $str
     *
     * @return string
     */
    public static function imageUrlReplace($str, $type, $orig)
    {
		if ($type == 'anime') {
			switch ($str) {
				case '38339':
					$str = 'https://shaggyze.website/Themes/covers/suzumi_bune.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				case '1317':
					$str = 'https://shaggyze.website/Themes/covers/eyeshield_21__maboroshi_no_golden_bowl.png';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				case '54757':
					$str = 'https://shaggyze.website/Themes/covers/3-nen_z-gumi_ginpachi-sensei.webp';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				/*case '58755':
					$str = 'https://shaggyze.website/Themes/covers/5-toubun_no_hanayome.png';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;*/
				case '55408':
					$str = 'https://shaggyze.website/Themes/covers/100_manten_pax_salomena.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				case '43879':
					$str = 'https://shaggyze.website/Themes/covers/curry_meshi_in_miracle.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				/*case '57554':
					$str = 'https://shaggyze.website/Themes/covers/rurouni_kenshin__meiji_kenkaku_romantan_-_kyoto_douran.jpeg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;*/
				/*case '50980':
					$str = 'https://cdn.myanimelist.net/images/anime/1763/120846l.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;*/
				case '56715':
					$str = 'https://cdn.myanimelist.net/images/anime/1217/138638l.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				/*case '55569':
					$str = 'https://cdn.myanimelist.net/images/anime/1236/136294l.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;*/
				case '55826':
					$str = 'https://cdn.myanimelist.net/images/anime/1217/138638l.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				/*case '52420':
					$str = 'https://cdn.myanimelist.net/images/anime/1818/127729l.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;*/
				/*case '52575':
					$str = 'https://cdn.myanimelist.net/images/anime/1012/126441l.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;*/
				case '56894':
					$str = 'https://shaggyze.website/Themes/covers/dragon_ball_daima.jpg';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				case '4772':
					$str = 'https://shaggyze.website/Themes/covers/aria_the_origination_episode_5.5.png';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				case '59463':
					$str = 'https://shaggyze.website/Themes/covers/majo_no_furo_life.webp';
					error_log('PHP Notice:  Compare  ' . $str . ' with ' . $orig);
					return $str;
				default:
					if (!empty($orig)) {
						return $orig;
					} else {
						error_log('PHP Notice:  Missing  https://myanimelist.net/anime/' . $str);
						return ('https://shaggyze.website/Themes/covers/unavailable.png');
					}
			}
		} else {
			switch ($str) {
				default:
					if (!empty($orig)) {
						return $orig;
					} else {
						error_log('PHP Notice:  Missing  https://myanimelist.net/manga/' . $str);
						return ('https://shaggyze.website/Themes/covers/unavailable.png');
					}
			}
		}
	}

    /**
     * Clean video URL.
     *
     * @param string $str
     *
     * @return string
     */
    public static function videoUrlCleaner($str)
    {
        $str = preg_replace('/\?.+/', '', $str);
        $str = str_replace('embed/', 'watch?v=', $str);

        return $str;
    }
}
