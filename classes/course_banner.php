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
 * Builds the data for the course header banner shown on the main page of a course.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_banner {

    /**
     * Template context for a course, or null when no banner should be shown.
     *
     * @param \stdClass $course
     * @return array|null
     */
    public static function context(\stdClass $course): ?array {
        global $OUTPUT, $USER, $CFG;

        if ((int) $course->id === SITEID) {
            return null;
        }
        $context = \context_course::instance($course->id);
        $element = new \core_course_list_element($course);

        $image = \core_course\external\course_summary_exporter::get_course_image($course);
        if (!$image) {
            $image = $OUTPUT->get_generated_image_for_id((int) $course->id);
        }

        $summary = trim(preg_replace('/\s+/', ' ', strip_tags(format_text($course->summary, $course->summaryformat,
            ['context' => $context, 'noclean' => false]))));
        $summary = \core_text::strlen($summary) > 230 ? \core_text::substr($summary, 0, 227) . '…' : $summary;

        $teachers = [];
        foreach ($element->get_course_contacts() as $contact) {
            $teachers[] = $contact['username'] ?? '';
        }
        $teachers = array_slice(array_filter($teachers), 0, 3);

        $category = '';
        $categoryurl = '';
        if (!empty($course->category)) {
            $cat = \core_course_category::get($course->category, IGNORE_MISSING);
            if ($cat) {
                $category = $cat->get_formatted_name();
                $categoryurl = (new \moodle_url('/course/index.php', ['categoryid' => $cat->id]))->out(false);
            }
        }

        $format = course_get_format($course);
        $sections = max(0, (int) $format->get_last_section_number());

        $progress = null;
        $actionurl = '';
        $isenrolled = isloggedin() && !isguestuser() && is_enrolled($context, $USER, '', true);
        if ($isenrolled) {
            $progress = \core_completion\progress::get_course_progress_percentage($course);
            $actionurl = self::next_activity_url($course);
        }

        return [
            'name' => format_string($course->fullname, true, ['context' => $context]),
            'category' => $category,
            'categoryurl' => $categoryurl,
            'hascategory' => $category !== '',
            'summary' => $summary,
            'hassummary' => $summary !== '',
            'image' => $image,
            'teachers' => implode(', ', $teachers),
            'hasteachers' => !empty($teachers),
            'learners' => self::count_label('cb_learner', (int) count_enrolled_users($context, '', 0, true)),
            'sections' => $sections > 0 ? self::count_label('cb_section', $sections) : '',
            'hasprogress' => $progress !== null,
            'progress' => $progress === null ? 0 : (int) round($progress),
            'progresslabel' => $progress === null ? '' : get_string('cb_complete', 'theme_instracta', (int) round($progress)),
            'hasaction' => $actionurl !== '',
            'actionurl' => $actionurl,
            'actionlabel' => $progress ? get_string('cb_continue', 'theme_instracta') : get_string('cb_start', 'theme_instracta'),
        ];
    }

    /**
     * The first activity the learner has not finished (or the first one, for a new learner).
     *
     * @param \stdClass $course
     * @return string URL, or an empty string
     */
    protected static function next_activity_url(\stdClass $course): string {
        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);
        $first = '';
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !$cm->url || $cm->modname === 'label') {
                continue;
            }
            if ($first === '') {
                $first = $cm->url->out(false);
            }
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
                continue;
            }
            $state = $completion->get_data($cm, false)->completionstate;
            if ($state == COMPLETION_INCOMPLETE) {
                return $cm->url->out(false);
            }
        }
        return $first;
    }

    /**
     * A count with a singular or plural noun.
     *
     * @param string $key language string key without the s suffix (cb_learner, cb_section)
     * @param int $count
     * @return string
     */
    protected static function count_label(string $key, int $count): string {
        return get_string($count === 1 ? $key : $key . 's', 'theme_instracta', $count);
    }
}
