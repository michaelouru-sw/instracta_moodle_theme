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
 * Site home layout: renders the published page builder layout (or the draft inside the builder preview).
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use theme_instracta\builder\manager;
use theme_instracta\builder\renderer;

$preview = defined('THEME_INSTRACTA_PREVIEW') && THEME_INSTRACTA_PREVIEW;
$home = ['before' => '', 'after' => '', 'hasmain' => true];
$active = false;

if ($preview || theme_instracta_home_takeover_enabled()) {
    $layoutdata = $preview ? manager::get_draft() : manager::get_published();
    if ($layoutdata !== null) {
        $home = (new renderer($OUTPUT, $preview))->render_layout($layoutdata);
        $active = true;
    }
}

$extra = [];
if ($active) {
    $extra[] = 'instracta-home';
}
if ($preview) {
    $extra[] = 'instracta-preview';
}

$context = theme_instracta\layout::drawers_context($extra);
$context['homeactive'] = $active;
$context['homebefore'] = $home['before'];
$context['homeafter'] = $home['after'];
$context['hasmain'] = $home['hasmain'];
if ($active && !$preview && has_capability('theme/instracta:managehome', context_system::instance())) {
    $context['canedit'] = true;
    $context['editurl'] = (new moodle_url('/theme/instracta/builder/index.php'))->out(false);
}
$context = theme_instracta\layout::finish($context);

echo $OUTPUT->render_from_template('theme_instracta/drawers', $context);
