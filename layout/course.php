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

/**
 * Course page layout: Boost's drawers layout plus the Instracta course banner.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$extra = [];
$banner = null;
// The banner belongs on the course home page only, not on settings or other pages that share this layout.
if ($PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE) && !empty($PAGE->course->id)) {
    $banner = theme_instracta\course_banner::context($PAGE->course);
    if ($banner) {
        $extra[] = 'instracta-has-banner';
    }
}

$context = theme_instracta\layout::drawers_context($extra);
$context['coursebanner'] = $banner ?: false;
$context = theme_instracta\layout::finish($context);

echo $OUTPUT->render_from_template('theme_instracta/drawers', $context);
