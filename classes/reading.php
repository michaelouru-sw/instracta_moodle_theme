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

namespace theme_instracta;

/**
 * Reading and layout preferences: what a visitor may choose, validated.
 *
 * Preferences live in the browser (so they apply before first paint) and, for logged in users, in one
 * user preference so they follow the person across devices. Whatever comes back from either place is
 * passed through {@see reading::sanitize()} before it reaches a page.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reading {

    /** @var string Name of the user preference. */
    const PREF = 'instracta-reading';

    /**
     * What each preference may hold.
     *
     * @return array key => allowed values
     */
    public static function allowed(): array {
        return [
            'size' => ['90', '100', '110', '120', '135', '150'],
            'lh' => ['default', 'relaxed', 'loose'],
            'measure' => ['narrow', 'default', 'wide', 'full'],
            'font' => ['default', 'easy', 'serif'],
            'contrast' => ['0', '1'],
            'motion' => ['0', '1'],
            'focus' => ['0', '1'],
            'cl' => array_keys(self::course_layouts()),
            'ch' => array_keys(self::course_headers()),
            'cs' => array_keys(self::card_styles()),
            'ci' => array_keys(self::index_layouts()),
        ];
    }

    /**
     * Course page layouts: key => language string key.
     *
     * @return array
     */
    public static function course_layouts(): array {
        return ['classic' => 'cl_classic', 'reading' => 'cl_reading', 'timeline' => 'cl_timeline', 'cards' => 'cl_cards',
            'compact' => 'cl_compact', 'focus' => 'cl_focus'];
    }

    /**
     * Course header styles.
     *
     * @return array
     */
    public static function course_headers(): array {
        return ['hero' => 'ch_hero', 'split' => 'ch_split', 'minimal' => 'ch_minimal', 'none' => 'ch_none'];
    }

    /**
     * Course card styles.
     *
     * @return array
     */
    public static function card_styles(): array {
        return ['outline' => 'cs_outline', 'elevated' => 'cs_elevated', 'cover' => 'cs_cover', 'list' => 'cs_list'];
    }

    /**
     * Course index (course listing) layouts.
     *
     * @return array
     */
    public static function index_layouts(): array {
        return ['grid' => 'ci_grid', 'list' => 'ci_list', 'compact' => 'ci_compact'];
    }

    /**
     * Keep only known keys with known values.
     *
     * @param mixed $raw JSON string or an array
     * @return array
     */
    public static function sanitize($raw): array {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach (self::allowed() as $key => $values) {
            if (isset($raw[$key]) && is_scalar($raw[$key]) && in_array((string) $raw[$key], $values, true)) {
                $clean[$key] = (string) $raw[$key];
            }
        }
        return $clean;
    }

    /**
     * The site wide defaults for the layout choices (what a visitor sees before choosing anything).
     *
     * @param \theme_config|null $theme
     * @return array
     */
    public static function site_defaults(?\theme_config $theme = null): array {
        $pick = function (string $name, array $options, string $fallback) use ($theme): string {
            $value = (string) tokens::setting($theme, $name, $fallback);
            return array_key_exists($value, $options) ? $value : $fallback;
        };
        return [
            'cl' => $pick('courselayout', self::course_layouts(), 'reading'),
            'ch' => $pick('courseheader', self::course_headers(), 'hero'),
            'cs' => $pick('cardstyle', self::card_styles(), 'outline'),
            'ci' => $pick('courseindex', self::index_layouts(), 'grid'),
        ];
    }
}
