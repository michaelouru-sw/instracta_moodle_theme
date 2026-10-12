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

use html_writer;
use moodle_url;

/**
 * Site features that are configured on the Menus and Helpers settings tabs: menus, notices, category colours, the mobile
 * bar, activity navigation and teacher hints.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class features {

    /** @var string Default links of the mobile bar. */
    const MOBILE_BAR_DEFAULT = "Home|/|house\nCourses|/theme/instracta/catalogue.php|book-open\nDashboard|/my/|gauge\nProfile|/user/profile.php|user";

    /**
     * Read one setting.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function cfg(string $name, $default = '') {
        $value = get_config('theme_instracta', $name);
        return ($value === false || $value === null) ? $default : $value;
    }

    /**
     * Whether a setting is switched on. Unset settings use the default.
     *
     * @param string $name
     * @param bool $default
     * @return bool
     */
    public static function on(string $name, bool $default = true): bool {
        $value = get_config('theme_instracta', $name);
        return ($value === false || $value === null) ? $default : (bool) $value;
    }

    /**
     * A URL typed by an administrator: only site paths and http(s) links are accepted.
     *
     * @param string $raw
     * @return moodle_url|null
     */
    public static function safe_url(string $raw): ?moodle_url {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if ($raw[0] === '/' && substr($raw, 0, 2) !== '//') {
            return new moodle_url($raw);
        }
        if (preg_match('~^https?://~i', $raw)) {
            return new moodle_url($raw);
        }
        return null;
    }

    /**
     * Whether the current user belongs to an audience.
     *
     * @param string $audience all, user, guest, teacher or admin
     * @return bool
     */
    public static function audience_ok(string $audience): bool {
        global $PAGE;
        $loggedin = isloggedin() && !isguestuser();
        switch ($audience) {
            case 'user':
                return $loggedin;
            case 'guest':
                return !$loggedin;
            case 'admin':
                return is_siteadmin();
            case 'teacher':
                return $loggedin && (is_siteadmin() || has_capability('moodle/course:update', $PAGE->context));
            default:
                return true;
        }
    }

    /**
     * Menu items typed by the administrator. Format per line: Label | URL | audience. A line starting with ">" is a
     * child of the previous top level item.
     *
     * @param string $text
     * @return array list of ['label', 'url', 'audience', 'children' => [...]]
     */
    public static function parse_menu(string $text): array {
        $items = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $child = $line[0] === '>';
            $parts = array_map('trim', explode('|', ltrim($line, '> ')));
            $label = clean_param($parts[0] ?? '', PARAM_TEXT);
            if ($label === '') {
                continue;
            }
            $audience = in_array($parts[2] ?? '', ['all', 'user', 'guest', 'teacher', 'admin'], true) ? $parts[2] : 'all';
            $item = ['label' => $label, 'url' => $parts[1] ?? '', 'audience' => $audience, 'children' => []];
            if ($child && $items) {
                $items[count($items) - 1]['children'][] = $item;
            } else {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Add the configured menu items, and the catalogue link, to the primary navigation.
     *
     * @param \core\navigation\views\primary $primary
     */
    public static function extend_primary(\core\navigation\views\primary $primary): void {
        $items = self::parse_menu((string) self::cfg('menuitems'));
        if (self::on('catalogue', true) && self::cfg('menuitems') === '') {
            $items[] = ['label' => get_string('catalogue', 'theme_instracta'), 'url' => '/theme/instracta/catalogue.php',
                'audience' => 'all', 'children' => []];
        }
        foreach ($items as $i => $item) {
            if (!self::audience_ok($item['audience'])) {
                continue;
            }
            $url = self::safe_url($item['url']);
            $kids = array_filter($item['children'], fn($c) => self::audience_ok($c['audience']) && self::safe_url($c['url']));
            if (!$url && !$kids) {
                continue;
            }
            $node = $primary->add($item['label'], $url, \navigation_node::TYPE_CUSTOM, null, 'instracta-menu-' . $i);
            foreach ($kids as $j => $child) {
                $node->add($child['label'], self::safe_url($child['url']), \navigation_node::TYPE_CUSTOM, null,
                    'instracta-menu-' . $i . '-' . $j);
            }
        }
    }

    /**
     * The mobile bottom bar.
     *
     * @return string
     */
    public static function mobile_bar(): string {
        if (!self::on('mobilebar', false) || self::minimal_layout()) {
            return '';
        }
        $links = '';
        $count = 0;
        foreach (preg_split('/\R/', (string) self::cfg('mobilebaritems', self::MOBILE_BAR_DEFAULT)) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $url = self::safe_url($parts[1] ?? '');
            $label = clean_param($parts[0] ?? '', PARAM_TEXT);
            if (!$url || $label === '' || $count >= 5) {
                continue;
            }
            $icon = preg_replace('/[^a-z0-9-]/', '', strtolower($parts[2] ?? 'circle')) ?: 'circle';
            $links .= html_writer::link($url, html_writer::tag('i', '', ['class' => "fa-solid fa-$icon", 'aria-hidden' => 'true']) .
                html_writer::span(s($label)), ['class' => 'instracta-mbar__item']);
            $count++;
        }
        if ($links === '') {
            return '';
        }
        return html_writer::tag('nav', $links, ['class' => 'instracta-mbar d-md-none d-print-none',
            'aria-label' => get_string('mobilebar', 'theme_instracta')]);
    }

    // Category colours ("flavours").

    /**
     * Brand colour overrides for the category being viewed.
     *
     * @return string CSS, or an empty string
     */
    public static function category_css(): string {
        global $PAGE;
        $map = [];
        foreach (preg_split('/\R/', (string) self::cfg('catcolors')) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) >= 2 && ctype_digit($parts[0]) && tokens::is_hex($parts[1])) {
                $map[(int) $parts[0]] = [$parts[1], tokens::is_hex($parts[2] ?? '') ? $parts[2] : ''];
            }
        }
        if (!$map) {
            return '';
        }
        $catid = 0;
        if (!empty($PAGE->course->category) && $PAGE->course->id != SITEID) {
            $catid = (int) $PAGE->course->category;
        } else if ($PAGE->context->contextlevel == CONTEXT_COURSECAT) {
            $catid = (int) $PAGE->context->instanceid;
        }
        // The nearest ancestor with a colour wins.
        while ($catid && !isset($map[$catid])) {
            $cat = \core_course_category::get($catid, IGNORE_MISSING);
            $catid = $cat ? (int) $cat->parent : 0;
        }
        if (!$catid) {
            return '';
        }
        [$primary, $accent] = $map[$catid];
        $rgb = self::rgb($primary);
        $hover = self::shade($primary, .86);
        $soft = self::tint($primary, .9);
        $softer = self::tint($primary, .95);
        $css = ':root:not([data-instracta-theme="dark"]){--instracta-primary:' . $primary . ';--instracta-primary-rgb:' .
            implode(',', $rgb) . ';--instracta-primary-hover:' . $hover . ';--instracta-primary-soft:' . $soft .
            ';--instracta-primary-softer:' . $softer . ';--instracta-heading:' . $primary . ';--instracta-grad-a:' . $primary .
            ';--instracta-grad-b:' . self::tint($primary, .2) . ';--instracta-dark-bg:' . self::shade($primary, .45) . ';';
        if ($accent) {
            $css .= '--instracta-accent:' . $accent . ';';
        }
        return $css . '}';
    }

    /**
     * Hex colour to an RGB triple.
     *
     * @param string $hex
     * @return int[]
     */
    protected static function rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * Multiply a colour's channels.
     *
     * @param string $hex
     * @param float $factor
     * @return string
     */
    protected static function shade(string $hex, float $factor): string {
        $c = array_map(fn($v) => max(0, min(255, (int) round($v * $factor))), self::rgb($hex));
        return sprintf('#%02x%02x%02x', ...$c);
    }

    /**
     * Blend a colour with white.
     *
     * @param string $hex
     * @param float $white share of white, 0 to 1
     * @return string
     */
    protected static function tint(string $hex, float $white): string {
        $c = array_map(fn($v) => (int) round($v * (1 - $white) + 255 * $white), self::rgb($hex));
        return sprintf('#%02x%02x%02x', ...$c);
    }

    /**
     * Pages without the normal chrome (login, popups, embedded, maintenance) get no banners or bars.
     *
     * @return bool
     */
    protected static function minimal_layout(): bool {
        global $PAGE;
        if ($PAGE->has_set_url() && strpos($PAGE->url->get_path(), '/theme/instracta/builder/') !== false) {
            return true;
        }
        return in_array($PAGE->pagelayout, ['login', 'embedded', 'popup', 'print', 'maintenance', 'redirect', 'secure'], true);
    }

    // Notices and teacher hints.

    /**
     * Which page group the current page belongs to.
     *
     * @return string home, dashboard, course or other
     */
    protected static function page_group(): string {
        global $PAGE;
        if ($PAGE->pagelayout === 'frontpage') {
            return 'home';
        }
        if ($PAGE->pagelayout === 'mydashboard') {
            return 'dashboard';
        }
        if ($PAGE->course && $PAGE->course->id != SITEID) {
            return 'course';
        }
        return 'other';
    }

    /**
     * Turn plain text with [label](url) links into safe HTML.
     *
     * @param string $text
     * @return string
     */
    protected static function inline_links(string $text): string {
        $html = s($text);
        return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
            $url = self::safe_url(htmlspecialchars_decode($m[2]));
            return $url ? html_writer::link($url, $m[1]) : $m[0];
        }, $html);
    }

    /**
     * Date typed as YYYY-MM-DD or YYYY-MM-DD HH:MM to a timestamp.
     *
     * @param string $text
     * @param bool $endofday when no time is given, use the end of that day
     * @return int 0 when empty or invalid
     */
    protected static function stamp(string $text, bool $endofday): int {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        $time = strtotime($text);
        if ($time === false) {
            return 0;
        }
        if ($endofday && !preg_match('/\d:\d/', $text)) {
            $time += DAYSECS - 1;
        }
        return $time;
    }

    /**
     * The administrator's banners that apply right now to this user and page.
     *
     * @return array list of ['id', 'html', 'type', 'dismiss']
     */
    public static function active_notices(): array {
        $now = time();
        $group = self::page_group();
        $out = [];
        for ($i = 1; $i <= 3; $i++) {
            $text = trim((string) self::cfg("notice{$i}_text"));
            if ($text === '') {
                continue;
            }
            $from = self::stamp((string) self::cfg("notice{$i}_from"), false);
            $to = self::stamp((string) self::cfg("notice{$i}_to"), true);
            if (($from && $now < $from) || ($to && $now > $to)) {
                continue;
            }
            $pages = (string) self::cfg("notice{$i}_pages", 'all');
            if ($pages !== 'all' && $pages !== $group) {
                continue;
            }
            if (!self::audience_ok((string) self::cfg("notice{$i}_audience", 'all'))) {
                continue;
            }
            $type = (string) self::cfg("notice{$i}_type", 'info');
            $out[] = [
                'id' => $i . '-' . substr(md5($text), 0, 6),
                'html' => self::inline_links($text),
                'type' => in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info',
                'dismiss' => self::on("notice{$i}_dismiss", true),
            ];
        }
        return $out;
    }

    /**
     * Hints for teachers on a course page: hidden course, open enrolment, switched role and live numbers.
     *
     * @return array list of ['type', 'html']
     */
    public static function teacher_hints(): array {
        global $PAGE, $DB;
        if (!self::on('teacherhints', true) || !$PAGE->course || $PAGE->course->id == SITEID ||
                !$PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
            return [];
        }
        $course = $PAGE->course;
        $context = \context_course::instance($course->id);
        if (!has_capability('moodle/course:update', $context)) {
            return [];
        }
        $hints = [];
        if (!$course->visible) {
            $hints[] = ['type' => 'warning', 'html' => get_string('th_hidden', 'theme_instracta')];
        }
        if (is_role_switched($course->id)) {
            $hints[] = ['type' => 'info', 'html' => get_string('th_switched', 'theme_instracta')];
        }
        $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'status' => ENROL_INSTANCE_ENABLED],
            '', 'id, enrol, password');
        foreach ($instances as $instance) {
            if ($instance->enrol === 'guest') {
                $hints[] = ['type' => 'info', 'html' => get_string('th_guest', 'theme_instracta')];
            } else if ($instance->enrol === 'self' && (string) $instance->password === '') {
                $hints[] = ['type' => 'info', 'html' => get_string('th_selfopen', 'theme_instracta')];
            }
        }
        $enrolled = count_enrolled_users($context, '', 0, true);
        $done = $course->enablecompletion ?
            (int) $DB->count_records_select('course_completions', 'course = ? AND timecompleted IS NOT NULL', [$course->id]) : null;
        $stats = get_string('th_enrolled', 'theme_instracta', $enrolled);
        if ($done !== null) {
            $stats .= ' · ' . get_string('th_completed', 'theme_instracta', $done);
        }
        $hints[] = ['type' => 'stats', 'html' => $stats];
        return $hints;
    }

    /**
     * Notices and hints as one block. A script moves it to the top of the main area; hidden until then.
     *
     * @return string
     */
    public static function notices_html(): string {
        if (self::minimal_layout()) {
            return '';
        }
        $items = '';
        foreach (self::active_notices() as $n) {
            $close = $n['dismiss'] ? html_writer::tag('button', html_writer::tag('i', '', ['class' => 'fa-solid fa-xmark',
                'aria-hidden' => 'true']), ['type' => 'button', 'class' => 'instracta-notice__close', 'data-action' => 'dismiss',
                'aria-label' => get_string('close', 'theme_instracta')]) : '';
            $items .= html_writer::div(html_writer::div($n['html'], 'instracta-notice__text') . $close,
                'instracta-notice instracta-notice--' . $n['type'], ['data-notice' => $n['id'], 'role' => 'status']);
        }
        $hints = self::teacher_hints();
        if ($hints) {
            $rows = '';
            foreach ($hints as $h) {
                $rows .= html_writer::tag('li', $h['html'], ['class' => 'is-' . $h['type']]);
            }
            $items .= html_writer::div(html_writer::div(get_string('th_title', 'theme_instracta'), 'instracta-hints__title') .
                html_writer::tag('ul', $rows), 'instracta-hints');
        }
        if ($items === '') {
            return '';
        }
        return html_writer::div($items, 'instracta-notices d-print-none', ['data-instracta-notices' => '1', 'hidden' => 'hidden']);
    }

    // Activity navigation.

    /**
     * Previous and next activity bar for activity pages.
     *
     * @return string
     */
    public static function activity_bar(): string {
        global $PAGE;
        if (self::minimal_layout() || !self::on('activitybar', true) || !$PAGE->course || $PAGE->course->id == SITEID || !$PAGE->cm) {
            return '';
        }
        $modinfo = get_fast_modinfo($PAGE->course);
        $list = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->uservisible && $cm->url && $cm->modname !== 'label') {
                $list[] = $cm;
            }
        }
        $index = null;
        foreach ($list as $i => $cm) {
            if ($cm->id == $PAGE->cm->id) {
                $index = $i;
                break;
            }
        }
        if ($index === null || count($list) < 2) {
            return '';
        }
        $link = function ($cm, string $class, string $label, string $icon, bool $after) {
            $name = html_writer::span(html_writer::span($label, 'instracta-abar__hint') .
                html_writer::span(s(format_string($cm->name)), 'instracta-abar__name'), 'instracta-abar__text');
            $i = html_writer::tag('i', '', ['class' => 'fa-solid ' . $icon, 'aria-hidden' => 'true']);
            return html_writer::link($cm->url, $after ? $name . $i : $i . $name, ['class' => 'instracta-abar__link ' . $class]);
        };
        $prev = $index > 0 ? $link($list[$index - 1], 'is-prev', get_string('ab_prev', 'theme_instracta'),
            'fa-arrow-left', false) : html_writer::span('', 'instracta-abar__link is-empty');
        $next = $index < count($list) - 1 ? $link($list[$index + 1], 'is-next', get_string('ab_next', 'theme_instracta'),
            'fa-arrow-right', true) : html_writer::span('', 'instracta-abar__link is-empty');
        $pos = get_string('ab_position', 'theme_instracta', ['n' => $index + 1, 'total' => count($list)]);
        $mid = html_writer::div(html_writer::span($pos, 'instracta-abar__pos') . html_writer::div(
            html_writer::div('', 'instracta-abar__fill', ['style' => 'width:' . round(($index + 1) / count($list) * 100) . '%']),
            'instracta-abar__track'), 'instracta-abar__mid');
        return html_writer::tag('nav', $prev . $mid . $next, ['class' => 'instracta-abar d-print-none',
            'aria-label' => get_string('ab_label', 'theme_instracta')]);
    }

    /**
     * The cookie notice.
     *
     * @return string
     */
    public static function cookie_html(): string {
        if (!self::on('cookie', false) || self::minimal_layout()) {
            return '';
        }
        $text = trim((string) self::cfg('cookietext')) ?: get_string('ck_default', 'theme_instracta');
        $url = self::safe_url((string) self::cfg('cookielink'));
        $more = $url ? ' ' . html_writer::link($url, get_string('ck_more', 'theme_instracta')) : '';
        $btn = html_writer::tag('button', get_string('ck_ok', 'theme_instracta'),
            ['type' => 'button', 'class' => 'btn btn-primary btn-sm', 'data-action' => 'accept']);
        return html_writer::div(html_writer::div(s($text) . $more, 'instracta-cookie__text') . $btn,
            'instracta-cookie d-print-none', ['data-instracta-cookie' => '1', 'role' => 'dialog', 'hidden' => 'hidden',
            'aria-label' => get_string('ck_label', 'theme_instracta')]);
    }
}
