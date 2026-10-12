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

namespace theme_instracta\output;

use theme_instracta\builder\registry;
use theme_instracta\tokens;

/**
 * Instracta core renderer: body classes that drive the navbar style, and the brand footer.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /** @var string[] Page layouts that never get the brand footer. */
    const NO_FOOTER_LAYOUTS = ['login', 'maintenance', 'popup', 'embedded', 'redirect', 'print', 'frametop', 'secure'];

    /**
     * Adds Instracta classes to the body.
     *
     * @param array $additionalclasses
     * @return string
     */
    public function body_css_classes(array $additionalclasses = []) {
        $tokens = tokens::resolve($this->page->theme);
        $style = in_array($tokens['navbarstyle'], ['glass', 'solid', 'transparent'], true) ? $tokens['navbarstyle'] : 'glass';
        $additionalclasses[] = 'instracta';
        $additionalclasses[] = 'instracta-navbar-' . $style;
        if (!empty($this->page->theme->setting_file_url('sitebackground', 'sitebackground'))) {
            $additionalclasses[] = 'instracta-has-sitebg';
        }
        return parent::body_css_classes($additionalclasses);
    }

    /**
     * Turn a setting like "Label|https://example.org" lines into link data.
     *
     * @param string $text
     * @return array
     */
    protected function parse_links(string $text): array {
        global $CFG;
        $links = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) < 2) {
                continue;
            }
            $url = registry::sanitize_url($parts[1]);
            if ($url === '') {
                continue;
            }
            if ($url[0] === '/' && substr($url, 0, 2) !== '//') {
                $url = $CFG->wwwroot . $url;
            }
            $links[] = ['text' => clean_param($parts[0], PARAM_TEXT), 'url' => $url];
        }
        return $links;
    }

    /**
     * The brand footer, built from the theme settings. Empty when switched off.
     *
     * @return string
     */
    public function instracta_footer(): string {
        global $SITE, $CFG;

        $theme = $this->page->theme;
        if (!(int) tokens::setting($theme, 'footerenabled', 1)
                || in_array($this->page->pagelayout, self::NO_FOOTER_LAYOUTS, true)) {
            return '';
        }

        $columns = [];
        for ($i = 1; $i <= 3; $i++) {
            $title = trim((string) tokens::setting($theme, 'footertitle' . $i, ''));
            $links = $this->parse_links((string) tokens::setting($theme, 'footerlinks' . $i, ''));
            if ($links) {
                $columns[] = ['title' => $title, 'links' => $links];
            }
        }
        // A sensible default so a fresh install has a useful footer.
        if (!$columns) {
            $columns[] = [
                'title' => get_string('footer_quicklinks', 'theme_instracta'),
                'links' => [
                    ['text' => get_string('home'), 'url' => $CFG->wwwroot . '/'],
                    ['text' => get_string('courses'), 'url' => $CFG->wwwroot . '/course/index.php'],
                    ['text' => get_string('mymoodle', 'admin'), 'url' => $CFG->wwwroot . '/my/'],
                ],
            ];
        }

        $social = [];
        $networks = [
            'facebook' => 'facebook-f', 'x' => 'x-twitter', 'instagram' => 'instagram',
            'linkedin' => 'linkedin-in', 'youtube' => 'youtube', 'whatsapp' => 'whatsapp',
        ];
        foreach ($networks as $key => $icon) {
            $url = registry::sanitize_url((string) tokens::setting($theme, 'social_' . $key, ''));
            if ($url !== '') {
                $social[] = ['url' => $url, 'icon' => $icon, 'name' => get_string('social_' . $key, 'theme_instracta')];
            }
        }

        $sitename = format_string($SITE->shortname, true, ['context' => \context_course::instance(SITEID)]);
        $copyright = trim((string) tokens::setting($theme, 'footercopyright', ''));
        $copyright = $copyright !== '' ? format_string($copyright) : '© ' . date('Y') . ' ' . $sitename;
        $about = trim((string) tokens::setting($theme, 'footerabout', ''));

        return $this->render_from_template('theme_instracta/footer_brand', [
            'sitename' => $sitename,
            'about' => $about !== '' ? format_text($about, FORMAT_HTML, ['context' => \context_system::instance()]) : '',
            'hasabout' => $about !== '',
            'columns' => $columns,
            'social' => $social,
            'hassocial' => !empty($social),
            'copyright' => $copyright,
        ]);
    }
}
