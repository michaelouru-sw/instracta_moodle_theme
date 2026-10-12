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
 * Course catalogue: search, category filter, sorting and a grid or list view.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$query = optional_param('q', '', PARAM_TEXT);
$categoryid = optional_param('cat', 0, PARAM_INT);
$sort = optional_param('sort', 'name', PARAM_ALPHA) === 'newest' ? 'newest' : 'name';
$view = optional_param('view', 'grid', PARAM_ALPHA) === 'list' ? 'list' : 'grid';
$page = max(0, optional_param('page', 0, PARAM_INT));

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/instracta/catalogue.php', ['q' => $query, 'cat' => $categoryid, 'sort' => $sort, 'view' => $view]);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('catalogue', 'theme_instracta'));
$PAGE->set_heading(get_string('catalogue', 'theme_instracta'));
if (!empty($CFG->forcelogin)) {
    require_login();
}

[$data, $total] = theme_instracta\catalogue::page($query, $categoryid, $sort, $view, $page);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_instracta/catalogue', $data);
echo $OUTPUT->paging_bar($total, $page, theme_instracta\catalogue::PERPAGE, $PAGE->url);
echo $OUTPUT->footer();
