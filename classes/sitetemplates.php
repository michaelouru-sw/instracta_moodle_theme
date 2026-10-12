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

namespace theme_instracta;

use theme_instracta\builder\manager;
use theme_instracta\builder\registry;

/**
 * Site templates: a design preset plus a home page for a kind of learning platform.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sitetemplates {

    /** @var string Marker written to template files. */
    const FORMAT = 'instracta-template';

    /** @var string Config key holding the undo information. */
    const BACKUP = 'templatebackup';

    /**
     * Templates shipped with the theme, in display order.
     *
     * @return array key => template
     */
    public static function bundled(): array {
        $out = [];
        foreach (['mooc', 'university', 'lnd', 'kids', 'simulation', 'bootcamp'] as $key) {
            $file = __DIR__ . '/../pack/' . $key . '.php';
            if (is_readable($file)) {
                $tpl = require($file);
                $tpl['key'] = $key;
                $tpl['settings'] = array_merge(['primarycolor' => '', 'accentcolor' => '', 'gradientstart' => '',
                    'gradientend' => '', 'footerabout' => ''], $tpl['settings']);
                $out[$key] = $tpl;
            }
        }
        return $out;
    }

    /**
     * Build a section definition for a template file.
     *
     * @param string $type
     * @param array $props
     * @param array $style
     * @return array
     */
    public static function sec(string $type, array $props = [], array $style = []): array {
        return ['type' => $type, 'props' => $props, 'style' => $style];
    }

    /**
     * Turn a template's section list into a full sanitised layout.
     *
     * @param array $sections
     * @return array
     */
    public static function build_layout(array $sections): array {
        $built = [];
        foreach ($sections as $s) {
            if (empty($s['type']) || !registry::get($s['type'])) {
                continue;
            }
            $built[] = registry::new_section($s['type'], $s['props'] ?? [], $s['style'] ?? []);
        }
        return registry::sanitize_layout(['v' => 1, 'sections' => $built]);
    }

    /**
     * Apply a template.
     *
     * @param array $tpl template with 'name', 'settings', 'sections'
     * @param bool $design apply the design settings
     * @param bool $home replace and publish the home page
     * @param int $userid
     * @return array [settings applied, settings rejected]
     */
    public static function apply(array $tpl, bool $design, bool $home, int $userid): array {
        global $DB;
        $backup = ['time' => time(), 'name' => (string) ($tpl['name'] ?? ''), 'settings' => null, 'layout' => null];
        $applied = 0;
        $rejected = [];

        if ($design && !empty($tpl['settings']) && is_array($tpl['settings'])) {
            $known = transfer::settings();
            $backup['settings'] = [];
            foreach ($tpl['settings'] as $name => $value) {
                if (isset($known[$name])) {
                    $old = get_config('theme_instracta', $name);
                    $backup['settings'][$name] = ($old === false) ? null : $old;
                }
            }
            [$applied, $rejected] = transfer::apply($tpl['settings']);
        }

        if ($home && !empty($tpl['sections'])) {
            $row = $DB->get_record('theme_instracta_page', ['pagekey' => 'home', 'status' => 'published']);
            $backup['layout'] = $row ? $row->layout : null;
            $layout = self::build_layout($tpl['sections']);
            if ($layout['sections']) {
                manager::save_draft($layout, $userid);
                manager::publish($userid, get_string('tpl_revision', 'theme_instracta', $tpl['name'] ?? ''));
            }
        }

        set_config(self::BACKUP, json_encode($backup, JSON_UNESCAPED_UNICODE), 'theme_instracta');
        theme_reset_all_caches();
        return [$applied, $rejected];
    }

    /**
     * Information about the last template that can still be undone.
     *
     * @return array|null
     */
    public static function backup(): ?array {
        $raw = get_config('theme_instracta', self::BACKUP);
        $data = $raw ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }

    /**
     * Undo the last template.
     *
     * @param int $userid
     * @return bool
     */
    public static function undo(int $userid): bool {
        $data = self::backup();
        if (!$data) {
            return false;
        }
        if (is_array($data['settings'])) {
            $known = transfer::settings();
            foreach ($data['settings'] as $name => $value) {
                if (!isset($known[$name])) {
                    continue;
                }
                if ($value === null) {
                    unset_config($name, 'theme_instracta');
                } else {
                    set_config($name, $value, 'theme_instracta');
                }
            }
        }
        if (!empty($data['layout'])) {
            $layout = json_decode($data['layout'], true);
            if (is_array($layout)) {
                manager::save_draft($layout, $userid);
                manager::publish($userid, get_string('tpl_undone', 'theme_instracta'));
            }
        }
        unset_config(self::BACKUP, 'theme_instracta');
        theme_reset_all_caches();
        return true;
    }

    /**
     * The current site as a template file.
     *
     * @param string $name
     * @return string JSON
     */
    public static function export(string $name): string {
        $settings = json_decode(transfer::export(), true)['settings'] ?? [];
        // Per-site content stays out of a shareable template.
        foreach (['catcolors', 'notice_text_1', 'notice_text_2', 'notice_text_3'] as $drop) {
            unset($settings[$drop]);
        }
        $draft = manager::get_published() ?? manager::get_draft();
        return json_encode([
            'format' => self::FORMAT, 'version' => 1, 'name' => $name, 'tagline' => '',
            'settings' => $settings, 'layout' => $draft,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Read a template file.
     *
     * @param string $json
     * @return array template ready for apply()
     */
    public static function parse(string $json): array {
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? '') !== self::FORMAT || !is_array($data['settings'] ?? null)) {
            throw new \moodle_exception('tpl_invalid', 'theme_instracta');
        }
        $sections = [];
        foreach (($data['layout']['sections'] ?? []) as $s) {
            if (is_array($s) && isset($s['type'])) {
                $sections[] = ['type' => $s['type'], 'props' => $s['props'] ?? [], 'style' => $s['style'] ?? []];
            }
        }
        return [
            'name' => clean_param((string) ($data['name'] ?? 'Imported'), PARAM_TEXT),
            'settings' => $data['settings'],
            'sections' => $sections,
        ];
    }
}
