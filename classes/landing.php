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

use moodle_url;

/**
 * Data for the course landing page.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landing {

    /**
     * Build the template context for a course.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function context(\stdClass $course): array {
        global $USER;
        $context = \context_course::instance($course->id);
        $listed = new \core_course_list_element($course);
        $cat = \core_course_category::get($course->category, IGNORE_MISSING);
        $enrolled = isloggedin() && !isguestuser() && is_enrolled($context, $USER, '', true);

        // Syllabus: the visible sections and how many activities each holds.
        $modinfo = get_fast_modinfo($course);
        $syllabus = [];
        foreach ($modinfo->get_section_info_all() as $number => $section) {
            if ($number == 0 || !$section->visible) {
                continue;
            }
            $count = 0;
            foreach (($modinfo->sections[$number] ?? []) as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if ($cm->visible && $cm->modname !== 'label') {
                    $count++;
                }
            }
            $syllabus[] = [
                'number' => count($syllabus) + 1,
                'name' => get_section_name($course, $section),
                'count' => $count,
                'countlabel' => get_string('ld_activities', 'theme_instracta', $count),
            ];
        }

        $teachers = [];
        if ($listed->has_course_contacts()) {
            foreach ($listed->get_course_contacts() as $contact) {
                $teachers[$contact['user']->id] = ['name' => $contact['username'], 'role' => $contact['rolename']];
            }
        }

        $learners = count_enrolled_users($context, '', 0, true);
        $enrolurl = new moodle_url('/enrol/index.php', ['id' => $course->id]);
        $summary = format_text($course->summary, $course->summaryformat, ['context' => $context]);
        $price = catalogue::price($course->id);

        return [
            'name' => format_string($course->fullname, true, ['context' => $context]),
            'category' => $cat ? $cat->get_formatted_name() : '',
            'categoryurl' => $cat ? (new moodle_url('/theme/instracta/catalogue.php', ['cat' => $cat->id]))->out(false) : '',
            'image' => catalogue::image($listed),
            'summary' => $summary,
            'hassummary' => trim(strip_tags($summary)) !== '',
            'teachers' => array_values($teachers),
            'hasteachers' => !empty($teachers),
            'learners' => $learners,
            'sections' => count($syllabus),
            'sectionslabel' => get_string(count($syllabus) === 1 ? 'ld_section' : 'ld_sections', 'theme_instracta', count($syllabus)),
            'learnerslabel' => get_string($learners === 1 ? 'ld_learner' : 'ld_learners', 'theme_instracta', $learners),
            'syllabus' => $syllabus,
            'hassyllabus' => !empty($syllabus),
            'price' => $price,
            'free' => $price === '',
            'enrolled' => $enrolled,
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'enrolurl' => $enrolurl->out(false),
            'catalogueurl' => (new moodle_url('/theme/instracta/catalogue.php'))->out(false),
        ];
    }
}
