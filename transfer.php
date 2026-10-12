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
 * Export and import the Instracta settings, and check the site configuration.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('themeselector');
require_capability('moodle/site:config', context_system::instance());

$url = new moodle_url('/theme/instracta/transfer.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('transfer_title', 'theme_instracta'));
$PAGE->set_heading(get_string('transfer_title', 'theme_instracta'));

if (optional_param('export', 0, PARAM_BOOL)) {
    require_sesskey();
    send_file(theme_instracta\transfer::export(), 'instracta-settings-' . date('Ymd') . '.json', 0, 0, true, true, 'application/json');
    die();
}

$message = '';
if (optional_param('import', 0, PARAM_BOOL)) {
    require_sesskey();
    $file = $_FILES['settingsfile'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 1048576) {
        $message = $OUTPUT->notification(get_string('tr_nofile', 'theme_instracta'), 'error');
    } else {
        try {
            [$applied, $rejected] = theme_instracta\transfer::import((string) file_get_contents($file['tmp_name']));
            $message = $OUTPUT->notification(get_string('tr_done', 'theme_instracta', $applied), 'success');
            if ($rejected) {
                $message .= $OUTPUT->notification(get_string('tr_rejected', 'theme_instracta', s(implode(', ', $rejected))), 'warning');
            }
        } catch (moodle_exception $e) {
            $message = $OUTPUT->notification($e->getMessage(), 'error');
        }
    }
}

echo $OUTPUT->header();
echo $message;
echo $OUTPUT->heading(get_string('transfer_title', 'theme_instracta'), 3);
echo html_writer::tag('p', get_string('tr_export_desc', 'theme_instracta'));
echo html_writer::link(new moodle_url($url, ['export' => 1, 'sesskey' => sesskey()]), get_string('tr_export', 'theme_instracta'),
    ['class' => 'btn btn-primary mb-4']);

echo $OUTPUT->heading(get_string('tr_import', 'theme_instracta'), 4);
echo html_writer::tag('p', get_string('tr_import_desc', 'theme_instracta'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'enctype' => 'multipart/form-data',
    'class' => 'mb-4']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'import', 'value' => 1]);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'settingsfile', 'accept' => '.json,application/json',
    'class' => 'form-control mb-2', 'required' => 'required', 'aria-label' => get_string('tr_import', 'theme_instracta')]);
echo html_writer::tag('button', get_string('tr_import', 'theme_instracta'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo $OUTPUT->heading(get_string('hc_title', 'theme_instracta'), 4);
$rows = '';
foreach (theme_instracta\transfer::checks() as $check) {
    $icon = $check['ok'] ? 'fa-circle-check text-success' : 'fa-triangle-exclamation text-warning';
    $rows .= html_writer::tag('li', html_writer::tag('i', '', ['class' => 'fa-solid ' . $icon . ' me-2', 'aria-hidden' => 'true']) .
        s($check['text']), ['class' => 'mb-1']);
}
echo html_writer::tag('ul', $rows, ['class' => 'list-unstyled']);
echo $OUTPUT->footer();
