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
 * Static pages (About, Contact, Help, Accessibility) edited in the theme settings.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$key = optional_param('p', 'about', PARAM_ALPHA);
if (!in_array($key, ['about', 'contact', 'help', 'accessibility'], true)) {
    $key = 'about';
}
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/theme/instracta/page.php', ['p' => $key]);
$PAGE->set_pagelayout('standard');
$title = trim((string) get_config('theme_instracta', "page_{$key}_title")) ?: get_string('pg_' . $key, 'theme_instracta');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
$content = trim((string) get_config('theme_instracta', "page_{$key}_content"));
if ($content === '') {
    echo $OUTPUT->notification(get_string('pg_empty', 'theme_instracta'), 'info');
} else {
    echo html_writer::div(format_text($content, FORMAT_HTML, ['context' => $context]), 'instracta-static');
}
echo $OUTPUT->footer();
