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
 * Site templates: pick a ready-made design and home page, import or export one.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use theme_instracta\sitetemplates;
use theme_instracta\builder\registry;

admin_externalpage_setup('themeselector');
require_capability('moodle/site:config', context_system::instance());
require_capability('theme/instracta:managehome', context_system::instance());

$s = 'theme_instracta';
$url = new moodle_url('/theme/instracta/sitetemplates.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('tpl_title', $s));
$PAGE->set_heading(get_string('tpl_title', $s));
$PAGE->requires->css('/theme/instracta/style/templates.css');

if (optional_param('export', 0, PARAM_BOOL)) {
    require_sesskey();
    send_file(sitetemplates::export(clean_param($SITE->shortname, PARAM_TEXT)),
        'instracta-template-' . date('Ymd') . '.json', 0, 0, true, true, 'application/json');
    die();
}

$bundled = sitetemplates::bundled();
$message = '';
$report = function (array $result, string $name) use ($OUTPUT, $s): string {
    [$applied, $rejected] = $result;
    $out = $OUTPUT->notification(get_string('tpl_applied', $s, $name), 'success');
    if ($rejected) {
        $out .= $OUTPUT->notification(get_string('tr_rejected', $s, s(implode(', ', $rejected))), 'warning');
    }
    return $out;
};

if (optional_param('apply', '', PARAM_ALPHANUMEXT) !== '') {
    require_sesskey();
    $key = required_param('apply', PARAM_ALPHANUMEXT);
    if (isset($bundled[$key])) {
        $result = sitetemplates::apply($bundled[$key], (bool) optional_param('design', 0, PARAM_BOOL),
            (bool) optional_param('home', 0, PARAM_BOOL), $USER->id);
        $message = $report($result, $bundled[$key]['name']);
    }
} else if (optional_param('undo', 0, PARAM_BOOL)) {
    require_sesskey();
    $message = sitetemplates::undo($USER->id) ? $OUTPUT->notification(get_string('tpl_undone', $s), 'success') : '';
} else if (optional_param('import', 0, PARAM_BOOL)) {
    require_sesskey();
    $file = $_FILES['templatefile'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2097152) {
        $message = $OUTPUT->notification(get_string('tpl_nofile', $s), 'error');
    } else {
        try {
            $tpl = sitetemplates::parse((string) file_get_contents($file['tmp_name']));
            $result = sitetemplates::apply($tpl, (bool) optional_param('design', 0, PARAM_BOOL),
                (bool) optional_param('home', 0, PARAM_BOOL), $USER->id);
            $message = $report($result, $tpl['name']);
        } catch (moodle_exception $e) {
            $message = $OUTPUT->notification($e->getMessage(), 'error');
        }
    }
}

// A tiny wireframe of the home page, one bar per section.
$wire = function (array $sections): string {
    $bars = '';
    foreach ($sections as $sec) {
        $bars .= html_writer::span('', 'tpl-bar tpl-bar-' . s($sec['type']) .
            (($sec['style']['bg'] ?? '') ? ' tpl-bg-' . s($sec['style']['bg']) : ''));
    }
    return html_writer::div($bars, 'tpl-wire');
};

echo $OUTPUT->header();
echo $message;
echo html_writer::tag('p', get_string('tpl_intro', $s), ['class' => 'tpl-intro']);

$backup = sitetemplates::backup();
if ($backup) {
    $undo = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'tpl-undo']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'undo', 'value' => 1]) .
        html_writer::span(get_string('tpl_lastapplied', $s, ['name' => s($backup['name']), 'time' => userdate($backup['time'])])) .
        html_writer::tag('button', get_string('tpl_undo', $s), ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']) .
        html_writer::end_tag('form');
    echo html_writer::div($undo, 'alert alert-info');
}

echo html_writer::start_div('tpl-grid');
foreach ($bundled as $key => $tpl) {
    $layout = sitetemplates::build_layout($tpl['sections']);
    $card = html_writer::div($wire($layout['sections']), 'tpl-preview', [
        'style' => 'background:linear-gradient(135deg,' . s($tpl['swatch'][0]) . ',' . s($tpl['swatch'][1]) . ')']);
    $body = html_writer::tag('h3', s($tpl['name']), ['class' => 'tpl-name']);
    $body .= html_writer::tag('p', s($tpl['tagline']), ['class' => 'tpl-tag']);
    $body .= html_writer::tag('p', html_writer::tag('strong', get_string('tpl_for', $s) . ': ') . s($tpl['for']), ['class' => 'tpl-for']);
    $body .= html_writer::tag('p', get_string('tpl_sections', $s, count($layout['sections'])), ['class' => 'tpl-meta']);
    $form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'apply', 'value' => $key]);
    foreach (['design' => 'tpl_opt_design', 'home' => 'tpl_opt_home'] as $name => $str) {
        $form .= html_writer::tag('label', html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $name,
            'value' => 1, 'checked' => 'checked']) . ' ' . get_string($str, $s), ['class' => 'tpl-opt']);
    }
    $form .= html_writer::tag('button', get_string('tpl_apply', $s), ['type' => 'submit', 'class' => 'btn btn-primary',
        'data-confirm' => get_string('tpl_confirm', $s)]);
    $body .= html_writer::tag('form', $form, ['method' => 'post', 'action' => $url->out(false), 'class' => 'tpl-form',
        'onsubmit' => 'return confirm(this.querySelector("[data-confirm]").getAttribute("data-confirm"));']);
    echo html_writer::div($card . html_writer::div($body, 'tpl-body'), 'tpl-card', ['id' => 'tpl-' . $key]);
}
echo html_writer::end_div();

// Import and export.
echo html_writer::tag('h3', get_string('tpl_yours', $s), ['class' => 'tpl-h']);
echo html_writer::start_div('tpl-tools');
$exp = html_writer::tag('h4', get_string('tpl_export', $s)) . html_writer::tag('p', get_string('tpl_export_desc', $s)) .
    html_writer::link(new moodle_url($url, ['export' => 1, 'sesskey' => sesskey()]), get_string('tpl_export_btn', $s), ['class' => 'btn btn-secondary']);
echo html_writer::div($exp, 'tpl-tool');
$imp = html_writer::tag('h4', get_string('tpl_import', $s)) . html_writer::tag('p', get_string('tpl_import_desc', $s));
$imp .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$imp .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'import', 'value' => 1]);
$imp .= html_writer::empty_tag('input', ['type' => 'file', 'name' => 'templatefile', 'accept' => '.json,application/json', 'class' => 'mb-2', 'required' => 'required']);
foreach (['design' => 'tpl_opt_design', 'home' => 'tpl_opt_home'] as $name => $str) {
    $imp .= html_writer::tag('label', html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $name, 'value' => 1,
        'checked' => 'checked']) . ' ' . get_string($str, $s), ['class' => 'tpl-opt']);
}
$imp .= html_writer::tag('button', get_string('tpl_import_btn', $s), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::div(html_writer::tag('form', $imp, ['method' => 'post', 'enctype' => 'multipart/form-data', 'action' => $url->out(false)]), 'tpl-tool');
echo html_writer::end_div();

echo $OUTPUT->footer();
