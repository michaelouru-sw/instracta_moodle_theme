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
 * Instracta site analytics dashboard.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\chart_bar;
use core\chart_line;
use core\chart_pie;
use core\chart_series;
use theme_instracta\analytics\site;
use theme_instracta\tokens;

$days = optional_param('days', 30, PARAM_INT);
$categoryid = optional_param('cat', 0, PARAM_INT);
$refresh = optional_param('refresh', 0, PARAM_BOOL);

admin_externalpage_setup('theme_instracta_analytics', '', ['days' => $days, 'cat' => $categoryid],
    '/theme/instracta/analytics/index.php');
require_capability('theme/instracta:viewanalytics', context_system::instance());

if ($refresh) {
    require_sesskey();
    cache::make('theme_instracta', 'analytics')->purge();
    redirect(new moodle_url('/theme/instracta/analytics/index.php', ['days' => $days, 'cat' => $categoryid]));
}

$days = in_array($days, site::RANGES, true) ? $days : 30;
$analytics = new site($days, $categoryid);
$tokens = tokens::resolve($PAGE->theme);

$PAGE->set_title(get_string('analytics_title', 'theme_instracta'));
$PAGE->set_heading(get_string('analytics_title', 'theme_instracta'));

// Headline numbers.
$kpimeta = [
    'activeusers' => 'users',
    'logins' => 'right-to-bracket',
    'newusers' => 'user-plus',
    'enrolments' => 'user-check',
    'completions' => 'award',
    'activecourses' => 'book-open',
];
$kpis = [];
foreach ($analytics->kpis() as $kpi) {
    $delta = $kpi['delta'];
    $kpis[] = [
        'label' => get_string('analytics_kpi_' . $kpi['key'], 'theme_instracta'),
        'icon' => $kpimeta[$kpi['key']],
        'value' => number_format($kpi['value']),
        'hasdelta' => true,
        'deltaclass' => $delta === null ? 'up' : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat')),
        'deltaicon' => $delta === null ? 'arrow-up' : ($delta > 0 ? 'arrow-up' : ($delta < 0 ? 'arrow-down' : 'minus')),
        'deltatext' => $delta === null ? get_string('analytics_new', 'theme_instracta') : (($delta > 0 ? '+' : '') . $delta . '%'),
        'previous' => get_string('analytics_vsprevious', 'theme_instracta', number_format($kpi['previous'])),
    ];
}

// Charts, drawn with Moodle's own chart API.
$series = $analytics->series();

$engagement = new chart_line();
$engagement->set_smooth(true);
$active = new chart_series(get_string('analytics_kpi_activeusers', 'theme_instracta'), $series['active']);
$active->set_color($tokens['primary']);
$logins = new chart_series(get_string('analytics_kpi_logins', 'theme_instracta'), $series['logins']);
$logins->set_color($tokens['accent']);
$engagement->add_series($active);
$engagement->add_series($logins);
$engagement->set_labels($series['labels']);
$engagement->get_yaxis(0, true)->set_min(0);

$flow = new chart_bar();
$flow->set_stacked(false);
foreach ([
    ['signups', 'analytics_kpi_newusers', $tokens['accent']],
    ['enrolments', 'analytics_kpi_enrolments', $tokens['primary']],
    ['completions', 'analytics_kpi_completions', '#16a34a'],
] as [$key, $string, $color]) {
    $item = new chart_series(get_string($string, 'theme_instracta'), $series[$key]);
    $item->set_color($color);
    $flow->add_series($item);
}
$flow->set_labels($series['labels']);
$flow->get_yaxis(0, true)->set_min(0);

$modules = $analytics->module_views();
$modulechart = '';
if ($modules) {
    $pie = new chart_pie();
    $pie->set_doughnut(true);
    $slice = new chart_series(get_string('analytics_col_views', 'theme_instracta'), array_column($modules, 'views'));
    $palette = ['#2563eb', '#06b6d4', '#7c3aed', '#ec4899', '#f59e0b', '#16a34a', '#ef4444', '#64748b'];
    $colors = [];
    foreach (array_keys($modules) as $i => $unused) {
        $colors[] = $palette[$i % count($palette)];
    }
    $slice->set_colors($colors);
    $pie->add_series($slice);
    $pie->set_labels(array_column($modules, 'name'));
    $modulechart = $OUTPUT->render($pie);
}

// Filters.
$ranges = [];
foreach (site::RANGES as $range) {
    $ranges[] = [
        'label' => get_string('analytics_range_' . $range, 'theme_instracta'),
        'url' => (new moodle_url($PAGE->url, ['days' => $range]))->out(false),
        'active' => $range === $days,
    ];
}
$categories = [['id' => 0, 'name' => get_string('analytics_allsite', 'theme_instracta'), 'selected' => $categoryid == 0]];
foreach (core_course_category::make_categories_list('moodle/category:viewcourselist') as $id => $name) {
    $categories[] = ['id' => $id, 'name' => $name, 'selected' => $id == $categoryid];
}

$export = function (string $dataset) use ($days, $categoryid): string {
    return (new moodle_url('/theme/instracta/analytics/export.php', [
        'dataset' => $dataset, 'days' => $days, 'cat' => $categoryid, 'sesskey' => sesskey(),
    ]))->out(false);
};

$totals = $analytics->totals();
$topcourses = $analytics->top_courses(8);
foreach ($topcourses as $i => $row) {
    $topcourses[$i]['userlabel'] = get_string(((int) $row['users']) === 1 ? 'analytics_usercount_one' : 'analytics_usercount', 'theme_instracta', $row['users']);
}
$completion = $analytics->completion_by_course(10);

$context = [
    'ranges' => $ranges,
    'categories' => $categories,
    'formurl' => (new moodle_url('/theme/instracta/analytics/index.php'))->out(false),
    'days' => $days,
    'kpis' => $kpis,
    'engagementchart' => $OUTPUT->render($engagement),
    'flowchart' => $OUTPUT->render($flow),
    'modulechart' => $modulechart,
    'hasmodules' => $modulechart !== '',
    'heat' => $analytics->heatmap(),
    'topcourses' => $topcourses,
    'hastopcourses' => !empty($topcourses),
    'completion' => $completion,
    'hascompletion' => !empty($completion),
    'exportactivity' => $export('activity'),
    'exportcourses' => $export('courses'),
    'exportcompletion' => $export('completion'),
    'exportmodules' => $export('modules'),
    'exportheatmap' => $export('heatmap'),
    'refreshurl' => (new moodle_url('/theme/instracta/analytics/index.php', [
        'days' => $days, 'cat' => $categoryid, 'refresh' => 1, 'sesskey' => sesskey(),
    ]))->out(false),
    'totalsline' => get_string('analytics_totals', 'theme_instracta', [
        'users' => number_format($totals['users']), 'courses' => number_format($totals['courses']),
    ]),
    'logdisabled' => !in_array('logstore_standard', explode(',', (string) get_config('tool_log', 'enabled_stores')), true),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_instracta/analytics/page', $context);
echo $OUTPUT->footer();
