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

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;

/**
 * Output hook callbacks. They only act when Instracta is the theme in use.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Whether the page being rendered uses Instracta (or a child of it).
     *
     * @return bool
     */
    protected static function active(): bool {
        global $PAGE;
        if (during_initial_install() || !isset($PAGE)) {
            return false;
        }
        return $PAGE->theme->name === 'instracta' || in_array('instracta', (array) ($PAGE->theme->parents ?? []), true);
    }

    /**
     * Sets colour mode, reading preferences and layout choices before first paint, so the page never
     * flashes in the wrong look.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_head(before_standard_head_html_generation $hook): void {
        global $PAGE;
        if (!self::active()) {
            return;
        }
        $tokens = tokens::resolve($PAGE->theme);
        $loggedin = isloggedin() && !isguestuser();
        $default = in_array($tokens['colormode'], ['auto', 'light', 'dark'], true) ? $tokens['colormode'] : 'auto';
        $userpref = $loggedin ? get_user_preferences('instracta-color-mode', '') : '';
        $userpref = in_array($userpref, ['auto', 'light', 'dark'], true) ? $userpref : '';

        $config = [
            'mode' => ['d' => $default, 'u' => $userpref, 't' => (int) $tokens['colortoggle']],
            'tools' => (int) self::tools_enabled($PAGE->theme),
            'lay' => (int) self::learner_layouts_enabled($PAGE->theme),
            'site' => reading::site_defaults($PAGE->theme),
            'user' => $loggedin ? (object) reading::sanitize(get_user_preferences(reading::PREF, '')) : (object) [],
        ];
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $script = '(function(){try{var C=' . $json . ',R=document.documentElement,M=C.mode;' .
            'var m=M.t?(localStorage.getItem("instracta-color-mode")||M.u||M.d):M.d;' .
            'var dark=m==="dark"||(m==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches);' .
            'R.setAttribute("data-instracta-theme",dark?"dark":"light");R.setAttribute("data-instracta-mode",m);' .
            'var P={},k,S={},L={cl:1,ch:1,cs:1,ci:1};' .
            'for(k in C.site)P[k]=C.site[k];' .
            'if(C.tools){try{S=JSON.parse(localStorage.getItem("instracta-reading")||"{}")||{}}catch(e){}' .
            'if(typeof S!=="object")S={};' .
            'for(k in C.user)if(C.lay||!L[k])P[k]=C.user[k];' .
            'for(k in S)if(typeof S[k]==="string"&&(C.lay||!L[k]))P[k]=S[k];}' .
            'for(k in P){if(P[k]!==""&&P[k]!=="0"&&P[k]!=="100"&&P[k]!=="default")R.setAttribute("data-i-"+k,P[k]);}' .
            '}catch(e){}})();';
        $hook->add_html(\html_writer::tag('script', $script));
        $css = features::category_css();
        if ($css !== '') {
            $hook->add_html(\html_writer::tag('style', $css));
        }
    }

    /**
     * Notices and teacher hints. A script moves them to the top of the main area.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function top_of_body(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        if (!self::active()) {
            return;
        }
        $hook->add_html(features::notices_html());
    }

    /**
     * Adds the configured menu items to the primary navigation.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (!self::active()) {
            return;
        }
        features::extend_primary($hook->get_primaryview());
    }

    /**
     * Whether visitors get the reading tools panel.
     *
     * @param \theme_config|null $theme
     * @return bool
     */
    public static function tools_enabled(?\theme_config $theme = null): bool {
        return (bool) tokens::setting($theme, 'readingtools', 1);
    }

    /**
     * Whether visitors may choose their own course layout.
     *
     * @param \theme_config|null $theme
     * @return bool
     */
    public static function learner_layouts_enabled(?\theme_config $theme = null): bool {
        return (bool) tokens::setting($theme, 'learnerlayouts', 1);
    }

    /**
     * Loads the theme behaviour module: colour mode toggle, reading tools, course focus mode and navbar state.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE;
        if (!self::active()) {
            return;
        }
        $tokens = tokens::resolve($PAGE->theme);
        // Strings travel through the core string loader: js_call_amd arguments are limited to 1 KB.
        $keys = ['colormode_dark', 'colormode_light', 'colormode_toggle'];
        foreach (array_keys(get_string_manager()->load_component_strings('theme_instracta', 'en')) as $key) {
            if (preg_match('/^(rt_|cl_|ch_|cs_|ci_|fn_|ft_)/', $key)) {
                $keys[] = $key;
            }
        }
        $hook->add_html(features::activity_bar() . features::mobile_bar() . features::cookie_html());
        $PAGE->requires->strings_for_js($keys, 'theme_instracta');

        $PAGE->requires->js_call_amd('theme_instracta/theme', 'init', [[
            'toggle' => (bool) $tokens['colortoggle'],
            'sticky' => (bool) $tokens['navbarsticky'],
            'mode' => $tokens['colormode'],
            'loggedin' => isloggedin() && !isguestuser(),
            'tools' => self::tools_enabled($PAGE->theme),
            'f' => [(int) features::on('backtotop', true), (int) features::on('readprogress', true),
                (int) features::on('readtime', true), (int) features::on('extlinks', false)],
            'layouts' => self::learner_layouts_enabled($PAGE->theme),
            'site' => reading::site_defaults($PAGE->theme),
            'options' => [
                'cl' => array_keys(reading::course_layouts()),
                'ch' => array_keys(reading::course_headers()),
                'cs' => array_keys(reading::card_styles()),
                'ci' => array_keys(reading::index_layouts()),
            ],
        ]]);
    }
}
