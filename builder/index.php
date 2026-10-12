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
 * Home page builder: full-screen visual editor.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use theme_instracta\builder\manager;
use theme_instracta\builder\registry;

admin_externalpage_setup('theme_instracta_builder', '', null, '/theme/instracta/builder/index.php');
require_capability('theme/instracta:managehome', context_system::instance());

// The editor uses the whole window, so it renders on the bare "embedded" layout.
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('b_title', 'theme_instracta'));
$PAGE->add_body_class('instracta-builder-page');
$PAGE->requires->css('/theme/instracta/style/builder.css');

manager::ensure_seeded();

$categories = [['id' => 0, 'name' => get_string('b_toplevel', 'theme_instracta')]];
foreach (core_course_category::make_categories_list('moodle/category:viewcourselist') as $id => $name) {
    $categories[] = ['id' => (int) $id, 'name' => $name];
}

// Interface strings for the JavaScript: every string whose key starts with "b_" or "f_"/"o_"/"sec_".
$strings = [];
foreach (get_string_manager()->load_component_strings('theme_instracta', current_language()) as $key => $value) {
    if (preg_match('/^(b_|bs_)/', $key)) {
        $strings[$key] = $value;
    }
}

$config = [
    'ajaxurl' => (new moodle_url('/theme/instracta/builder/ajax.php'))->out(false),
    'previewurl' => (new moodle_url('/theme/instracta/builder/preview.php'))->out(false),
    'siteurl' => (new moodle_url('/'))->out(false),
    'sesskey' => sesskey(),
    'schema' => registry::schema_for_js(),
    'layout' => manager::get_draft(),
    'haschanges' => manager::has_unpublished_changes(),
    'revisions' => manager::revisions(),
    'categories' => $categories,
    'maxupload' => min(5 * 1024 * 1024, get_max_upload_file_size($CFG->maxbytes)),
    'strings' => $strings,
];

$PAGE->requires->js_call_amd('theme_instracta/builder', 'init', [$config]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_instracta/builder/app', [
    'previewurl' => $config['previewurl'],
    'exiturl' => (new moodle_url('/admin/settings.php', ['section' => 'themesettinginstracta']))->out(false),
    'viewurl' => $config['siteurl'],
    'templatesurl' => (new moodle_url('/theme/instracta/sitetemplates.php'))->out(false),
]);
echo $OUTPUT->footer();
