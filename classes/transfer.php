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

/**
 * Export and import of the theme settings, and a short configuration check.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transfer {

    /** @var string Marker written to export files. */
    const FORMAT = 'instracta-settings';

    /**
     * All settings of the theme that hold plain values (uploaded files are not moved).
     *
     * @return \admin_setting[] keyed by setting name
     */
    public static function settings(): array {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $root = admin_get_root(true, true);
        $found = [];
        $collect = function ($node) use (&$collect, &$found) {
            if ($node instanceof \admin_settingpage) {
                foreach ($node->settings as $setting) {
                    if ($setting instanceof \admin_settingpage) {
                        $collect($setting);
                        continue;
                    }
                    if ($setting->plugin === 'theme_instracta' && !($setting instanceof \admin_setting_heading) &&
                            !($setting instanceof \admin_setting_description) && !($setting instanceof \admin_setting_configstoredfile)) {
                        $found[$setting->name] = $setting;
                    }
                }
            }
            if ($node instanceof \admin_category) {
                foreach ($node->children as $child) {
                    $collect($child);
                }
            }
        };
        $collect($root->locate('themesettinginstracta'));
        return $found;
    }

    /**
     * Export the current settings.
     *
     * @return string JSON
     */
    public static function export(): string {
        $values = [];
        foreach (self::settings() as $name => $setting) {
            $value = get_config('theme_instracta', $name);
            if ($value !== false && $value !== null) {
                $values[$name] = $value;
            }
        }
        ksort($values);
        return json_encode(['format' => self::FORMAT, 'version' => 1, 'settings' => $values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Import settings from an export file. Every value goes through its own setting's validation.
     *
     * @param string $json
     * @return array [number applied, list of rejected setting names]
     */
    public static function import(string $json): array {
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? '') !== self::FORMAT || !is_array($data['settings'] ?? null)) {
            throw new \moodle_exception('tr_invalid', 'theme_instracta');
        }
        return self::apply($data['settings']);
    }

    /**
     * Write a list of settings. Every value goes through its own setting's validation.
     *
     * @param array $values name => value
     * @return array [number applied, list of rejected setting names]
     */
    public static function apply(array $values): array {
        $known = self::settings();
        $applied = 0;
        $rejected = [];
        foreach ($values as $name => $value) {
            if (!is_string($name) || !isset($known[$name]) || !is_scalar($value)) {
                $rejected[] = clean_param((string) $name, PARAM_ALPHANUMEXT);
                continue;
            }
            $error = $known[$name]->write_setting((string) $value);
            if ($error === '' && (string) get_config('theme_instracta', $name) === (string) $value) {
                $applied++;
            } else {
                $rejected[] = $name;
            }
        }
        theme_reset_all_caches();
        return [$applied, $rejected];
    }

    /**
     * Configuration checks for the administrator.
     *
     * @return array list of ['ok' => bool, 'text' => string]
     */
    public static function checks(): array {
        global $CFG, $DB;
        $out = [];
        $add = function (bool $ok, string $key) use (&$out) {
            $out[] = ['ok' => $ok, 'text' => get_string('hc_' . $key . ($ok ? '_ok' : '_bad'), 'theme_instracta')];
        };
        $add($CFG->theme === 'instracta', 'active');
        $add(empty($CFG->themedesignermode), 'designer');
        $add(!empty($CFG->cachejs), 'cachejs');
        $add(!empty($CFG->enablecompletion), 'completion');
        $add(!empty(get_config('core_admin', 'logo')) || !empty(get_config('core_admin', 'logocompact')), 'logo');
        $logstores = array_filter(explode(',', (string) get_config('tool_log', 'enabled_stores')));
        $add(in_array('logstore_standard', $logstores, true), 'logs');
        $add($DB->record_exists('theme_instracta_page', ['status' => 'published']), 'home');
        return $out;
    }
}
