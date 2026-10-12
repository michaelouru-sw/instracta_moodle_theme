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
 * Instracta theme callbacks.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS: Boost's default preset followed by the Instracta design system.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_instracta_get_main_scss_content($theme) {
    global $CFG;

    $scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    $scss .= "\n" . theme_instracta\tokens::font_faces($theme) . "\n";
    // Resolved through the theme import paths: theme/instracta/scss/instracta/_main.scss.
    $scss .= "\n@import \"instracta/main\";\n";
    return $scss;
}

/**
 * SCSS that is prepended to the compilation: design token variables and the admin's own pre-SCSS.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_instracta_get_pre_scss($theme) {
    $scss = theme_instracta\tokens::pre_scss($theme);

    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\$behatsite: true;\n";
    }
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }
    return $scss;
}

/**
 * SCSS appended after everything else: CSS custom properties for runtime tokens, images and custom SCSS.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_instracta_get_extra_scss($theme) {
    $content = '';
    $tokens = theme_instracta\tokens::resolve($theme);

    $content .= ':root { --instracta-navbar-style: ' . preg_replace('/[^a-z]/', '', $tokens['navbarstyle']) . '; }' . "\n";

    $loginbg = $theme->setting_file_url('loginbackground', 'loginbackground');
    if (!empty($loginbg)) {
        $content .= 'body.pagelayout-login { --instracta-login-bg: url("' . $loginbg . '"); }' . "\n";
    }
    $herobg = $theme->setting_file_url('sitebackground', 'sitebackground');
    if (!empty($herobg)) {
        $content .= 'body { --instracta-site-bg: url("' . $herobg . '"); }' . "\n";
        $content .= 'body.instracta-has-sitebg #page { background-image: var(--instracta-site-bg); ' .
            'background-size: cover; background-attachment: fixed; }' . "\n";
    }

    // Admin custom SCSS goes last so it can override anything above.
    if (!empty($theme->settings->scss)) {
        $content .= "\n" . $theme->settings->scss . "\n";
    }
    return $content;
}

/**
 * Fallback compiled CSS for when SCSS compilation is unavailable: Boost's own.
 *
 * @return string
 */
function theme_instracta_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/boost/style/moodle.css');
}

/**
 * Serves files stored by the theme settings and the page builder media library.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool|void
 */
function theme_instracta_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        send_file_not_found();
    }

    $themefiles = ['loginbackground', 'sitebackground'];
    if (in_array($filearea, $themefiles, true)) {
        $theme = theme_config::load('instracta');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }

    if ($filearea === 'media') {
        // Page builder media library: public, so guests see the home page images.
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = get_file_storage()->get_file($context->id, 'theme_instracta', 'media', 0, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
        send_stored_file($file, 60 * 60 * 24 * 30, 0, false, ['cacheability' => 'public']);
    }

    send_file_not_found();
}

/**
 * User preferences the theme stores, so the colour mode follows the user across devices.
 *
 * @return array
 */
function theme_instracta_user_preferences(): array {
    return [
        'instracta-color-mode' => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => 'auto',
            'choices' => ['auto', 'light', 'dark'],
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // Reading and course layout choices as one small JSON object, validated by theme_instracta\reading.
        'instracta-reading' => [
            'type' => PARAM_RAW,
            'null' => NULL_NOT_ALLOWED,
            'default' => '',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Whether the home page builder should take over the site home page.
 *
 * @return bool
 */
function theme_instracta_home_takeover_enabled(): bool {
    $enabled = get_config('theme_instracta', 'homebuilder');
    return $enabled === false || (bool) $enabled;
}
