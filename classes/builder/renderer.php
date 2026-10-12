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

namespace theme_instracta\builder;

/**
 * Turns a sanitised layout into HTML, using one Mustache template per section type.
 *
 * The same renderer produces the public home page and the builder's live preview, so what the
 * editor sees is what visitors get.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer {

    /** @var \renderer_base */
    protected $output;

    /** @var bool Preview mode shows hidden sections and ignores audience rules. */
    protected $preview;

    /** @var array Category names by id, per request. */
    protected $categorynames = [];

    /**
     * Constructor.
     *
     * @param \renderer_base $output
     * @param bool $preview
     */
    public function __construct(\renderer_base $output, bool $preview = false) {
        $this->output = $output;
        $this->preview = $preview;
    }

    /**
     * Render a layout.
     *
     * The Moodle default front page content is a section of its own ("moodlecontent"); the page
     * layout needs to place it in the template, so the result is split around it.
     *
     * @param array $layout
     * @return array ['before' => html, 'after' => html, 'hasmain' => bool]
     */
    public function render_layout(array $layout): array {
        $result = ['before' => '', 'after' => '', 'hasmain' => false];
        $target = 'before';
        foreach ($layout['sections'] ?? [] as $section) {
            if (!$this->is_visible($section)) {
                continue;
            }
            if ($section['type'] === 'moodlecontent') {
                if ($result['hasmain']) {
                    continue; // The default content can only appear once.
                }
                $result['hasmain'] = true;
                $target = 'after';
                continue;
            }
            $result[$target] .= $this->render_section($section);
        }
        return $result;
    }

    /**
     * Whether a section is shown to the current user.
     *
     * @param array $section
     * @return bool
     */
    protected function is_visible(array $section): bool {
        if ($this->preview) {
            return true;
        }
        if (!empty($section['hidden'])) {
            return false;
        }
        $audience = $section['style']['audience'] ?? 'all';
        $isguest = !isloggedin() || isguestuser();
        if ($audience === 'guest' && !$isguest) {
            return false;
        }
        if ($audience === 'loggedin' && $isguest) {
            return false;
        }
        return true;
    }

    /**
     * Render one section, wrapped.
     *
     * @param array $section
     * @return string
     */
    public function render_section(array $section): string {
        $def = registry::get($section['type']);
        if (!$def) {
            return '';
        }
        $props = $this->normalise($def['fields'], $section['props'] ?? []);
        $context = $this->context($section['type'], $props, $section);
        $inner = $this->output->render_from_template('theme_instracta/sections/' . $section['type'], $context);
        return $this->output->render_from_template('theme_instracta/section', $this->wrapper($section, $inner));
    }

    /**
     * Wrapper context: classes and CSS custom properties from the style settings.
     *
     * @param array $section
     * @param string $inner
     * @return array
     */
    protected function wrapper(array $section, string $inner): array {
        $style = array_merge(registry::style_defaults($section['type']), $section['style'] ?? []);
        $bg = $style['bg'];
        $classes = ['instracta-sec', 'instracta-sec--' . $section['type'], 'instracta-bg-' . $bg, 'instracta-w-' . $style['width'],
            'instracta-pt-' . $style['padtop'], 'instracta-pb-' . $style['padbottom'], 'instracta-anim-' . $style['animation']];

        $dark = in_array($bg, ['primary', 'gradient', 'dark', 'image'], true);
        if ($bg === 'color' && $style['bgcolor'] !== '') {
            $dark = self::is_dark_colour($style['bgcolor']);
        }
        if ($dark) {
            $classes[] = 'instracta-on-dark';
        }
        if (!empty($style['hidemobile'])) {
            $classes[] = 'd-none d-md-block';
        }
        if (!empty($style['hidedesktop'])) {
            $classes[] = 'd-md-none';
        }
        if (!empty($section['hidden'])) {
            $classes[] = 'instracta-is-hidden';
        }
        if (!empty($style['cssclass'])) {
            $classes[] = trim(preg_replace('/[^a-z0-9_ -]/i', '', $style['cssclass']));
        }

        $vars = [];
        if ($bg === 'color' && $style['bgcolor'] !== '') {
            $vars[] = '--instracta-bgcolor:' . $style['bgcolor'];
        }
        if ($bg === 'image' && $style['bgimage'] !== '') {
            $vars[] = '--instracta-bgimg:url("' . $this->css_url($this->absolute($style['bgimage'])) . '")';
            $vars[] = '--instracta-overlay:' . round(((int) $style['overlay']) / 100, 2);
        }
        $anchor = $style['anchor'] !== '' ? preg_replace('/[^a-z0-9_-]/i', '', $style['anchor']) : '';

        return [
            'id' => $section['id'],
            'type' => $section['type'],
            'anchor' => $anchor !== '' ? $anchor : 'instracta-' . $section['id'],
            'classes' => implode(' ', array_filter($classes)),
            'vars' => implode(';', $vars),
            'contained' => $style['width'] === 'contained',
            'wide' => $style['width'] === 'wide',
            'inner' => $inner,
            'previewlabel' => $this->preview ? ($section['label'] !== '' ? $section['label'] : get_string('sec_' . $section['type'], 'theme_instracta')) : '',
            'hiddenlabel' => $this->preview && !empty($section['hidden']) ? get_string('b_hidden', 'theme_instracta') : '',
        ];
    }

    /**
     * Whether a hex colour is dark enough to need light text on it.
     *
     * @param string $hex
     * @return bool
     */
    protected static function is_dark_colour(string $hex): bool {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return false;
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 150;
    }

    /**
     * Make a stored URL absolute and escape it for use inside CSS url("...").
     *
     * @param string $url
     * @return string
     */
    protected function css_url(string $url): string {
        return str_replace(['"', "'", '(', ')', '\\', "\n", "\r"], ['%22', '%27', '%28', '%29', '%5C', '', ''], $url);
    }

    /**
     * Prefix site-relative URLs with the site address.
     *
     * @param string $url
     * @return string
     */
    protected function absolute(string $url): string {
        global $CFG;
        if ($url !== '' && $url[0] === '/' && substr($url, 0, 2) !== '//') {
            return $CFG->wwwroot . $url;
        }
        return $url;
    }

    /**
     * Resolve URLs in a property set and add a flag for each selected option (layout_split => true).
     *
     * @param array $fields field definitions
     * @param array $values
     * @return array
     */
    protected function normalise(array $fields, array $values): array {
        $out = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $values[$key] ?? $field['default'];
            switch ($field['type']) {
                case 'url':
                case 'image':
                    $value = $this->absolute((string) $value);
                    break;
                case 'html':
                    $value = format_text((string) $value, FORMAT_HTML, ['context' => \context_system::instance(), 'trusted' => false]);
                    break;
                case 'select':
                    $out[$key . '_' . $value] = true;
                    break;
                case 'list':
                    $items = [];
                    foreach ((array) $value as $index => $item) {
                        $item = $this->normalise($field['fields'], (array) $item);
                        $item['index'] = $index;
                        $item['number'] = $index + 1;
                        $items[] = $item;
                    }
                    $value = $items;
                    $out['has' . $key] = !empty($items);
                    break;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /**
     * Build the template context for a section type, adding live data where needed.
     *
     * @param string $type
     * @param array $props normalised properties
     * @param array $section
     * @return array
     */
    protected function context(string $type, array $props, array $section): array {
        global $CFG;
        $props['wwwroot'] = $CFG->wwwroot;
        $props['preview'] = $this->preview;
        $props['sectionid'] = $section['id'];
        $props['hashead'] = !empty($props['eyebrow']) || !empty($props['title']) || !empty($props['subtitle']);
        $method = 'context_' . $type;
        if (method_exists($this, $method)) {
            return $this->{$method}($props);
        }
        return $props;
    }

    /**
     * Hero.
     *
     * @param array $p
     * @return array
     */
    protected function context_hero(array $p): array {
        global $CFG;
        $p['searchaction'] = $CFG->wwwroot . '/course/search.php';
        $p['hasactions'] = $p['btn1text'] !== '' || $p['btn2text'] !== '';
        $p['hasbtn1'] = $p['btn1text'] !== '' && $p['btn1url'] !== '';
        $p['hasbtn2'] = $p['btn2text'] !== '' && $p['btn2url'] !== '';
        // Hide the log in button for people who are already logged in.
        if (!$this->preview && isloggedin() && !isguestuser() && strpos($p['btn2url'], '/login/') !== false) {
            $p['hasbtn2'] = false;
        }
        return $p;
    }

    /**
     * Call to action.
     *
     * @param array $p
     * @return array
     */
    protected function context_cta(array $p): array {
        $p['hasbtn1'] = $p['btn1text'] !== '' && $p['btn1url'] !== '';
        $p['hasbtn2'] = $p['btn2text'] !== '' && $p['btn2url'] !== '';
        if (!$this->preview && isloggedin() && !isguestuser() && strpos($p['btn1url'], 'signup') !== false) {
            $p['hasbtn1'] = false;
        }
        return $p;
    }

    /**
     * Split section.
     *
     * @param array $p
     * @return array
     */
    protected function context_split(array $p): array {
        $p['hasbtn'] = $p['btntext'] !== '' && $p['btnurl'] !== '';
        $p['hasimage'] = $p['image'] !== '';
        return $p;
    }

    /**
     * Features: cards link only when a URL is set.
     *
     * @param array $p
     * @return array
     */
    protected function context_features(array $p): array {
        foreach ($p['items'] as &$item) {
            $item['haslink'] = $item['url'] !== '';
        }
        return $p;
    }

    /**
     * Stats: live metrics are cached for ten minutes.
     *
     * @param array $p
     * @return array
     */
    protected function context_stats(array $p): array {
        foreach ($p['items'] as &$item) {
            if ($item['metric'] === 'custom') {
                $raw = preg_replace('/[^0-9.]/', '', (string) $item['value']);
                $item['count'] = $raw === '' ? 0 : (float) $raw;
                $item['display'] = (string) $item['value'];
                $item['animatable'] = $raw !== '' && (string) $raw === trim((string) $item['value']);
            } else {
                $item['count'] = $this->metric($item['metric']);
                $item['display'] = number_format($item['count']);
                $item['animatable'] = true;
            }
            $item['count'] = (float) $item['count'];
        }
        return $p;
    }

    /**
     * A live site metric, cached.
     *
     * @param string $metric
     * @return int
     */
    protected function metric(string $metric): int {
        global $DB;
        $cache = \cache::make('theme_instracta', 'stats');
        $cached = $cache->get('metric_' . $metric);
        if ($cached !== false) {
            return (int) $cached;
        }
        switch ($metric) {
            case 'courses':
                $value = $DB->count_records_select('course', 'id <> ? AND visible = 1', [SITEID]);
                break;
            case 'users':
                $value = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND confirmed = 1 AND id <> ?',
                    [guest_user()->id]);
                break;
            case 'enrolments':
                $value = $DB->count_records_select('user_enrolments', 'status = 0');
                break;
            case 'completions':
                $value = $DB->count_records_select('course_completions', 'timecompleted IS NOT NULL');
                break;
            case 'activities':
                $value = $DB->count_records_select('course_modules', 'deletioninprogress = 0 AND visible = 1');
                break;
            default:
                $value = 0;
        }
        $cache->set('metric_' . $metric, (int) $value);
        return (int) $value;
    }

    /**
     * Course cards.
     *
     * @param array $p
     * @return array
     */
    protected function context_courses(array $p): array {
        global $CFG;
        $courses = $this->fetch_courses($p);
        $cards = [];
        $enrolcache = \cache::make('theme_instracta', 'stats');
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $image = \core_course\external\course_summary_exporter::get_course_image($course);
            $enrolled = 0;
            if ($p['showenrolled']) {
                $enrolled = $enrolcache->get('enrolled_' . $course->id);
                if ($enrolled === false) {
                    $enrolled = count_enrolled_users($context);
                    $enrolcache->set('enrolled_' . $course->id, $enrolled);
                }
            }
            $summary = '';
            if ($p['showsummary'] && !empty($course->summary)) {
                $summary = shorten_text(trim(strip_tags(format_text($course->summary, $course->summaryformat,
                    ['context' => $context, 'noclean' => false]))), 130);
            }
            $cards[] = [
                'id' => (int) $course->id,
                'name' => format_string($course->fullname, true, ['context' => $context]),
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'image' => $image ?: '',
                'hasimage' => !empty($image),
                'pattern' => $image ? '' : $this->output->get_generated_image_for_id((int) $course->id),
                'summary' => $summary,
                'category' => $p['showcategory'] ? $this->category_name((int) $course->category) : '',
                'enrolled' => (int) $enrolled,
                'showenrolled' => $p['showenrolled'] && $enrolled > 0,
            ];
        }
        $p['cards'] = $cards;
        $p['hascards'] = !empty($cards);
        $p['hasbtn'] = $p['btntext'] !== '' && $p['btnurl'] !== '';
        $p['isgrid'] = $p['layout'] === 'grid';
        $p['iscarousel'] = $p['layout'] === 'carousel';
        return $p;
    }

    /**
     * Find the courses for a course section.
     *
     * @param array $p
     * @return \stdClass[]
     */
    protected function fetch_courses(array $p): array {
        global $DB;
        $limit = max(1, (int) $p['limit']);
        $courses = [];
        $canviewhidden = has_capability('moodle/course:viewhiddencourses', \context_system::instance());

        switch ($p['source']) {
            case 'featured':
                $ids = array_slice(array_map('intval', (array) $p['courses']), 0, $limit);
                if ($ids) {
                    $records = $DB->get_records_list('course', 'id', $ids);
                    foreach ($ids as $id) {
                        if (isset($records[$id]) && $records[$id]->id != SITEID && ($records[$id]->visible || $canviewhidden)) {
                            $courses[] = $records[$id];
                        }
                    }
                }
                break;
            case 'popular':
                $sql = "SELECT c.id, COUNT(ue.id) AS enrolments
                          FROM {course} c
                          JOIN {enrol} e ON e.courseid = c.id
                          JOIN {user_enrolments} ue ON ue.enrolid = e.id
                         WHERE c.visible = 1 AND c.id <> :site
                      GROUP BY c.id
                      ORDER BY enrolments DESC, c.id DESC";
                $ids = array_keys($DB->get_records_sql($sql, ['site' => SITEID], 0, $limit));
                if ($ids) {
                    $records = $DB->get_records_list('course', 'id', $ids);
                    foreach ($ids as $id) {
                        if (isset($records[$id])) {
                            $courses[] = $records[$id];
                        }
                    }
                }
                // Fall back to latest courses on a site with no enrolments yet.
                if (!$courses) {
                    $courses = $this->latest_courses($limit);
                }
                break;
            case 'category':
                try {
                    $category = \core_course_category::get((int) $p['category'], IGNORE_MISSING);
                    if ($category) {
                        foreach ($category->get_courses(['recursive' => true, 'limit' => $limit,
                            'sort' => ['timecreated' => -1]]) as $listed) {
                            $courses[] = get_course($listed->id);
                        }
                    }
                } catch (\Exception $e) {
                    $courses = [];
                }
                break;
            default:
                $courses = $this->latest_courses($limit);
        }
        return $courses;
    }

    /**
     * Most recently created visible courses.
     *
     * @param int $limit
     * @return \stdClass[]
     */
    protected function latest_courses(int $limit): array {
        global $DB;
        return array_values($DB->get_records_select('course', 'id <> ? AND visible = 1', [SITEID], 'timecreated DESC, id DESC',
            '*', 0, $limit));
    }

    /**
     * Category name by id, with a per-request cache.
     *
     * @param int $id
     * @return string
     */
    protected function category_name(int $id): string {
        if (!isset($this->categorynames[$id])) {
            $category = \core_course_category::get($id, IGNORE_MISSING);
            $this->categorynames[$id] = $category ? $category->get_formatted_name() : '';
        }
        return $this->categorynames[$id];
    }

    /**
     * Category tiles.
     *
     * @param array $p
     * @return array
     */
    protected function context_categories(array $p): array {
        $tiles = [];
        try {
            $parent = \core_course_category::get((int) $p['parent'], IGNORE_MISSING);
            $icons = ['book-open', 'laptop-code', 'flask', 'palette', 'briefcase', 'globe', 'brain', 'seedling'];
            if ($parent) {
                $i = 0;
                foreach ($parent->get_children(['limit' => (int) $p['limit']]) as $child) {
                    $tiles[] = [
                        'name' => $child->get_formatted_name(),
                        'url' => (new \moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false),
                        'count' => $child->get_courses_count(['recursive' => true]),
                        'showcount' => (bool) $p['showcount'],
                        'icon' => $icons[$i % count($icons)],
                    ];
                    $i++;
                }
            }
        } catch (\Exception $e) {
            $tiles = [];
        }
        $p['tiles'] = $tiles;
        $p['hastiles'] = !empty($tiles);
        return $p;
    }

    /**
     * Site announcements from the front page news forum.
     *
     * @param array $p
     * @return array
     */
    protected function context_announcements(array $p): array {
        global $DB, $CFG;
        $posts = [];
        $forumurl = '';
        try {
            $forum = $DB->get_record('forum', ['course' => SITEID, 'type' => 'news'], 'id', IGNORE_MULTIPLE);
            if ($forum) {
                $forumurl = (new \moodle_url('/mod/forum/view.php', ['f' => $forum->id]))->out(false);
                $sql = "SELECT d.id, d.name, d.timemodified, fp.message, fp.messageformat
                          FROM {forum_discussions} d
                          JOIN {forum_posts} fp ON fp.id = d.firstpost
                         WHERE d.forum = :forum AND fp.deleted = 0 AND d.timestart <= :now1
                               AND (d.timeend = 0 OR d.timeend > :now2)
                      ORDER BY d.timemodified DESC";
                $now = time();
                foreach ($DB->get_records_sql($sql, ['forum' => $forum->id, 'now1' => $now, 'now2' => $now], 0,
                    (int) $p['limit']) as $row) {
                    $posts[] = [
                        'title' => format_string($row->name),
                        'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $row->id]))->out(false),
                        'excerpt' => shorten_text(trim(strip_tags(format_text($row->message, $row->messageformat,
                            ['context' => \context_course::instance(SITEID)]))), 150),
                        'date' => userdate($row->timemodified, get_string('strftimedate', 'langconfig')),
                        'showdate' => (bool) $p['showdate'],
                    ];
                }
            }
        } catch (\Exception $e) {
            $posts = [];
        }
        $p['posts'] = $posts;
        $p['hasposts'] = !empty($posts);
        $p['forumurl'] = $forumurl;
        $p['hasforum'] = $forumurl !== '';
        return $p;
    }

    /**
     * Testimonials.
     *
     * @param array $p
     * @return array
     */
    protected function context_testimonials(array $p): array {
        foreach ($p['items'] as &$item) {
            $item['hasavatar'] = $item['avatar'] !== '';
            $item['initial'] = $item['name'] !== '' ? \core_text::strtoupper(\core_text::substr($item['name'], 0, 1)) : '';
        }
        return $p;
    }

    /**
     * Logos: items link only when a URL is set.
     *
     * @param array $p
     * @return array
     */
    protected function context_logos(array $p): array {
        $p['items'] = array_values(array_filter($p['items'], function ($item) {
            return $item['image'] !== '';
        }));
        foreach ($p['items'] as &$item) {
            $item['haslink'] = $item['url'] !== '';
        }
        $p['hasitems'] = !empty($p['items']);
        return $p;
    }

    /**
     * Team members.
     *
     * @param array $p
     * @return array
     */
    protected function context_team(array $p): array {
        foreach ($p['items'] as &$item) {
            $item['hasphoto'] = $item['photo'] !== '';
            $item['initial'] = $item['name'] !== '' ? \core_text::strtoupper(\core_text::substr($item['name'], 0, 1)) : '';
        }
        return $p;
    }

    /**
     * Gallery: items link only when a URL is set; items without images are skipped.
     *
     * @param array $p
     * @return array
     */
    protected function context_gallery(array $p): array {
        $p['items'] = array_values(array_filter($p['items'], function ($item) {
            return $item['image'] !== '';
        }));
        foreach ($p['items'] as &$item) {
            $item['haslink'] = $item['url'] !== '';
        }
        $p['hasitems'] = !empty($p['items']);
        return $p;
    }

    /**
     * FAQ: the first question can start open.
     *
     * @param array $p
     * @return array
     */
    protected function context_faq(array $p): array {
        foreach ($p['items'] as &$item) {
            $item['open'] = !empty($p['openfirst']) && $item['index'] === 0;
        }
        return $p;
    }

    /**
     * Video: turn a YouTube, Vimeo or file URL into something embeddable.
     *
     * @param array $p
     * @return array
     */
    protected function context_video(array $p): array {
        $url = $p['url'];
        $embed = '';
        $isfile = false;
        if (preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
        } else if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            $embed = 'https://player.vimeo.com/video/' . $m[1];
        } else if (preg_match('#\.(mp4|webm|ogg)(\?.*)?$#i', $url)) {
            $embed = $url;
            $isfile = true;
        }
        $p['embed'] = $embed;
        $p['hasvideo'] = $embed !== '';
        $p['isfile'] = $isfile;
        $p['isembed'] = $embed !== '' && !$isfile;
        $p['hasposter'] = $p['poster'] !== '';
        // With a poster, the third party player only loads when the visitor presses play.
        $p['deferred'] = $p['isembed'] && $p['hasposter'];
        $p['direct'] = $p['isembed'] && !$p['hasposter'];
        return $p;
    }

    /**
     * Spacer.
     *
     * @param array $p
     * @return array
     */
    protected function context_spacer(array $p): array {
        $p['height'] = (int) $p['height'];
        return $p;
    }
}
