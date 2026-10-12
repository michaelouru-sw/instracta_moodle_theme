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

namespace theme_instracta\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for the Instracta theme.
 *
 * The theme keeps no personal data of its own: the colour mode and the reading and layout choices are user preferences, and the
 * page builder tables record only which administrator last changed a layout.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {

    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('instracta-color-mode', 'privacy:metadata:preference:colormode');
        $collection->add_user_preference('instracta-reading', 'privacy:metadata:preference:reading');
        $collection->add_database_table('theme_instracta_page', [
            'usermodified' => 'privacy:metadata:page:usermodified',
            'timemodified' => 'privacy:metadata:page:timemodified',
        ], 'privacy:metadata:page');
        return $collection;
    }

    /**
     * Export the user's colour mode preference.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        $mode = get_user_preferences('instracta-color-mode', null, $userid);
        if ($mode !== null) {
            \core_privacy\local\request\writer::export_user_preference(
                'theme_instracta',
                'instracta-color-mode',
                $mode,
                get_string('privacy:metadata:preference:colormode', 'theme_instracta')
            );
        }
        $reading = get_user_preferences('instracta-reading', null, $userid);
        if ($reading !== null && $reading !== '') {
            \core_privacy\local\request\writer::export_user_preference(
                'theme_instracta',
                'instracta-reading',
                $reading,
                get_string('privacy:metadata:preference:reading', 'theme_instracta')
            );
        }
    }
}
