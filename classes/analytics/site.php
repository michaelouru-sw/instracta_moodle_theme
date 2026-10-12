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

/**
 * Site analytics: engagement, enrolment and completion numbers for a date range.
 *
 * The class is split in two layers. The fetch_* methods run SQL and return plain rows; the public
 * methods turn those rows into figures (deltas, filled time series, heatmap matrix). Results are
 * cached for a few minutes because the log table can be large.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site {

    /** @var int[] Ranges offered in the interface, in days. */
    const RANGES = [7, 30, 90, 365];

    /** @var int Days after which the time series switches to weekly buckets. */
    const WEEKLY_AFTER = 120;

    /** @var string Event name of a successful login. */
    const LOGIN_EVENT = '\core\event\user_loggedin';

    /** @var int Range length in days. */
    protected $days;

    /** @var int Course category filter (0 = whole site). */
    protected $categoryid;

    /** @var int Range end. */
    protected $to;

    /** @var int Range start. */
    protected $from;

    /** @var int Start of the comparison range (the same length, just before). */
    protected $prevfrom;

    /** @var int User timezone offset in seconds. */
    protected $offset;

    /** @var int Days per time series bucket. */
    protected $bucketdays;

    /** @var int Counter for unique SQL parameter names (Moodle forbids reusing a named parameter). */
    protected $paramcount = 0;

    /**
     * Constructor.
     *
     * @param int $days
     * @param int $categoryid
     * @param int|null $now timestamp to treat as the end of the range (for testing)
     */
    public function __construct(int $days = 30, int $categoryid = 0, ?int $now = null) {
        $this->days = in_array($days, self::RANGES, true) ? $days : 30;
        $this->categoryid = max(0, $categoryid);
        $this->to = $now ?? time();
        $this->from = $this->to - ($this->days * DAYSECS);
        $this->prevfrom = $this->from - ($this->days * DAYSECS);
        $this->offset = (new \DateTime('@' . $this->to))
            ->setTimezone(\core_date::get_user_timezone_object())->getOffset();
        $this->bucketdays = $this->days > self::WEEKLY_AFTER ? 7 : 1;
    }

    /**
     * Range length in days.
     *
     * @return int
     */
    public function get_days(): int {
        return $this->days;
    }

    /**
     * Add a value to a parameter list under a fresh name and return the placeholder.
     *
     * @param array $params
     * @param mixed $value
     * @return string
     */
    protected function param(array &$params, $value): string {
        $name = 'lp' . (++$this->paramcount);
        $params[$name] = $value;
        return ':' . $name;
    }

    /**
     * SQL restricting a course id column to the selected category and its sub-categories.
     *
     * @param string $column
     * @param array $params
     * @return string empty when no category is selected
     */
    protected function course_filter(string $column, array &$params): string {
        global $DB;
        if (!$this->categoryid) {
            return '';
        }
        $path = $DB->get_field('course_categories', 'path', ['id' => $this->categoryid]);
        if (!$path) {
            return ' AND 1 = 0';
        }
        $id = $this->param($params, $this->categoryid);
        $like = $this->param($params, $path . '/%');
        return " AND $column IN (SELECT c.id
                                   FROM {course} c
                                   JOIN {course_categories} cc ON cc.id = c.category
                                  WHERE cc.id = $id OR cc.path LIKE $like)";
    }

    /**
     * Cache wrapper: compute once per range, category and dataset.
     *
     * @param string $dataset
     * @param callable $compute
     * @return mixed
     */
    protected function cached(string $dataset, callable $compute) {
        $cache = \cache::make('theme_instracta', 'analytics');
        $key = $dataset . '_' . $this->days . '_' . $this->categoryid . '_' . $this->offset;
        $value = $cache->get($key);
        if ($value === false) {
            $value = $compute();
            $cache->set($key, $value);
        }
        return $value;
    }

    // Fetch layer: SQL only.

    /**
     * Count distinct users with log activity in a window.
     *
     * @param int $from
     * @param int $to
     * @return int
     */
    protected function fetch_active_users(int $from, int $to): int {
        global $DB;
        $params = [];
        $sql = 'SELECT COUNT(DISTINCT l.userid) FROM {logstore_standard_log} l
                 WHERE l.timecreated >= ' . $this->param($params, $from) . '
                   AND l.timecreated < ' . $this->param($params, $to) . '
                   AND l.userid > 0 AND l.userid <> ' . $this->param($params, guest_user()->id)
            . $this->course_filter('l.courseid', $params);
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Count login events in a window.
     *
     * @param int $from
     * @param int $to
     * @return int
     */
    protected function fetch_logins(int $from, int $to): int {
        global $DB;
        $params = [];
        $sql = 'SELECT COUNT(1) FROM {logstore_standard_log} l
                 WHERE l.timecreated >= ' . $this->param($params, $from) . '
                   AND l.timecreated < ' . $this->param($params, $to) . '
                   AND l.eventname = ' . $this->param($params, self::LOGIN_EVENT);
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Count accounts created in a window.
     *
     * @param int $from
     * @param int $to
     * @return int
     */
    protected function fetch_new_users(int $from, int $to): int {
        global $DB;
        return (int) $DB->count_records_select('user', 'deleted = 0 AND timecreated >= ? AND timecreated < ?', [$from, $to]);
    }

    /**
     * Count enrolments created in a window.
     *
     * @param int $from
     * @param int $to
     * @return int
     */
    protected function fetch_enrolments(int $from, int $to): int {
        global $DB;
        $params = [];
        $sql = 'SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.timecreated >= ' . $this->param($params, $from) . '
                   AND ue.timecreated < ' . $this->param($params, $to)
            . $this->course_filter('e.courseid', $params);
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Count course completions in a window.
     *
     * @param int $from
     * @param int $to
     * @return int
     */
    protected function fetch_completions(int $from, int $to): int {
        global $DB;
        $params = [];
        $sql = 'SELECT COUNT(1) FROM {course_completions} cc
                 WHERE cc.timecompleted >= ' . $this->param($params, $from) . '
                   AND cc.timecompleted < ' . $this->param($params, $to)
            . $this->course_filter('cc.course', $params);
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Count distinct courses with activity in a window.
     *
     * @param int $from
     * @param int $to
     * @return int
     */
    protected function fetch_active_courses(int $from, int $to): int {
        global $DB;
        $params = [];
        $sql = 'SELECT COUNT(DISTINCT l.courseid) FROM {logstore_standard_log} l
                 WHERE l.timecreated >= ' . $this->param($params, $from) . '
                   AND l.timecreated < ' . $this->param($params, $to) . '
                   AND l.courseid <> ' . $this->param($params, SITEID)
            . $this->course_filter('l.courseid', $params);
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Bucketed time series from a subquery that exposes the bucket as column b.
     *
     * @param string $inner SELECT returning columns b (bucket number) and optionally u (user id)
     * @param array $params
     * @param bool $distinctusers count distinct u instead of rows
     * @return array bucket number => value
     */
    protected function fetch_buckets(string $inner, array $params, bool $distinctusers = false): array {
        global $DB;
        $count = $distinctusers ? 'COUNT(DISTINCT u)' : 'COUNT(1)';
        $rs = $DB->get_recordset_sql("SELECT b, $count AS c FROM ($inner) x GROUP BY b", $params);
        $out = [];
        foreach ($rs as $row) {
            $out[(int) $row->b] = (int) $row->c;
        }
        $rs->close();
        return $out;
    }

    /**
     * SQL expression turning a timestamp column into a bucket number.
     *
     * @param string $column
     * @param array $params
     * @return string
     */
    protected function bucket_sql(string $column, array &$params): string {
        $off = $this->param($params, $this->offset);
        $size = $this->param($params, 86400 * $this->bucketdays);
        return "FLOOR(($column + $off) / $size)";
    }

    /**
     * Active users per bucket.
     *
     * @return array
     */
    protected function fetch_active_series(): array {
        $params = [];
        $bucket = $this->bucket_sql('l.timecreated', $params);
        $inner = "SELECT $bucket AS b, l.userid AS u FROM {logstore_standard_log} l
                   WHERE l.timecreated >= " . $this->param($params, $this->from) . '
                     AND l.timecreated < ' . $this->param($params, $this->to) . '
                     AND l.userid > 0 AND l.userid <> ' . $this->param($params, guest_user()->id)
            . $this->course_filter('l.courseid', $params);
        return $this->fetch_buckets($inner, $params, true);
    }

    /**
     * Logins per bucket.
     *
     * @return array
     */
    protected function fetch_login_series(): array {
        $params = [];
        $bucket = $this->bucket_sql('l.timecreated', $params);
        $inner = "SELECT $bucket AS b FROM {logstore_standard_log} l
                   WHERE l.timecreated >= " . $this->param($params, $this->from) . '
                     AND l.timecreated < ' . $this->param($params, $this->to) . '
                     AND l.eventname = ' . $this->param($params, self::LOGIN_EVENT);
        return $this->fetch_buckets($inner, $params);
    }

    /**
     * Enrolments per bucket.
     *
     * @return array
     */
    protected function fetch_enrolment_series(): array {
        $params = [];
        $bucket = $this->bucket_sql('ue.timecreated', $params);
        $inner = "SELECT $bucket AS b FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                   WHERE ue.timecreated >= " . $this->param($params, $this->from) . '
                     AND ue.timecreated < ' . $this->param($params, $this->to)
            . $this->course_filter('e.courseid', $params);
        return $this->fetch_buckets($inner, $params);
    }

    /**
     * Completions per bucket.
     *
     * @return array
     */
    protected function fetch_completion_series(): array {
        $params = [];
        $bucket = $this->bucket_sql('cc.timecompleted', $params);
        $inner = "SELECT $bucket AS b FROM {course_completions} cc
                   WHERE cc.timecompleted >= " . $this->param($params, $this->from) . '
                     AND cc.timecompleted < ' . $this->param($params, $this->to)
            . $this->course_filter('cc.course', $params);
        return $this->fetch_buckets($inner, $params);
    }

    /**
     * New accounts per bucket.
     *
     * @return array
     */
    protected function fetch_signup_series(): array {
        $params = [];
        $bucket = $this->bucket_sql('u.timecreated', $params);
        $inner = "SELECT $bucket AS b FROM {user} u
                   WHERE u.deleted = 0 AND u.timecreated >= " . $this->param($params, $this->from) . '
                     AND u.timecreated < ' . $this->param($params, $this->to);
        return $this->fetch_buckets($inner, $params);
    }

    /**
     * Event counts per (day, hour) for the heatmap.
     *
     * @return array of [day number, hour, count]
     */
    protected function fetch_hourly(): array {
        global $DB;
        $params = [];
        $off = $this->param($params, $this->offset);
        $off2 = $this->param($params, $this->offset);
        $off3 = $this->param($params, $this->offset);
        $inner = "SELECT FLOOR((l.timecreated + $off) / 86400) AS d,
                         FLOOR(((l.timecreated + $off2) - FLOOR((l.timecreated + $off3) / 86400) * 86400) / 3600) AS h
                    FROM {logstore_standard_log} l
                   WHERE l.timecreated >= " . $this->param($params, $this->from) . '
                     AND l.timecreated < ' . $this->param($params, $this->to) . '
                     AND l.userid > 0 AND l.userid <> ' . $this->param($params, guest_user()->id)
            . $this->course_filter('l.courseid', $params);
        $rs = $DB->get_recordset_sql("SELECT d, h, COUNT(1) AS c FROM ($inner) x GROUP BY d, h", $params);
        $out = [];
        foreach ($rs as $row) {
            $out[] = [(int) $row->d, (int) $row->h, (int) $row->c];
        }
        $rs->close();
        return $out;
    }

    /**
     * Most active courses: distinct users and events in the range.
     *
     * @param int $limit
     * @return array of objects: courseid, users, events
     */
    protected function fetch_top_courses(int $limit): array {
        global $DB;
        $params = [];
        $sql = 'SELECT l.courseid, COUNT(DISTINCT l.userid) AS users, COUNT(1) AS events
                  FROM {logstore_standard_log} l
                 WHERE l.timecreated >= ' . $this->param($params, $this->from) . '
                   AND l.timecreated < ' . $this->param($params, $this->to) . '
                   AND l.courseid <> ' . $this->param($params, SITEID) . '
                   AND l.userid > 0 AND l.userid <> ' . $this->param($params, guest_user()->id)
            . $this->course_filter('l.courseid', $params) . '
              GROUP BY l.courseid
              ORDER BY users DESC, events DESC';
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Course module views by activity type.
     *
     * @return array of objects: component, views
     */
    protected function fetch_module_views(): array {
        global $DB;
        $params = [];
        $like = $DB->sql_like('l.eventname', $this->param($params, '%course_module_viewed'));
        $sql = "SELECT l.component, COUNT(1) AS views
                  FROM {logstore_standard_log} l
                 WHERE l.timecreated >= " . $this->param($params, $this->from) . '
                   AND l.timecreated < ' . $this->param($params, $this->to) . "
                   AND $like"
            . $this->course_filter('l.courseid', $params) . '
              GROUP BY l.component
              ORDER BY views DESC';
        return array_values($DB->get_records_sql($sql, $params, 0, 8));
    }

    /**
     * Enrolled and completed counts for the largest courses with completion tracking.
     *
     * @param int $limit
     * @return array of objects: id, fullname, enrolled, completed
     */
    protected function fetch_completion_by_course(int $limit): array {
        global $DB;
        $params = [];
        $sql = 'SELECT c.id, c.fullname,
                       (SELECT COUNT(DISTINCT ue.userid)
                          FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE e.courseid = c.id AND ue.status = 0) AS enrolled,
                       (SELECT COUNT(1) FROM {course_completions} cc
                         WHERE cc.course = c.id AND cc.timecompleted IS NOT NULL) AS completed
                  FROM {course} c
                 WHERE c.id <> ' . $this->param($params, SITEID) . ' AND c.visible = 1 AND c.enablecompletion = 1'
            . $this->course_filter('c.id', $params) . '
              ORDER BY enrolled DESC, c.id';
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    // Processing layer: pure PHP.

    /**
     * Percentage change from a previous value, or null when there is nothing to compare against.
     *
     * @param int $current
     * @param int $previous
     * @return float|null
     */
    public static function delta(int $current, int $previous): ?float {
        if ($previous <= 0) {
            return $current > 0 ? null : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Headline numbers with comparison against the previous period.
     *
     * @return array of ['key', 'value', 'previous', 'delta']
     */
    public function kpis(): array {
        return $this->cached('kpis', function () {
            $metrics = [
                'activeusers' => [$this->fetch_active_users($this->from, $this->to),
                    $this->fetch_active_users($this->prevfrom, $this->from)],
                'logins' => [$this->fetch_logins($this->from, $this->to), $this->fetch_logins($this->prevfrom, $this->from)],
                'newusers' => [$this->fetch_new_users($this->from, $this->to), $this->fetch_new_users($this->prevfrom, $this->from)],
                'enrolments' => [$this->fetch_enrolments($this->from, $this->to),
                    $this->fetch_enrolments($this->prevfrom, $this->from)],
                'completions' => [$this->fetch_completions($this->from, $this->to),
                    $this->fetch_completions($this->prevfrom, $this->from)],
                'activecourses' => [$this->fetch_active_courses($this->from, $this->to),
                    $this->fetch_active_courses($this->prevfrom, $this->from)],
            ];
            $out = [];
            foreach ($metrics as $key => [$value, $previous]) {
                $out[] = ['key' => $key, 'value' => $value, 'previous' => $previous, 'delta' => self::delta($value, $previous)];
            }
            return $out;
        });
    }

    /**
     * Bucket numbers covering the range, oldest first.
     *
     * @return int[]
     */
    protected function bucket_range(): array {
        $size = 86400 * $this->bucketdays;
        $first = (int) floor(($this->from + $this->offset) / $size);
        $last = (int) floor(($this->to + $this->offset - 1) / $size);
        return range($first, $last);
    }

    /**
     * Turn a sparse bucket => value map into a dense list in range order.
     *
     * @param array $sparse
     * @param int[] $buckets
     * @return int[]
     */
    public static function fill(array $sparse, array $buckets): array {
        $out = [];
        foreach ($buckets as $bucket) {
            $out[] = $sparse[$bucket] ?? 0;
        }
        return $out;
    }

    /**
     * Engagement over time.
     *
     * @return array ['labels' => string[], 'active' => int[], 'logins' => int[], 'enrolments' => int[],
     *                'completions' => int[], 'signups' => int[]]
     */
    public function series(): array {
        return $this->cached('series', function () {
            $buckets = $this->bucket_range();
            $labels = [];
            foreach ($buckets as $bucket) {
                // Noon of the bucket's first day, so the label survives timezone rounding.
                $labels[] = userdate(($bucket * 86400 * $this->bucketdays) - $this->offset + 43200,
                    get_string('strftimedateshort', 'langconfig'));
            }
            return [
                'labels' => $labels,
                'active' => self::fill($this->fetch_active_series(), $buckets),
                'logins' => self::fill($this->fetch_login_series(), $buckets),
                'enrolments' => self::fill($this->fetch_enrolment_series(), $buckets),
                'completions' => self::fill($this->fetch_completion_series(), $buckets),
                'signups' => self::fill($this->fetch_signup_series(), $buckets),
            ];
        });
    }

    /**
     * Fold (day, hour, count) rows into a weekday x hour matrix.
     *
     * @param array $rows [day number, hour, count]
     * @return array ['matrix' => [weekday 0=Monday..6 => [hour => count]], 'max' => int]
     */
    public static function fold_heatmap(array $rows): array {
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));
        $max = 0;
        foreach ($rows as [$day, $hour, $count]) {
            // Day 0 (1 January 1970) was a Thursday: with Monday = 0, that is weekday 3.
            $weekday = (($day + 3) % 7 + 7) % 7;
            if ($hour < 0 || $hour > 23) {
                continue;
            }
            $matrix[$weekday][$hour] += $count;
            $max = max($max, $matrix[$weekday][$hour]);
        }
        return ['matrix' => $matrix, 'max' => $max];
    }

    /**
     * Activity heatmap, ready for the template: rows of cells with an intensity between 0 and 1.
     *
     * @return array
     */
    public function heatmap(): array {
        return $this->cached('heatmap', function () {
            $folded = self::fold_heatmap($this->fetch_hourly());
            $names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $rows = [];
            foreach ($folded['matrix'] as $weekday => $hours) {
                $cells = [];
                foreach ($hours as $hour => $count) {
                    $intensity = $folded['max'] > 0 ? round($count / $folded['max'], 3) : 0;
                    $cells[] = [
                        'v' => $intensity,
                        'title' => get_string('analytics_heat_cell', 'theme_instracta', [
                            'count' => number_format($count),
                            'hour' => sprintf('%02d:00', $hour),
                        ]),
                    ];
                }
                $rows[] = [
                    'label' => \core_text::substr(get_string($names[$weekday], 'calendar'), 0, 3),
                    'cells' => $cells,
                ];
            }
            $hourlabels = [];
            for ($h = 0; $h < 24; $h++) {
                $hourlabels[] = ['label' => $h % 3 === 0 ? sprintf('%02d', $h) : ''];
            }
            return ['rows' => $rows, 'hours' => $hourlabels, 'max' => $folded['max'], 'hasdata' => $folded['max'] > 0];
        });
    }

    /**
     * Most active courses with a bar width relative to the leader.
     *
     * @param int $limit
     * @return array
     */
    public function top_courses(int $limit = 8): array {
        return $this->cached('topcourses', function () use ($limit) {
            global $DB;
            $rows = $this->fetch_top_courses($limit);
            if (!$rows) {
                return [];
            }
            $names = $DB->get_records_list('course', 'id', array_column($rows, 'courseid'), '', 'id, fullname');
            $max = max(1, (int) $rows[0]->users);
            $out = [];
            foreach ($rows as $row) {
                if (!isset($names[$row->courseid])) {
                    continue;
                }
                $out[] = [
                    'id' => (int) $row->courseid,
                    'name' => format_string($names[$row->courseid]->fullname, true,
                        ['context' => \context_course::instance($row->courseid)]),
                    'url' => (new \moodle_url('/course/view.php', ['id' => $row->courseid]))->out(false),
                    'users' => (int) $row->users,
                    'events' => (int) $row->events,
                    'width' => round(((int) $row->users / $max) * 100, 1),
                ];
            }
            return $out;
        });
    }

    /**
     * Course module views by activity type, with readable names.
     *
     * @return array of ['name', 'views']
     */
    public function module_views(): array {
        return $this->cached('modules', function () {
            $out = [];
            foreach ($this->fetch_module_views() as $row) {
                $component = (string) $row->component;
                $name = $component;
                if (get_string_manager()->string_exists('pluginname', $component)) {
                    $name = get_string('pluginname', $component);
                }
                $out[] = ['name' => $name, 'views' => (int) $row->views];
            }
            return $out;
        });
    }

    /**
     * Completion rate for the largest courses that track completion.
     *
     * @param int $limit
     * @return array
     */
    public function completion_by_course(int $limit = 10): array {
        return $this->cached('completion', function () use ($limit) {
            $out = [];
            foreach ($this->fetch_completion_by_course($limit) as $row) {
                $enrolled = (int) $row->enrolled;
                $completed = (int) $row->completed;
                $out[] = [
                    'id' => (int) $row->id,
                    'name' => format_string($row->fullname, true, ['context' => \context_course::instance($row->id)]),
                    'url' => (new \moodle_url('/course/view.php', ['id' => $row->id]))->out(false),
                    'enrolled' => $enrolled,
                    'completed' => $completed,
                    'rate' => $enrolled > 0 ? round(min(100, ($completed / $enrolled) * 100), 1) : 0,
                ];
            }
            return $out;
        });
    }

    /**
     * Site totals shown as context beneath the headline numbers.
     *
     * @return array
     */
    public function totals(): array {
        global $DB;
        return [
            'users' => (int) $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND confirmed = 1 AND id <> ?',
                [guest_user()->id]),
            'courses' => (int) $DB->count_records_select('course', 'id <> ?', [SITEID]),
        ];
    }

    /**
     * Rows for a CSV export.
     *
     * @param string $dataset activity, courses, completion, modules or heatmap
     * @return array|null [header row, data rows] or null for an unknown dataset
     */
    public function export_rows(string $dataset): ?array {
        switch ($dataset) {
            case 'activity':
                $s = $this->series();
                $rows = [];
                foreach ($s['labels'] as $i => $label) {
                    $rows[] = [$label, $s['active'][$i], $s['logins'][$i], $s['signups'][$i], $s['enrolments'][$i],
                        $s['completions'][$i]];
                }
                return [[get_string('analytics_col_period', 'theme_instracta'), get_string('analytics_kpi_activeusers', 'theme_instracta'),
                    get_string('analytics_kpi_logins', 'theme_instracta'), get_string('analytics_kpi_newusers', 'theme_instracta'),
                    get_string('analytics_kpi_enrolments', 'theme_instracta'), get_string('analytics_kpi_completions', 'theme_instracta')],
                    $rows];
            case 'courses':
                $rows = [];
                foreach ($this->top_courses(50) as $c) {
                    $rows[] = [$c['name'], $c['users'], $c['events']];
                }
                return [[get_string('course'), get_string('analytics_col_users', 'theme_instracta'),
                    get_string('analytics_col_events', 'theme_instracta')], $rows];
            case 'completion':
                $rows = [];
                foreach ($this->completion_by_course(50) as $c) {
                    $rows[] = [$c['name'], $c['enrolled'], $c['completed'], $c['rate'] . '%'];
                }
                return [[get_string('course'), get_string('analytics_col_enrolled', 'theme_instracta'),
                    get_string('analytics_col_completed', 'theme_instracta'), get_string('analytics_col_rate', 'theme_instracta')], $rows];
            case 'modules':
                $rows = [];
                foreach ($this->module_views() as $m) {
                    $rows[] = [$m['name'], $m['views']];
                }
                return [[get_string('analytics_col_activitytype', 'theme_instracta'),
                    get_string('analytics_col_views', 'theme_instracta')], $rows];
            case 'heatmap':
                $heat = $this->heatmap();
                $header = [get_string('analytics_col_weekday', 'theme_instracta')];
                for ($h = 0; $h < 24; $h++) {
                    $header[] = sprintf('%02d:00', $h);
                }
                $folded = self::fold_heatmap($this->fetch_hourly());
                $rows = [];
                foreach ($heat['rows'] as $weekday => $row) {
                    $rows[] = array_merge([$row['label']], $folded['matrix'][$weekday]);
                }
                return [$header, $rows];
        }
        return null;
    }
}
