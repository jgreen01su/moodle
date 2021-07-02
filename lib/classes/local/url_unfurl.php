<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Receives Open Graph protocol metadata from a link
 *
 * @package    core
 * @copyright  2021 Jon Green <jgreen01@stanford.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unfurl {
    public $title = '';
    public $site_name = '';
    public $image = '';
    public $description = '';
    public $canonical_url = '';
    public $no_og_metadata = true;

    function __construct($url) {
        $html = file_get_contents($url);

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html); // defaults to ISO-8859-1 encoding unless told otherwise
        $meta_tag_list = $doc->getElementsByTagName('meta');

        foreach($meta_tag_list as $meta_tag) {
            $property_attribute = strtolower(s($meta_tag->getAttribute('property')));
            if (
                !empty($property_attribute) &&
                preg_match ('/^og:\w+/i', $property_attribute) === 1
            ) {
                $this->no_og_metadata = false;
                break;
            }
        }

        if ($this->no_og_metadata) {
            return;
        }

        foreach($meta_tag_list as $meta_tag) {
            $property_attribute = strtolower(s($meta_tag->getAttribute('property')));
            $content_attribute = s($meta_tag->getAttribute('content'));
            if (
                !empty($property_attribute) &&
                !empty($content_attribute) &&
                preg_match ('/^og:\w+/i', $property_attribute) === 1
            ) {
                switch ($property_attribute) {
                    case 'og:title':
                        $this->title = $content_attribute;
                        break;
                    case 'og:site_name':
                        $this->site_name = $content_attribute;
                        break;
                    case 'og:image':
                        // check that image url has a host because some websites only give the path
                        $imageurl_parts = parse_url($content_attribute);
                        if (empty($imageurl_parts['host']) && !empty($imageurl_parts['path'])) {
                            $url_parts = parse_url($url);
                            $this->image = $url_parts['scheme'].'://'.$url_parts['host'].$imageurl_parts['path'];
                        } else {
                            $this->image = $content_attribute;
                        }
                        break;
                    case 'og:description':
                        $this->description = $content_attribute;
                        break;
                    case 'og:url':
                        $this->canonical_url = $content_attribute;
                        break;
                    default:
                        break;
                }
            }
        }
    }
}

/**
 * Receives and stores unfurled link metadata
 *
 * @package    core
 * @copyright  2021 Jon Green <jgreen01@stanford.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unfurl_store {
    private static $instance = null;
    private $cache;

    private function __construct() {
        $this->cache = cache::make('core', 'unfurl');
    }

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new unfurl_store();
        }

        return self::$instance;
    }

    public function get_unfurl($url) {
        $cached_metadata = $this->cache->get($url);

        if ($cached_metadata) {
            return $cached_metadata;
        }

        $metadata = new unfurl($url);
        $this->cache->set($url, $metadata);

        return $metadata;
    }
}