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
 * Shared page layout logic: Boost's drawer layout context, extended by Instracta's page layouts.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout {

    /**
     * Build the template context of Boost's drawers layout.
     *
     * This mirrors theme_boost's layout/drawers.php so Instracta's layouts behave identically
     * (course index, block drawer, secondary navigation, activity header).
     *
     * @param array $extraclasses additional body classes
     * @return array
     */
    public static function drawers_context(array $extraclasses = []): array {
        global $OUTPUT, $PAGE, $SITE, $CFG;

        require_once($CFG->libdir . '/behat/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $addblockbutton = $OUTPUT->addblockbutton();

        if (isloggedin()) {
            $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
            $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
        } else {
            $courseindexopen = false;
            $blockdraweropen = false;
        }

        if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
            $blockdraweropen = true;
        }

        $classes = array_merge(['uses-drawers'], $extraclasses);
        if ($courseindexopen) {
            $classes[] = 'drawer-open-index';
        }

        $blockshtml = $OUTPUT->blocks('side-pre');
        $hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
        if (!$hasblocks) {
            $blockdraweropen = false;
        }
        $courseindex = core_course_drawer();
        if (!$courseindex) {
            $courseindexopen = false;
        }

        $forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

        $secondarynavigation = false;
        $overflow = '';
        if ($PAGE->has_secondary_navigation()) {
            $tablistnav = $PAGE->has_tablist_secondary_navigation();
            $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
            $secondarynavigation = $moremenu->export_for_template($OUTPUT);
            $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
            if (!is_null($overflowdata)) {
                $overflow = $overflowdata->export_for_template($OUTPUT);
            }
        }

        $primary = new \core\navigation\output\primary($PAGE);
        $renderer = $PAGE->get_renderer('core');
        $primarymenu = $primary->export_for_template($renderer);
        $buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
        $regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

        $header = $PAGE->activityheader;
        $headercontent = $header->export_for_template($renderer);

        return [
            'sitename' => format_string($SITE->shortname, true, ['context' => \context_course::instance(SITEID), 'escape' => false]),
            'output' => $OUTPUT,
            'sidepreblocks' => $blockshtml,
            'hasblocks' => $hasblocks,
            'courseindexopen' => $courseindexopen,
            'blockdraweropen' => $blockdraweropen,
            'courseindex' => $courseindex,
            'primarymoremenu' => $primarymenu['moremenu'],
            'secondarymoremenu' => $secondarynavigation ?: false,
            'mobileprimarynav' => $primarymenu['mobileprimarynav'],
            'usermenu' => $primarymenu['user'],
            'langmenu' => $primarymenu['lang'],
            'forceblockdraweropen' => $forceblockdraweropen,
            'regionmainsettingsmenu' => $regionmainsettingsmenu,
            'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
            'overflow' => $overflow,
            'headercontent' => $headercontent,
            'addblockbutton' => $addblockbutton,
            // Body attributes are built last, once every class has been decided.
            'bodyclasses' => $classes,
        ];
    }

    /**
     * Finish a context: compute the body attributes from the classes collected.
     *
     * @param array $context
     * @param array $moreclasses
     * @return array
     */
    public static function finish(array $context, array $moreclasses = []): array {
        global $OUTPUT;
        $classes = array_merge($context['bodyclasses'], $moreclasses);
        unset($context['bodyclasses']);
        $context['bodyattributes'] = $OUTPUT->body_attributes($classes);
        return $context;
    }
}
