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

namespace theme_instracta\analytics;

use theme_instracta\features;

/**
 * Data for the learner insights strip on the dashboard: progress, completions, streak and resume.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner {

    /** @var int Days of activity drawn in the sparkline. */
    const SPARK_DAYS = 14;

    /** @var int Days of history scanned when working out the streak. */
    const STREAK_DAYS = 60;

    /**
     * Template context for the strip, or null if nothing useful can be shown.
     *
     * @param int $userid
     * @return array|null
     */
    public static function strip_context(int $userid): ?array {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $user = \core_user::get_user($userid);
        if (!$user) {
            return null;
        }

        $courses = enrol_get_my_courses('id, fullname, summary, summaryformat, category, visible, enablecompletion',
            'fullname ASC', 24);

        $progress = [];
        $completed = 0;
        $inprogress = 0;
        foreach ($courses as $course) {
            $value = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $progress[$course->id] = $value;
            if ($value !== null) {
                if ($value >= 100) {
                    $completed++;
                } else {
                    $inprogress++;
                }
            }
        }
        $tracked = array_filter($progress, function ($v) {
            return $v !== null;
        });
        $average = $tracked ? (int) round(array_sum($tracked) / count($tracked)) : null;

        $activity = self::daily_activity($userid);
        $streak = self::streak($activity['days']);

        $stats = [
            ['value' => count($courses), 'label' => get_string('learner_courses', 'theme_instracta')],
            ['value' => $average === null ? '&ndash;' : $average . '%', 'label' => get_string('learner_avgprogress', 'theme_instracta')],
            ['value' => $completed, 'label' => get_string('learner_completed', 'theme_instracta')],
            ['value' => $streak, 'label' => get_string('learner_streak', 'theme_instracta')],
        ];

        $hour = (int) usergetdate(time())['hours'];
        $key = $hour < 12 ? 'learner_greeting_morning' : ($hour < 18 ? 'learner_greeting_afternoon' : 'learner_greeting_evening');
        $subline = $inprogress > 0
            ? get_string('learner_subline_progress', 'theme_instracta', $inprogress)
            : get_string('learner_subline_none', 'theme_instracta');

        $resume = self::resume_course($userid, $courses, $progress);

        // The week at a glance: seven bars, oldest first, with the weekday letter.
        $series = array_slice($activity['series'], -7);
        $max = max(1, max($series));
        $week = [];
        foreach ($series as $i => $count) {
            $ts = time() - (6 - $i) * DAYSECS;
            $week[] = [
                'day' => userdate($ts, '%a'),
                'height' => $count > 0 ? max(12, (int) round($count / $max * 100)) : 6,
                'active' => $count > 0,
                'today' => $i === 6,
            ];
        }
        $activedays = count(array_filter($series));

        return [
            'greeting' => get_string($key, 'theme_instracta', $user->firstname),
            'date' => userdate(time(), get_string('strftimedaydate', 'langconfig')),
            'subline' => $subline,
            'stats' => $stats,
            'hasresume' => $resume !== null,
            'resume' => $resume,
            'week' => $week,
            'weeksummary' => get_string('learner_weeksummary', 'theme_instracta', $activedays),
            'hasweek' => array_sum($series) > 0,
            'courses' => self::course_cards($userid, $courses, $progress, $resume['id'] ?? 0),
            'upcoming' => self::upcoming($userid),
            'links' => self::quick_links(),
            'browseurl' => (new \moodle_url('/course/index.php'))->out(false),
        ];
    }

    /**
     * Quick links under the greeting.
     *
     * @return array
     */
    protected static function quick_links(): array {
        $browse = features::on('catalogue', true) ? '/theme/instracta/catalogue.php' : '/course/index.php';
        $links = [
            ['icon' => 'compass', 'url' => $browse, 'label' => get_string('learner_ql_browse', 'theme_instracta')],
            ['icon' => 'calendar-days', 'url' => '/calendar/view.php?view=month', 'label' => get_string('learner_ql_calendar', 'theme_instracta')],
            ['icon' => 'chart-simple', 'url' => '/grade/report/overview/index.php', 'label' => get_string('learner_ql_grades', 'theme_instracta')],
            ['icon' => 'comments', 'url' => '/message/index.php', 'label' => get_string('learner_ql_messages', 'theme_instracta')],
        ];
        foreach ($links as &$link) {
            $link['url'] = (new \moodle_url($link['url']))->out(false);
        }
        return $links;
    }

    /**
     * Cards for the courses in progress, most recently visited first.
     *
     * @param int $userid
     * @param array $courses
     * @param array $progress
     * @param int $skip course already shown as the featured one
     * @return array
     */
    protected static function course_cards(int $userid, array $courses, array $progress, int $skip): array {
        global $DB;
        $recent = $DB->get_records('user_lastaccess', ['userid' => $userid], 'timeaccess DESC', 'courseid, timeaccess', 0, 50);
        $order = [];
        foreach ($recent as $access) {
            if (isset($courses[$access->courseid])) {
                $order[$access->courseid] = (int) $access->timeaccess;
            }
        }
        $cards = [];
        $ids = array_merge(array_keys($order), array_diff(array_keys($courses), array_keys($order)));
        foreach ($ids as $id) {
            if ($id == $skip || count($cards) >= 6) {
                continue;
            }
            $course = $courses[$id];
            $value = $progress[$id] ?? null;
            if ($value !== null && $value >= 100) {
                continue;
            }
            $image = \core_course\external\course_summary_exporter::get_course_image($course);
            $cards[] = [
                'name' => format_string($course->fullname, true, ['context' => \context_course::instance($id)]),
                'url' => (new \moodle_url('/course/view.php', ['id' => $id]))->out(false),
                'image' => $image ?: '',
                'hasimage' => !empty($image),
                'progress' => $value === null ? 0 : (int) round($value),
                'hasprogress' => $value !== null,
                'seen' => isset($order[$id]) ? get_string('learner_lastseen', 'theme_instracta', userdate($order[$id], get_string('strftimedatefullshort', 'langconfig'))) : get_string('learner_notstarted', 'theme_instracta'),
            ];
        }
        return $cards;
    }

    /**
     * The next things due: assignments, quizzes and other action events.
     *
     * @param int $userid
     * @return array
     */
    protected static function upcoming(int $userid): array {
        $out = [];
        try {
            global $CFG;
            require_once($CFG->dirroot . '/calendar/lib.php');
            $user = \core_user::get_user($userid);
            $events = \core_calendar\local\api::get_action_events_by_timesort(time() - HOURSECS, null, null, 5, true, $user);
            foreach ($events as $event) {
                $start = $event->get_times()->get_sort_time();
                $course = $event->get_course();
                $action = $event->get_action();
                $days = (int) floor(($start->getTimestamp() - time()) / DAYSECS);
                $out[] = [
                    'name' => format_string($event->get_name()),
                    'course' => $course ? format_string($course->get('fullname')) : '',
                    'when' => userdate($start->getTimestamp(), get_string('strftimedatetimeshort', 'langconfig')),
                    'url' => $action ? $action->get_url()->out(false) : '',
                    'hasurl' => (bool) $action,
                    'soon' => $days <= 2,
                    'month' => userdate($start->getTimestamp(), '%b'),
                    'day' => userdate($start->getTimestamp(), '%d'),
                ];
            }
        } catch (\Throwable $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        return $out;
    }

    /**
     * Event counts per day for a user.
     *
     * @param int $userid
     * @return array ['days' => [daynumber => count], 'series' => [count per day, oldest first, SPARK_DAYS long]]
     */
    protected static function daily_activity(int $userid): array {
        global $DB;
        $offset = (new \DateTime('now', \core_date::get_user_timezone_object()))->getOffset();
        $today = (int) floor((time() + $offset) / DAYSECS);
        $days = [];

        if ($DB->get_manager()->table_exists('logstore_standard_log')) {
            $sql = "SELECT d, COUNT(1) AS c
                      FROM (SELECT FLOOR((timecreated + :off) / 86400) AS d
                              FROM {logstore_standard_log}
                             WHERE userid = :userid AND timecreated >= :since) x
                  GROUP BY d";
            $rs = $DB->get_recordset_sql($sql, [
                'off' => $offset,
                'userid' => $userid,
                'since' => time() - (self::STREAK_DAYS * DAYSECS),
            ]);
            foreach ($rs as $row) {
                $days[(int) $row->d] = (int) $row->c;
            }
            $rs->close();
        }

        $series = [];
        for ($i = self::SPARK_DAYS - 1; $i >= 0; $i--) {
            $series[] = $days[$today - $i] ?? 0;
        }
        return ['days' => $days, 'series' => $series, 'today' => $today];
    }

    /**
     * Consecutive days of activity, counted back from today (or from yesterday if today is still empty).
     *
     * @param array $days daynumber => count
     * @return int
     */
    public static function streak(array $days): int {
        if (!$days) {
            return 0;
        }
        $offset = (new \DateTime('now', \core_date::get_user_timezone_object()))->getOffset();
        $cursor = (int) floor((time() + $offset) / DAYSECS);
        if (empty($days[$cursor])) {
            $cursor--;
        }
        $streak = 0;
        while (!empty($days[$cursor])) {
            $streak++;
            $cursor--;
        }
        return $streak;
    }

    /**
     * Most recently visited enrolled course, for the "continue learning" card.
     *
     * @param int $userid
     * @param array $courses enrolled courses by id
     * @param array $progress progress percentage by course id
     * @return array|null
     */
    protected static function resume_course(int $userid, array $courses, array $progress): ?array {
        global $DB;
        if (!$courses) {
            return null;
        }
        $course = null;
        $recent = $DB->get_records('user_lastaccess', ['userid' => $userid], 'timeaccess DESC', 'courseid, timeaccess', 0, 10);
        foreach ($recent as $access) {
            if (isset($courses[$access->courseid])) {
                $course = $courses[$access->courseid];
                break;
            }
        }
        // Never visited anything yet: offer the first unfinished course.
        if (!$course) {
            foreach ($courses as $candidate) {
                if (($progress[$candidate->id] ?? 0) < 100) {
                    $course = $candidate;
                    break;
                }
            }
            $course = $course ?? reset($courses);
        }

        $image = \core_course\external\course_summary_exporter::get_course_image($course);
        $value = $progress[$course->id] ?? null;
        return [
            'id' => (int) $course->id,
            'name' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'image' => $image ?: '',
            'hasimage' => !empty($image),
            'progress' => $value === null ? 0 : (int) round($value),
            'hasprogress' => $value !== null,
        ];
    }

    /**
     * A small inline SVG line chart.
     *
     * @param int[] $values
     * @return string
     */
    public static function sparkline(array $values): string {
        $count = count($values);
        if ($count < 2) {
            return '';
        }
        $width = 300;
        $height = 52;
        $pad = 4;
        $max = max(1, max($values));
        $points = [];
        foreach ($values as $i => $v) {
            $x = round($i * ($width / ($count - 1)), 1);
            $y = round($height - $pad - ($v / $max) * ($height - 2 * $pad), 1);
            $points[] = [$x, $y];
        }
        $line = 'M' . implode(' L', array_map(function ($p) {
            return $p[0] . ' ' . $p[1];
        }, $points));
        $area = $line . ' L' . $width . ' ' . $height . ' L0 ' . $height . ' Z';

        return '<svg class="instracta-spark" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" ' .
            'role="img" aria-label="' . s(get_string('learner_activity14', 'theme_instracta')) . '">' .
            '<path class="area" d="' . $area . '"/><path class="line" d="' . $line . '" vector-effect="non-scaling-stroke"/></svg>';
    }
}
