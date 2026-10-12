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
 * Course landing page: what the course is, what it covers, who teaches it and how to join.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
$coursecontext = context_course::instance($course->id);
if ($course->id == SITEID) {
    redirect(new moodle_url('/'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/theme/instracta/course.php', ['id' => $course->id]);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('instracta-landing');
if (!empty($CFG->forcelogin)) {
    require_login();
}
if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
    throw new moodle_exception('coursehidden', '', new moodle_url('/theme/instracta/catalogue.php'));
}
$title = format_string($course->fullname, true, ['context' => $coursecontext]);
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_instracta/landing', theme_instracta\landing::context($course));
echo $OUTPUT->footer();
