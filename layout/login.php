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
 * Login layout: split screen with a configurable brand panel.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use theme_instracta\tokens;

$theme = $PAGE->theme;
$sitename = format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]);
$headline = trim((string) tokens::setting($theme, 'loginheadline', ''));
$subtext = trim((string) tokens::setting($theme, 'loginsubtext', ''));
$hasbg = !empty($theme->setting_file_url('loginbackground', 'loginbackground'));

$logourl = $OUTPUT->should_display_navbar_logo() ? $OUTPUT->get_compact_logo_url() : false;

$templatecontext = [
    'sitename' => $sitename,
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(['instracta-login-page']),
    'headline' => $headline !== '' ? $headline : get_string('login_headline_default', 'theme_instracta'),
    'subtext' => $subtext !== '' ? $subtext : get_string('login_subtext_default', 'theme_instracta'),
    'hasbg' => $hasbg,
    'homeurl' => $CFG->wwwroot . '/',
    'haslogo' => (bool) $logourl,
    'logourl' => $logourl ? $logourl->out(false) : '',
    'year' => date('Y'),
];

echo $OUTPUT->render_from_template('theme_instracta/login', $templatecontext);
