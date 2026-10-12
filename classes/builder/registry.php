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
 * Section registry for the home page builder.
 *
 * Each section type is described by data: its fields, their types and defaults. The same
 * description drives the builder inspector (JavaScript), the server-side sanitiser and the
 * renderer, so adding a section type means adding one entry here plus one Mustache template.
 *
 * Field types: text, textarea, html, url, image, select, number, range, color, toggle, icon,
 * courses, category and list (a repeater, with its own "fields").
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {

    /** @var int Maximum items in a repeater field. */
    const MAX_ITEMS = 24;

    /** @var int Maximum sections on one page. */
    const MAX_SECTIONS = 60;

    /**
     * Icons offered by the icon picker (Font Awesome 6 free solid names).
     *
     * @return string[]
     */
    public static function icons(): array {
        return [
            'graduation-cap', 'book-open', 'book', 'chalkboard-user', 'laptop-code', 'laptop', 'users', 'user-graduate',
            'award', 'certificate', 'trophy', 'medal', 'star', 'heart', 'bolt', 'rocket', 'lightbulb', 'brain',
            'chart-line', 'chart-pie', 'chart-simple', 'bullseye', 'compass', 'map', 'globe', 'earth-africa', 'language',
            'comments', 'message', 'envelope', 'phone', 'calendar-days', 'clock', 'hourglass-half', 'circle-check',
            'shield-halved', 'lock', 'key', 'gear', 'wrench', 'puzzle-piece', 'cubes', 'layer-group', 'database',
            'cloud', 'wifi', 'mobile-screen', 'video', 'play', 'microphone', 'headphones', 'camera', 'palette', 'pen-nib',
            'pencil', 'file-lines', 'clipboard-check', 'list-check', 'flask', 'microscope', 'atom', 'seedling', 'leaf',
            'handshake', 'building-columns', 'briefcase', 'money-bill-wave', 'coins', 'scale-balanced', 'gavel',
            'hand-holding-heart', 'people-group', 'universal-access', 'infinity', 'magnifying-glass', 'tags', 'flag',
        ];
    }

    /**
     * Fields every section shares (stored under "style").
     *
     * @return array
     */
    public static function style_fields(): array {
        return [
            self::sel('bg', 'none', ['none', 'soft', 'primary', 'gradient', 'dark', 'color', 'image'], 'style'),
            self::color('bgcolor', '', 'style', ['bg' => ['color']]),
            self::image('bgimage', '', 'style', ['bg' => ['image']]),
            self::range('overlay', 55, 0, 90, 5, 'style', ['bg' => ['image']]),
            self::sel('width', 'contained', ['contained', 'wide', 'full'], 'style'),
            self::sel('padtop', 'lg', ['none', 'sm', 'md', 'lg', 'xl'], 'style'),
            self::sel('padbottom', 'lg', ['none', 'sm', 'md', 'lg', 'xl'], 'style'),
            self::sel('audience', 'all', ['all', 'guest', 'loggedin'], 'advanced'),
            self::toggle('hidemobile', 0, 'advanced'),
            self::toggle('hidedesktop', 0, 'advanced'),
            self::sel('animation', 'up', ['none', 'fade', 'up', 'zoom'], 'advanced'),
            self::text('anchor', '', 'advanced'),
            self::text('cssclass', '', 'advanced'),
        ];
    }

    /**
     * All section types, keyed by type.
     *
     * @return array
     */
    public static function sections(): array {
        $align = self::sel('align', 'center', ['left', 'center'], 'content');
        $eyebrow = self::text('eyebrow', '', 'content');
        $title = self::text('title', '', 'content');
        $subtitle = self::textarea('subtitle', '', 'content');

        return [
            'hero' => [
                'category' => 'hero', 'icon' => 'rocket', 'style' => ['bg' => 'gradient', 'padtop' => 'xl', 'padbottom' => 'xl'],
                'fields' => [
                    self::sel('layout', 'split', ['center', 'left', 'split'], 'content'),
                    self::text('eyebrow', 'Learning that fits your life', 'content'),
                    self::text('title', 'Learn without limits', 'content'),
                    self::textarea('subtitle', 'Flexible, high quality courses you can take from anywhere, on any device, at your own pace.', 'content'),
                    self::text('btn1text', 'Browse courses', 'content'),
                    self::url('btn1url', '/course/index.php', 'content'),
                    self::text('btn2text', 'Log in', 'content'),
                    self::url('btn2url', '/login/index.php', 'content'),
                    self::image('image', '', 'content', ['layout' => ['split']]),
                    self::toggle('showsearch', 1, 'content'),
                    self::sel('height', 'tall', ['auto', 'tall', 'screen'], 'content'),
                    self::lst('bullets', [['text' => 'Self-paced learning'], ['text' => 'Certificates on completion']],
                        [self::text('text', '', 'content')], 'content'),
                ],
            ],
            'intro' => [
                'category' => 'content', 'icon' => 'heading', 'style' => [],
                'fields' => [$eyebrow, self::text('title', 'A better way to learn', 'content'), $subtitle, $align],
            ],
            'features' => [
                'category' => 'content', 'icon' => 'cubes', 'style' => [],
                'fields' => [
                    self::text('eyebrow', 'Why learn with us', 'content'),
                    self::text('title', 'Everything you need to succeed', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::sel('columns', '3', ['2', '3', '4'], 'content'),
                    self::sel('cardstyle', 'card', ['card', 'flat', 'outline'], 'content'),
                    self::lst('items', [
                        ['icon' => 'laptop-code', 'title' => 'Learn anywhere', 'text' => 'Study on your phone, tablet or laptop, online or offline.', 'url' => ''],
                        ['icon' => 'chalkboard-user', 'title' => 'Expert tutors', 'text' => 'Courses designed and supported by experienced educators.', 'url' => ''],
                        ['icon' => 'award', 'title' => 'Recognised awards', 'text' => 'Earn certificates and badges that prove what you know.', 'url' => ''],
                    ], [
                        self::icon('icon', 'star', 'content'),
                        self::text('title', '', 'content'),
                        self::textarea('text', '', 'content'),
                        self::url('url', '', 'content'),
                    ], 'content'),
                ],
            ],
            'stats' => [
                'category' => 'content', 'icon' => 'chart-simple', 'style' => ['bg' => 'soft'],
                'fields' => [
                    self::text('title', '', 'content'),
                    self::sel('look', 'cards', ['plain', 'cards'], 'content'),
                    self::toggle('animate', 1, 'content'),
                    self::lst('items', [
                        ['metric' => 'courses', 'value' => '', 'prefix' => '', 'suffix' => '+', 'label' => 'Courses', 'icon' => 'book-open'],
                        ['metric' => 'users', 'value' => '', 'prefix' => '', 'suffix' => '+', 'label' => 'Learners', 'icon' => 'users'],
                        ['metric' => 'completions', 'value' => '', 'prefix' => '', 'suffix' => '+', 'label' => 'Completions', 'icon' => 'award'],
                        ['metric' => 'custom', 'value' => '98', 'prefix' => '', 'suffix' => '%', 'label' => 'Satisfaction', 'icon' => 'heart'],
                    ], [
                        self::sel('metric', 'custom', ['courses', 'users', 'enrolments', 'completions', 'activities', 'custom'], 'content'),
                        self::text('value', '', 'content', ['metric' => ['custom']]),
                        self::text('prefix', '', 'content'),
                        self::text('suffix', '', 'content'),
                        self::text('label', '', 'content'),
                        self::icon('icon', 'star', 'content'),
                    ], 'content'),
                ],
            ],
            'courses' => [
                'category' => 'learning', 'icon' => 'book-open', 'style' => [],
                'fields' => [
                    self::text('eyebrow', 'Popular right now', 'content'),
                    self::text('title', 'Featured courses', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::sel('source', 'latest', ['featured', 'latest', 'popular', 'category'], 'content'),
                    self::courses('courses', [], 'content', ['source' => ['featured']]),
                    self::category('category', 0, 'content', ['source' => ['category']]),
                    self::number('limit', 6, 1, 24, 'content'),
                    self::sel('layout', 'grid', ['grid', 'carousel'], 'content'),
                    self::sel('columns', '3', ['2', '3', '4'], 'content', ['layout' => ['grid']]),
                    self::toggle('showsummary', 1, 'content'),
                    self::toggle('showcategory', 1, 'content'),
                    self::toggle('showenrolled', 1, 'content'),
                    self::text('btntext', 'View all courses', 'content'),
                    self::url('btnurl', '/course/index.php', 'content'),
                ],
            ],
            'categories' => [
                'category' => 'learning', 'icon' => 'layer-group', 'style' => ['bg' => 'soft'],
                'fields' => [
                    self::text('eyebrow', 'Explore', 'content'),
                    self::text('title', 'Browse by category', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::category('parent', 0, 'content'),
                    self::number('limit', 8, 1, 24, 'content'),
                    self::sel('columns', '4', ['2', '3', '4'], 'content'),
                    self::toggle('showcount', 1, 'content'),
                ],
            ],
            'announcements' => [
                'category' => 'learning', 'icon' => 'bullhorn', 'style' => [],
                'fields' => [
                    self::text('eyebrow', 'Stay in the loop', 'content'),
                    self::text('title', 'Latest announcements', 'content'),
                    self::number('limit', 3, 1, 12, 'content'),
                    self::toggle('showdate', 1, 'content'),
                ],
            ],
            'split' => [
                'category' => 'content', 'icon' => 'table-columns', 'style' => [],
                'fields' => [
                    self::text('eyebrow', '', 'content'),
                    self::text('title', 'Learning designed around you', 'content'),
                    self::html('text', '<p>Our courses combine short videos, hands-on activities and community discussion, so you stay motivated and keep moving forward.</p>', 'content'),
                    self::image('image', '', 'content'),
                    self::sel('imageside', 'right', ['left', 'right'], 'content'),
                    self::sel('imagestyle', 'rounded', ['rounded', 'shadow', 'plain'], 'content'),
                    self::lst('bullets', [['text' => 'Short, focused lessons'], ['text' => 'Practice with real projects']],
                        [self::text('text', '', 'content')], 'content'),
                    self::text('btntext', '', 'content'),
                    self::url('btnurl', '', 'content'),
                ],
            ],
            'steps' => [
                'category' => 'content', 'icon' => 'list-check', 'style' => ['bg' => 'soft'],
                'fields' => [
                    self::text('eyebrow', 'How it works', 'content'),
                    self::text('title', 'Start learning in three steps', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::sel('look', 'numbered', ['numbered', 'icons'], 'content'),
                    self::lst('items', [
                        ['icon' => 'user-graduate', 'title' => 'Create your account', 'text' => 'Sign up in under a minute.'],
                        ['icon' => 'magnifying-glass', 'title' => 'Pick a course', 'text' => 'Browse the catalogue and enrol.'],
                        ['icon' => 'trophy', 'title' => 'Earn your certificate', 'text' => 'Complete the course and share your achievement.'],
                    ], [
                        self::icon('icon', 'star', 'content', ['look' => ['icons']]),
                        self::text('title', '', 'content'),
                        self::textarea('text', '', 'content'),
                    ], 'content'),
                ],
            ],
            'testimonials' => [
                'category' => 'social', 'icon' => 'quote-left', 'style' => [],
                'fields' => [
                    self::text('eyebrow', 'Stories', 'content'),
                    self::text('title', 'Loved by learners', 'content'),
                    self::sel('layout', 'grid', ['grid', 'slider'], 'content'),
                    self::lst('items', [
                        ['quote' => 'The flexibility let me study around my job, and I finished with a qualification I am proud of.', 'name' => 'Amina W.', 'role' => 'Graduate', 'avatar' => ''],
                        ['quote' => 'Clear lessons, helpful tutors and a platform that just works on my phone.', 'name' => 'Brian O.', 'role' => 'Student', 'avatar' => ''],
                        ['quote' => 'I could apply what I learned at work the very next day.', 'name' => 'Grace M.', 'role' => 'Professional', 'avatar' => ''],
                    ], [
                        self::textarea('quote', '', 'content'),
                        self::text('name', '', 'content'),
                        self::text('role', '', 'content'),
                        self::image('avatar', '', 'content'),
                    ], 'content'),
                ],
            ],
            'logos' => [
                'category' => 'social', 'icon' => 'building-columns', 'style' => ['padtop' => 'md', 'padbottom' => 'md'],
                'fields' => [
                    self::text('title', 'Trusted by leading organisations', 'content'),
                    self::toggle('grayscale', 1, 'content'),
                    self::lst('items', [], [
                        self::image('image', '', 'content'),
                        self::text('name', '', 'content'),
                        self::url('url', '', 'content'),
                    ], 'content'),
                ],
            ],
            'team' => [
                'category' => 'social', 'icon' => 'people-group', 'style' => [],
                'fields' => [
                    self::text('eyebrow', 'Our people', 'content'),
                    self::text('title', 'Meet the team', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::sel('columns', '4', ['2', '3', '4'], 'content'),
                    self::lst('items', [], [
                        self::image('photo', '', 'content'),
                        self::text('name', '', 'content'),
                        self::text('role', '', 'content'),
                        self::textarea('bio', '', 'content'),
                    ], 'content'),
                ],
            ],
            'faq' => [
                'category' => 'content', 'icon' => 'circle-question', 'style' => [],
                'fields' => [
                    self::text('eyebrow', 'FAQ', 'content'),
                    self::text('title', 'Frequently asked questions', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::toggle('openfirst', 1, 'content'),
                    self::lst('items', [
                        ['question' => 'How do I enrol in a course?', 'answer' => 'Create an account, open the course page and choose Enrol me. Some courses need an enrolment key.'],
                        ['question' => 'Can I study on my phone?', 'answer' => 'Yes. The site works on any modern phone, tablet or computer.'],
                        ['question' => 'Will I get a certificate?', 'answer' => 'Courses that offer certificates award them automatically when you complete all requirements.'],
                    ], [
                        self::text('question', '', 'content'),
                        self::textarea('answer', '', 'content'),
                    ], 'content'),
                ],
            ],
            'cta' => [
                'category' => 'hero', 'icon' => 'bullhorn', 'style' => ['bg' => 'gradient'],
                'fields' => [
                    self::text('title', 'Ready to start learning?', 'content'),
                    self::textarea('text', 'Join thousands of learners and take the next step today.', 'content'),
                    self::text('btn1text', 'Get started', 'content'),
                    self::url('btn1url', '/login/signup.php', 'content'),
                    self::text('btn2text', '', 'content'),
                    self::url('btn2url', '', 'content'),
                    $align,
                ],
            ],
            'video' => [
                'category' => 'media', 'icon' => 'circle-play', 'style' => [],
                'fields' => [
                    self::text('eyebrow', '', 'content'),
                    self::text('title', 'See it in action', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::url('url', '', 'content'),
                    self::image('poster', '', 'content'),
                    self::sel('ratio', '16x9', ['16x9', '4x3', '21x9'], 'content'),
                ],
            ],
            'gallery' => [
                'category' => 'media', 'icon' => 'images', 'style' => [],
                'fields' => [
                    self::text('title', 'Gallery', 'content'),
                    self::textarea('subtitle', '', 'content'),
                    self::sel('columns', '3', ['2', '3', '4'], 'content'),
                    self::sel('look', 'grid', ['grid', 'masonry'], 'content'),
                    self::lst('items', [], [
                        self::image('image', '', 'content'),
                        self::text('caption', '', 'content'),
                        self::url('url', '', 'content'),
                    ], 'content'),
                ],
            ],
            'richtext' => [
                'category' => 'content', 'icon' => 'align-left', 'style' => [],
                'fields' => [
                    self::html('html', '<h2>Heading</h2><p>Write anything here. Basic formatting, links and images are supported.</p>', 'content'),
                    self::sel('align', 'left', ['left', 'center'], 'content'),
                    self::sel('maxwidth', 'normal', ['narrow', 'normal', 'wide'], 'content'),
                ],
            ],
            'html' => [
                'category' => 'advanced', 'icon' => 'code', 'style' => [],
                'fields' => [
                    self::html('html', '<div class="text-center">Custom HTML</div>', 'content'),
                ],
            ],
            'moodlecontent' => [
                'category' => 'learning', 'icon' => 'house', 'style' => ['padtop' => 'md', 'padbottom' => 'md'],
                'fields' => [],
            ],
            'spacer' => [
                'category' => 'advanced', 'icon' => 'arrows-up-down', 'style' => ['padtop' => 'none', 'padbottom' => 'none'],
                'fields' => [
                    self::range('height', 40, 0, 240, 4, 'content'),
                    self::toggle('divider', 0, 'content'),
                ],
            ],
        ];
    }

    /**
     * Definition of one section type.
     *
     * @param string $type
     * @return array|null
     */
    public static function get(string $type): ?array {
        $all = self::sections();
        return $all[$type] ?? null;
    }

    /**
     * Default property values for a type.
     *
     * @param string $type
     * @return array
     */
    public static function defaults(string $type): array {
        $def = self::get($type);
        return $def ? self::field_defaults($def['fields']) : [];
    }

    /**
     * Default style values for a type (global defaults overlaid by the type's own).
     *
     * @param string $type
     * @return array
     */
    public static function style_defaults(string $type): array {
        $style = self::field_defaults(self::style_fields());
        $def = self::get($type);
        return $def ? array_merge($style, $def['style']) : $style;
    }

    /**
     * Collect defaults from field definitions.
     *
     * @param array $fields
     * @return array
     */
    protected static function field_defaults(array $fields): array {
        $out = [];
        foreach ($fields as $field) {
            $out[$field['key']] = $field['default'];
        }
        return $out;
    }

    /**
     * Build a fresh section of a type.
     *
     * @param string $type
     * @param array $props property overrides
     * @param array $style style overrides
     * @return array
     */
    public static function new_section(string $type, array $props = [], array $style = []): array {
        return [
            'id' => self::new_id(),
            'type' => $type,
            'label' => '',
            'hidden' => 0,
            'props' => array_merge(self::defaults($type), $props),
            'style' => array_merge(self::style_defaults($type), $style),
        ];
    }

    /**
     * A short random section id.
     *
     * @return string
     */
    public static function new_id(): string {
        return 's' . substr(bin2hex(random_bytes(6)), 0, 10);
    }

    /**
     * Everything the builder JavaScript needs to know about the section types, with localised labels.
     *
     * @return array
     */
    public static function schema_for_js(): array {
        $sm = get_string_manager();
        $label = function (string $id, string $fallback) use ($sm): string {
            return $sm->string_exists($id, 'theme_instracta') ? get_string($id, 'theme_instracta') : $fallback;
        };
        $decorate = function (array $fields) use (&$decorate, $label): array {
            foreach ($fields as &$field) {
                $field['label'] = $label('f_' . $field['key'], $field['key']);
                if (!empty($field['options'])) {
                    $opts = [];
                    foreach ($field['options'] as $value) {
                        $opts[] = ['value' => (string) $value, 'label' => $label('o_' . $value, (string) $value)];
                    }
                    $field['options'] = $opts;
                }
                if (!empty($field['fields'])) {
                    $field['fields'] = $decorate($field['fields']);
                }
            }
            return $fields;
        };

        $types = [];
        foreach (self::sections() as $type => $def) {
            $types[$type] = [
                'type' => $type,
                'label' => $label('sec_' . $type, $type),
                'description' => $label('sec_' . $type . '_desc', ''),
                'category' => $def['category'],
                'icon' => $def['icon'],
                'fields' => $decorate($def['fields']),
                'defaults' => self::field_defaults($def['fields']),
                'styledefaults' => self::style_defaults($type),
            ];
        }
        $categories = [];
        foreach (['hero', 'content', 'learning', 'social', 'media', 'advanced'] as $cat) {
            $categories[] = ['key' => $cat, 'label' => $label('cat_' . $cat, $cat)];
        }
        return [
            'types' => $types,
            'categories' => $categories,
            'stylefields' => $decorate(self::style_fields()),
            'icons' => self::icons(),
            'maxitems' => self::MAX_ITEMS,
        ];
    }

    // Sanitising.

    /**
     * Sanitise a whole layout received from the browser.
     *
     * @param mixed $layout decoded JSON
     * @return array a clean layout: ['v' => 1, 'sections' => [...]]
     */
    public static function sanitize_layout($layout): array {
        $clean = ['v' => 1, 'sections' => []];
        if (!is_array($layout) || empty($layout['sections']) || !is_array($layout['sections'])) {
            return $clean;
        }
        $seen = [];
        foreach (array_slice($layout['sections'], 0, self::MAX_SECTIONS) as $section) {
            if (!is_array($section) || empty($section['type']) || !is_string($section['type'])) {
                continue;
            }
            $def = self::get($section['type']);
            if (!$def) {
                continue;
            }
            $id = isset($section['id']) && is_string($section['id']) && preg_match('/^[a-z0-9_-]{4,40}$/i', $section['id'])
                ? $section['id'] : self::new_id();
            if (isset($seen[$id])) {
                $id = self::new_id();
            }
            $seen[$id] = true;
            $clean['sections'][] = [
                'id' => $id,
                'type' => $section['type'],
                'label' => isset($section['label']) && is_string($section['label'])
                    ? \core_text::substr(clean_param($section['label'], PARAM_TEXT), 0, 80) : '',
                'hidden' => empty($section['hidden']) ? 0 : 1,
                'props' => self::sanitize_fields($def['fields'], $section['props'] ?? []),
                'style' => self::sanitize_fields(self::style_fields(), $section['style'] ?? [], self::style_defaults($section['type'])),
            ];
        }
        return $clean;
    }

    /**
     * Sanitise values against field definitions. Unknown keys are dropped.
     *
     * @param array $fields field definitions
     * @param mixed $values raw values
     * @param array|null $defaults optional defaults overriding the field defaults
     * @return array
     */
    public static function sanitize_fields(array $fields, $values, ?array $defaults = null): array {
        $values = is_array($values) ? $values : [];
        $out = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $default = $defaults[$key] ?? $field['default'];
            $out[$key] = array_key_exists($key, $values) ? self::sanitize_value($field, $values[$key], $default) : $default;
        }
        return $out;
    }

    /**
     * Sanitise a single value.
     *
     * @param array $field
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    protected static function sanitize_value(array $field, $value, $default) {
        switch ($field['type']) {
            case 'text':
                return is_scalar($value) ? \core_text::substr(trim(clean_param((string) $value, PARAM_TEXT)), 0, 300) : $default;
            case 'textarea':
                return is_scalar($value) ? \core_text::substr(trim(clean_param((string) $value, PARAM_TEXT)), 0, 3000) : $default;
            case 'html':
                return is_scalar($value) ? \core_text::substr(clean_text((string) $value, FORMAT_HTML), 0, 60000) : $default;
            case 'url':
            case 'image':
                return self::sanitize_url($value);
            case 'select':
                $allowed = array_map('strval', $field['options']);
                return is_scalar($value) && in_array((string) $value, $allowed, true) ? (string) $value : $default;
            case 'number':
            case 'range':
                if (!is_numeric($value)) {
                    return $default;
                }
                return max($field['min'], min($field['max'], (int) $value));
            case 'color':
                return is_string($value) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value) ? $value : '';
            case 'toggle':
                return empty($value) ? 0 : 1;
            case 'icon':
                return is_string($value) && preg_match('/^[a-z0-9-]{1,40}$/', $value) ? $value : $default;
            case 'category':
                return is_numeric($value) ? max(0, (int) $value) : 0;
            case 'courses':
                if (!is_array($value)) {
                    return [];
                }
                $ids = [];
                foreach (array_slice($value, 0, self::MAX_ITEMS) as $id) {
                    if (is_numeric($id) && (int) $id > 0) {
                        $ids[] = (int) $id;
                    }
                }
                return array_values(array_unique($ids));
            case 'list':
                if (!is_array($value)) {
                    return $default;
                }
                $items = [];
                foreach (array_slice(array_values($value), 0, self::MAX_ITEMS) as $item) {
                    if (is_array($item)) {
                        $items[] = self::sanitize_fields($field['fields'], $item);
                    }
                }
                return $items;
        }
        return $default;
    }

    /**
     * Allow relative paths, anchors and http(s), mailto and tel links only.
     *
     * @param mixed $value
     * @return string
     */
    public static function sanitize_url($value): string {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x1f\x7f"\'<>\\\\]/', $value)) {
            return '';
        }
        if (preg_match('#^(/(?!/)|\#|\?)#', $value) || preg_match('#^(https?://|mailto:|tel:)#i', $value)) {
            return \core_text::substr($value, 0, 1000);
        }
        return '';
    }

    // Field constructors.

    /**
     * Common field shape.
     *
     * @param string $key
     * @param string $type
     * @param mixed $default
     * @param string $group
     * @param array $show conditional display: other field key => values
     * @param array $extra
     * @return array
     */
    protected static function field(string $key, string $type, $default, string $group, array $show = [], array $extra = []): array {
        return array_merge(['key' => $key, 'type' => $type, 'default' => $default, 'group' => $group, 'show' => $show], $extra);
    }

    /**
     * Single line text.
     */
    protected static function text(string $key, string $default = '', string $group = 'content', array $show = []): array {
        return self::field($key, 'text', $default, $group, $show);
    }

    /**
     * Multi-line plain text.
     */
    protected static function textarea(string $key, string $default = '', string $group = 'content', array $show = []): array {
        return self::field($key, 'textarea', $default, $group, $show);
    }

    /**
     * Rich HTML, cleaned on save.
     */
    protected static function html(string $key, string $default = '', string $group = 'content', array $show = []): array {
        return self::field($key, 'html', $default, $group, $show);
    }

    /**
     * URL.
     */
    protected static function url(string $key, string $default = '', string $group = 'content', array $show = []): array {
        return self::field($key, 'url', $default, $group, $show);
    }

    /**
     * Image (media library).
     */
    protected static function image(string $key, string $default = '', string $group = 'content', array $show = []): array {
        return self::field($key, 'image', $default, $group, $show);
    }

    /**
     * Colour.
     */
    protected static function color(string $key, string $default = '', string $group = 'style', array $show = []): array {
        return self::field($key, 'color', $default, $group, $show);
    }

    /**
     * Toggle.
     */
    protected static function toggle(string $key, int $default = 0, string $group = 'content', array $show = []): array {
        return self::field($key, 'toggle', $default, $group, $show);
    }

    /**
     * Select from fixed options.
     */
    protected static function sel(string $key, string $default, array $options, string $group = 'content', array $show = []): array {
        return self::field($key, 'select', $default, $group, $show, ['options' => $options]);
    }

    /**
     * Whole number between bounds.
     */
    protected static function number(string $key, int $default, int $min, int $max, string $group = 'content', array $show = []): array {
        return self::field($key, 'number', $default, $group, $show, ['min' => $min, 'max' => $max, 'step' => 1]);
    }

    /**
     * Slider.
     */
    protected static function range(string $key, int $default, int $min, int $max, int $step, string $group = 'content', array $show = []): array {
        return self::field($key, 'range', $default, $group, $show, ['min' => $min, 'max' => $max, 'step' => $step]);
    }

    /**
     * Icon picker.
     */
    protected static function icon(string $key, string $default = 'star', string $group = 'content', array $show = []): array {
        return self::field($key, 'icon', $default, $group, $show);
    }

    /**
     * Course picker (list of course ids).
     */
    protected static function courses(string $key, array $default = [], string $group = 'content', array $show = []): array {
        return self::field($key, 'courses', $default, $group, $show);
    }

    /**
     * Course category picker.
     */
    protected static function category(string $key, int $default = 0, string $group = 'content', array $show = []): array {
        return self::field($key, 'category', $default, $group, $show);
    }

    /**
     * Repeater of items.
     */
    protected static function lst(string $key, array $default, array $fields, string $group = 'content', array $show = []): array {
        return self::field($key, 'list', $default, $group, $show, ['fields' => $fields]);
    }
}
