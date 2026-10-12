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
 * Data for the course catalogue and the course landing page.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue {

    /** @var int Courses per page. */
    const PERPAGE = 12;

    /**
     * Image URL for a course: its cover image, or a generated pattern.
     *
     * @param \core_course_list_element $course
     * @return string
     */
    public static function image(\core_course_list_element $course): string {
        global $OUTPUT;
        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                    null, $file->get_filepath(), $file->get_filename())->out(false);
            }
        }
        return $OUTPUT->get_generated_image_for_id($course->id);
    }

    /**
     * Teacher names of a course.
     *
     * @param \core_course_list_element $course
     * @return string
     */
    public static function teachers(\core_course_list_element $course): string {
        $names = [];
        if ($course->has_course_contacts()) {
            foreach ($course->get_course_contacts() as $contact) {
                $names[] = $contact['username'];
            }
        }
        return implode(', ', array_unique($names));
    }

    /**
     * The price of a course when it is sold through the Course payment enrolment method.
     *
     * @param int $courseid
     * @return string empty when free or not sold
     */
    public static function price(int $courseid): string {
        global $DB;
        $fee = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED],
            'id, cost, currency', IGNORE_MULTIPLE);
        if (!$fee || (float) $fee->cost <= 0) {
            return '';
        }
        return $fee->currency . ' ' . format_float((float) $fee->cost, 2, true, true);
    }

    /**
     * Build the catalogue page context.
     *
     * @param string $query search text
     * @param int $categoryid 0 for all
     * @param string $sort name or newest
     * @param string $view grid or list
     * @param int $page zero based page
     * @return array [context for the template, total number of matches]
     */
    public static function page(string $query, int $categoryid, string $sort, string $view, int $page): array {
        global $USER;
        $category = $categoryid ? \core_course_category::get($categoryid, IGNORE_MISSING) : null;
        $root = $category ?: \core_course_category::top();
        $courses = $root->get_courses(['recursive' => true, 'sort' => $sort === 'newest' ? ['timecreated' => -1] : ['fullname' => 1]]);

        $query = trim($query);
        $matches = [];
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            if ($query !== '') {
                $haystack = \core_text::strtolower($course->fullname . ' ' . $course->shortname . ' ' . strip_tags((string) $course->summary));
                if (strpos($haystack, \core_text::strtolower($query)) === false) {
                    continue;
                }
            }
            $matches[] = $course;
        }
        $total = count($matches);
        $slice = array_slice($matches, $page * self::PERPAGE, self::PERPAGE);

        $cards = [];
        foreach ($slice as $course) {
            $context = \context_course::instance($course->id);
            $cat = \core_course_category::get($course->category, IGNORE_MISSING);
            $enrolled = isloggedin() && !isguestuser() && is_enrolled($context, $USER, '', true);
            $cards[] = [
                'id' => $course->id,
                'name' => format_string($course->fullname, true, ['context' => $context]),
                'category' => $cat ? $cat->get_formatted_name() : '',
                'summary' => shorten_text(trim(strip_tags(format_text($course->summary, $course->summaryformat,
                    ['context' => $context]))), 150),
                'teachers' => self::teachers($course),
                'image' => self::image($course),
                'url' => (new moodle_url('/theme/instracta/course.php', ['id' => $course->id]))->out(false),
                'learners' => count_enrolled_users($context, '', 0, true),
                'price' => self::price($course->id),
                'enrolled' => $enrolled,
            ];
        }

        $options = [['id' => 0, 'name' => get_string('cat_all', 'theme_instracta'), 'selected' => !$categoryid]];
        foreach (\core_course_category::make_categories_list() as $id => $name) {
            $options[] = ['id' => $id, 'name' => $name, 'selected' => $id == $categoryid];
        }

        return [[
            'action' => (new moodle_url('/theme/instracta/catalogue.php'))->out(false),
            'query' => $query,
            'categories' => $options,
            'sortname' => $sort !== 'newest',
            'sortnewest' => $sort === 'newest',
            'view' => $view,
            'urlgrid' => (new moodle_url('/theme/instracta/catalogue.php', ['q' => $query, 'cat' => $categoryid, 'sort' => $sort,
                'view' => 'grid']))->out(false),
            'urllist' => (new moodle_url('/theme/instracta/catalogue.php', ['q' => $query, 'cat' => $categoryid, 'sort' => $sort,
                'view' => 'list']))->out(false),
            'isgrid' => $view !== 'list',
            'islist' => $view === 'list',
            'cards' => $cards,
            'hascards' => !empty($cards),
            'count' => get_string('cat_count', 'theme_instracta', $total),
        ], $total];
    }
}
