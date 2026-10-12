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
 * AJAX endpoint of the home page builder.
 *
 * Every action requires a logged in user with theme/instracta:managehome and a valid sesskey.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use theme_instracta\builder\manager;
use theme_instracta\builder\registry;
use theme_instracta\builder\renderer;

require_login(null, false);
$context = context_system::instance();
require_capability('theme/instracta:managehome', $context);
require_sesskey();

$PAGE->set_context($context);
$PAGE->set_url('/theme/instracta/builder/ajax.php');

$action = required_param('action', PARAM_ALPHA);

/**
 * Send a JSON response and stop.
 *
 * @param mixed $data
 * @param int $status
 */
function theme_instracta_json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    die;
}

/**
 * Decode the layout parameter.
 *
 * @return array
 */
function theme_instracta_layout_param(): array {
    $decoded = json_decode(required_param('layout', PARAM_RAW), true);
    if (!is_array($decoded)) {
        theme_instracta_json(['error' => get_string('b_err_layout', 'theme_instracta')], 400);
    }
    return $decoded;
}

try {
    switch ($action) {
        case 'save':
            $layout = manager::save_draft(theme_instracta_layout_param(), (int) $USER->id);
            theme_instracta_json([
                'ok' => true,
                'layout' => $layout,
                'haschanges' => manager::has_unpublished_changes(),
                'saved' => userdate(time(), get_string('strftimetime', 'langconfig')),
            ]);
            break;

        case 'render':
            // Render an unsaved layout for the live preview. The layout is sanitised exactly as on save.
            $layout = registry::sanitize_layout(theme_instracta_layout_param());
            $result = (new renderer($PAGE->get_renderer('core'), true))->render_layout($layout);
            theme_instracta_json($result);
            break;

        case 'publish':
            $note = optional_param('note', '', PARAM_TEXT);
            manager::save_draft(theme_instracta_layout_param(), (int) $USER->id);
            manager::publish((int) $USER->id, $note);
            theme_instracta_json([
                'ok' => true,
                'haschanges' => false,
                'revisions' => manager::revisions(),
            ]);
            break;

        case 'restore':
            $layout = manager::restore(required_param('id', PARAM_INT), (int) $USER->id);
            if ($layout === null) {
                theme_instracta_json(['error' => get_string('b_err_revision', 'theme_instracta')], 404);
            }
            theme_instracta_json(['ok' => true, 'layout' => $layout, 'haschanges' => manager::has_unpublished_changes()]);
            break;

        case 'discard':
            $layout = manager::discard_draft((int) $USER->id);
            theme_instracta_json(['ok' => true, 'layout' => $layout, 'haschanges' => manager::has_unpublished_changes()]);
            break;

        case 'reset':
            $layout = manager::save_draft(manager::default_layout(), (int) $USER->id);
            theme_instracta_json(['ok' => true, 'layout' => $layout, 'haschanges' => manager::has_unpublished_changes()]);
            break;

        case 'courses':
            $query = trim(optional_param('query', '', PARAM_TEXT));
            $found = [];
            if ($query !== '') {
                $list = core_course_category::search_courses(['search' => $query], ['limit' => 20]);
            } else {
                $list = core_course_category::top()->get_courses(['recursive' => true, 'limit' => 20,
                    'sort' => ['timecreated' => -1]]);
            }
            foreach ($list as $course) {
                if ($course->id == SITEID) {
                    continue;
                }
                $found[] = [
                    'id' => (int) $course->id,
                    'name' => $course->get_formatted_name(),
                    'category' => core_course_category::get($course->category, IGNORE_MISSING)
                        ? core_course_category::get($course->category)->get_formatted_name() : '',
                ];
            }
            theme_instracta_json(['courses' => $found]);
            break;

        case 'coursenames':
            // Resolve names for course ids already in a section.
            $ids = array_filter(array_map('intval', explode(',', optional_param('ids', '', PARAM_SEQUENCE))));
            $names = [];
            if ($ids) {
                foreach ($DB->get_records_list('course', 'id', $ids, '', 'id, fullname') as $c) {
                    $names[(int) $c->id] = format_string($c->fullname, true, ['context' => context_course::instance($c->id)]);
                }
            }
            theme_instracta_json(['names' => $names]);
            break;

        case 'media':
            $files = get_file_storage()->get_area_files($context->id, 'theme_instracta', 'media', 0, 'timemodified DESC', false);
            $out = [];
            foreach ($files as $file) {
                $url = moodle_url::make_pluginfile_url($context->id, 'theme_instracta', 'media', 0, '/', $file->get_filename());
                $out[] = [
                    'name' => $file->get_filename(),
                    'url' => $url->out_as_local_url(false),
                    'size' => $file->get_filesize(),
                    'time' => $file->get_timemodified(),
                ];
            }
            theme_instracta_json(['files' => array_slice($out, 0, 200)]);
            break;

        case 'upload':
            theme_instracta_json(theme_instracta_handle_upload($context));
            break;

        case 'deletemedia':
            $name = required_param('name', PARAM_FILE);
            $file = get_file_storage()->get_file($context->id, 'theme_instracta', 'media', 0, '/', $name);
            if ($file) {
                $file->delete();
            }
            theme_instracta_json(['ok' => true]);
            break;

        default:
            theme_instracta_json(['error' => 'unknown action'], 400);
    }
} catch (Throwable $e) {
    debugging($e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), DEBUG_DEVELOPER);
    error_log('theme_instracta builder: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    theme_instracta_json(['error' => get_string('b_err_generic', 'theme_instracta')], 500);
}

/**
 * Store an uploaded image in the builder media library.
 *
 * Only raster images are accepted (no SVG, which could carry script), checked by content and not by name.
 *
 * @param context $context
 * @return array
 */
function theme_instracta_handle_upload(context $context): array {
    global $CFG;
    if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        theme_instracta_json(['error' => get_string('b_err_upload', 'theme_instracta')], 400);
    }
    $limit = min(5 * 1024 * 1024, get_max_upload_file_size($CFG->maxbytes));
    if ($_FILES['file']['size'] > $limit) {
        theme_instracta_json(['error' => get_string('b_err_toolarge', 'theme_instracta', display_size($limit))], 400);
    }

    $info = @getimagesize($_FILES['file']['tmp_name']);
    $types = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (defined('IMAGETYPE_AVIF')) {
        $types[IMAGETYPE_AVIF] = 'avif';
    }
    if (!$info || !isset($types[$info[2]])) {
        theme_instracta_json(['error' => get_string('b_err_imagetype', 'theme_instracta')], 400);
    }

    $base = pathinfo(clean_param($_FILES['file']['name'], PARAM_FILE), PATHINFO_FILENAME);
    $base = trim(preg_replace('/[^a-z0-9_-]+/i', '-', $base), '-');
    $base = $base !== '' ? \core_text::substr($base, 0, 40) : 'image';
    $filename = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $types[$info[2]];

    $file = get_file_storage()->create_file_from_pathname([
        'contextid' => $context->id,
        'component' => 'theme_instracta',
        'filearea' => 'media',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
    ], $_FILES['file']['tmp_name']);

    $url = moodle_url::make_pluginfile_url($context->id, 'theme_instracta', 'media', 0, '/', $file->get_filename());
    return [
        'ok' => true,
        'file' => [
            'name' => $file->get_filename(),
            'url' => $url->out_as_local_url(false),
            'size' => $file->get_filesize(),
            'time' => $file->get_timemodified(),
        ],
    ];
}
