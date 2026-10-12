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
 * Live preview of the draft home page, shown inside the builder's iframe (or opened on its own).
 *
 * It renders the real front page layout with the draft in place of the published page, so the
 * preview is exactly what visitors will see after publishing.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('THEME_INSTRACTA_PREVIEW', true);

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('theme/instracta:managehome', $context);

$PAGE->set_context($context);
$PAGE->set_url('/theme/instracta/builder/preview.php');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title(get_string('b_preview', 'theme_instracta'));
$PAGE->set_heading(format_string($SITE->fullname, true, ['context' => $context]));

\theme_instracta\builder\manager::ensure_seeded();

echo $OUTPUT->header();
echo html_writer::div(
    html_writer::tag('i', '', ['class' => 'fa-solid fa-house', 'aria-hidden' => 'true']) . ' ' .
    get_string('b_mainplaceholder', 'theme_instracta'),
    'instracta-main-placeholder'
);
echo $OUTPUT->footer();
