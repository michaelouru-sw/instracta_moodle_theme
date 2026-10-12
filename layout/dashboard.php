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
 * Dashboard layout: Boost's drawers layout plus the learner insights strip.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$onhome = ($PAGE->pagetype === 'my-index');
$context = theme_instracta\layout::drawers_context($onhome ? ['instracta-hub-page'] : []);
$context['homeactive'] = false;
$context['hasmain'] = true;

$strip = '';
if ($onhome && theme_instracta\tokens::setting($PAGE->theme, 'learnerstrip', 1) && isloggedin() && !isguestuser()) {
    $data = theme_instracta\analytics\learner::strip_context((int) $USER->id);
    if ($data) {
        $strip = $OUTPUT->render_from_template('theme_instracta/learner_strip', $data);
    }
}
$context['dashboardstrip'] = $strip;
$context = theme_instracta\layout::finish($context);

echo $OUTPUT->render_from_template('theme_instracta/drawers', $context);
