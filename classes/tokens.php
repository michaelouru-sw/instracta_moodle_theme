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
 * Design tokens: palettes, fonts, radius and shadow presets, and their SCSS output.
 *
 * Everything the admin can pick on the "Brand and style" settings tab is resolved here, so the
 * SCSS pre-code, the CSS custom properties and the builder preview all agree on the same values.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tokens {

    /**
     * Colour palettes. Each has a primary, an accent and the two ends of the signature gradient.
     *
     * @return array
     */
    public static function palettes(): array {
        return [
            'instracta' => ['primary' => '#052853', 'accent' => '#E8A44D', 'grada' => '#052853', 'gradb' => '#0E4A8A'],
            'ocean' => ['primary' => '#2563eb', 'accent' => '#06b6d4', 'grada' => '#2563eb', 'gradb' => '#06b6d4'],
            'aurora' => ['primary' => '#7c3aed', 'accent' => '#ec4899', 'grada' => '#7c3aed', 'gradb' => '#ec4899'],
            'sunset' => ['primary' => '#ea580c', 'accent' => '#e11d48', 'grada' => '#f59e0b', 'gradb' => '#e11d48'],
            'forest' => ['primary' => '#15803d', 'accent' => '#0d9488', 'grada' => '#16a34a', 'gradb' => '#0d9488'],
            'royal' => ['primary' => '#4338ca', 'accent' => '#d4a017', 'grada' => '#312e81', 'gradb' => '#4f46e5'],
            'rose' => ['primary' => '#be185d', 'accent' => '#f97316', 'grada' => '#be185d', 'gradb' => '#f97316'],
            'graphite' => ['primary' => '#1f2937', 'accent' => '#0ea5e9', 'grada' => '#111827', 'gradb' => '#374151'],
        ];
    }

    /**
     * Font stacks. The first four are bundled in the theme (fonts/ directory), so no third party
     * request is made when they are used.
     *
     * @return array key => [label, css family, bundled file or null, weight range]
     */
    public static function fonts(): array {
        $fallback = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
        return [
            'inter' => ['Inter', '"Instracta Inter", ' . $fallback, 'inter.woff2', '100 900'],
            'jakarta' => ['Plus Jakarta Sans', '"Instracta Jakarta", ' . $fallback, 'jakarta.woff2', '200 800'],
            'manrope' => ['Manrope', '"Instracta Manrope", ' . $fallback, 'manrope.woff2', '200 800'],
            'sora' => ['Sora', '"Instracta Sora", ' . $fallback, 'sora.woff2', '100 800'],
            'atkinson' => ['Atkinson Hyperlegible (easiest to read)', '"Instracta Atkinson", ' . $fallback, 'atkinson.woff2', '200 800'],
            'sourceserif' => ['Source Serif (long reading)', '"Instracta Source Serif", Georgia, "Times New Roman", serif', 'sourceserif.woff2', '200 900'],
            'system' => ['System UI', $fallback, null, null],
            'serif' => ['Serif (Georgia)', 'Georgia, "Times New Roman", serif', null, null],
        ];
    }

    /**
     * Radius presets: sm, md, lg, xl and button radius, in rem.
     *
     * @return array
     */
    public static function radii(): array {
        return [
            'sharp' => ['sm' => '.125rem', 'md' => '.25rem', 'lg' => '.375rem', 'xl' => '.5rem', 'btn' => '.25rem'],
            'soft' => ['sm' => '.375rem', 'md' => '.625rem', 'lg' => '.875rem', 'xl' => '1.25rem', 'btn' => '.625rem'],
            'round' => ['sm' => '.5rem', 'md' => '.875rem', 'lg' => '1.25rem', 'xl' => '1.75rem', 'btn' => '.875rem'],
            'pill' => ['sm' => '.5rem', 'md' => '.875rem', 'lg' => '1.25rem', 'xl' => '1.75rem', 'btn' => '999px'],
        ];
    }

    /**
     * Shadow presets: resting and raised.
     *
     * @return array
     */
    public static function shadows(): array {
        return [
            'flat' => [
                'sm' => 'none',
                'md' => '0 0 0 1px rgba(15, 23, 42, .06)',
                'lg' => '0 0 0 1px rgba(15, 23, 42, .1)',
            ],
            'soft' => [
                'sm' => '0 1px 2px rgba(15, 23, 42, .06), 0 1px 3px rgba(15, 23, 42, .06)',
                'md' => '0 4px 12px -2px rgba(15, 23, 42, .08), 0 2px 4px -2px rgba(15, 23, 42, .06)',
                'lg' => '0 18px 40px -12px rgba(15, 23, 42, .18), 0 6px 12px -6px rgba(15, 23, 42, .08)',
            ],
            'dramatic' => [
                'sm' => '0 2px 6px rgba(15, 23, 42, .1)',
                'md' => '0 14px 30px -8px rgba(15, 23, 42, .2), 0 4px 8px -4px rgba(15, 23, 42, .1)',
                'lg' => '0 32px 64px -16px rgba(15, 23, 42, .35), 0 12px 20px -10px rgba(15, 23, 42, .15)',
            ],
        ];
    }

    /**
     * Read one theme setting, falling back to a default when unset or empty.
     *
     * @param \theme_config|null $theme
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function setting(?\theme_config $theme, string $name, $default = '') {
        if ($theme && isset($theme->settings->{$name}) && $theme->settings->{$name} !== '') {
            return $theme->settings->{$name};
        }
        $value = get_config('theme_instracta', $name);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }

    /**
     * Whether a string is a six or three digit hex colour.
     *
     * @param string $value
     * @return bool
     */
    public static function is_hex(string $value): bool {
        return (bool) preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value);
    }

    /**
     * Resolve the complete token set for a theme.
     *
     * @param \theme_config|null $theme
     * @return array
     */
    public static function resolve(?\theme_config $theme = null): array {
        $palettes = self::palettes();
        $key = (string) self::setting($theme, 'palette', 'instracta');
        $palette = $palettes[$key] ?? $palettes['instracta'];

        $primary = (string) self::setting($theme, 'primarycolor', '');
        $accent = (string) self::setting($theme, 'accentcolor', '');
        $grada = (string) self::setting($theme, 'gradientstart', '');
        $gradb = (string) self::setting($theme, 'gradientend', '');

        $primary = self::is_hex($primary) ? $primary : $palette['primary'];
        $accent = self::is_hex($accent) ? $accent : $palette['accent'];
        // A custom primary without a custom gradient gets a gradient built from primary and accent.
        $custom = self::is_hex((string) self::setting($theme, 'primarycolor', ''));
        $grada = self::is_hex($grada) ? $grada : ($custom ? $primary : $palette['grada']);
        $gradb = self::is_hex($gradb) ? $gradb : ($custom ? $accent : $palette['gradb']);

        $fonts = self::fonts();
        $body = (string) self::setting($theme, 'fontbody', 'inter');
        $heading = (string) self::setting($theme, 'fontheading', 'jakarta');
        $radii = self::radii();
        $radius = (string) self::setting($theme, 'radius', 'soft');
        $shadows = self::shadows();
        $shadow = (string) self::setting($theme, 'shadow', 'soft');

        $container = (string) self::setting($theme, 'containerwidth', '1280');

        return [
            'primary' => $primary,
            'accent' => $accent,
            'grada' => $grada,
            'gradb' => $gradb,
            'fontbody' => isset($fonts[$body]) ? $body : 'inter',
            'fontcontent' => isset($fonts[(string) self::setting($theme, 'readingfont', 'body')])
                ? (string) self::setting($theme, 'readingfont', 'body') : 'body',
            'fontheading' => isset($fonts[$heading]) ? $heading : 'jakarta',
            'radius' => $radii[$radius] ?? $radii['soft'],
            'shadow' => $shadows[$shadow] ?? $shadows['soft'],
            'container' => $container === 'fluid' ? '100%' : (clean_param($container, PARAM_INT) . 'px'),
            'navbarstyle' => (string) self::setting($theme, 'navbarstyle', 'glass'),
            'navbarsticky' => (int) self::setting($theme, 'navbarsticky', 1),
            'colormode' => (string) self::setting($theme, 'colormode', 'auto'),
            'colortoggle' => (int) self::setting($theme, 'colortoggle', 1),
            'readsize' => in_array((string) self::setting($theme, 'readingsize', '17'), ['16', '17', '18', '19'], true)
                ? (string) self::setting($theme, 'readingsize', '17') : '17',
            'readlh' => in_array((string) self::setting($theme, 'readinglh', '1.7'), ['1.6', '1.7', '1.8', '1.9'], true)
                ? (string) self::setting($theme, 'readinglh', '1.7') : '1.7',
            'measure' => in_array((string) self::setting($theme, 'readingmeasure', '68'), ['58', '68', '78', '0'], true)
                ? (string) self::setting($theme, 'readingmeasure', '68') : '68',
            'contentfont' => (string) self::setting($theme, 'readingfont', 'body'),
        ];
    }

    /**
     * SCSS variable declarations that must exist before Bootstrap is compiled.
     *
     * @param \theme_config|null $theme
     * @return string
     */
    public static function pre_scss(?\theme_config $theme = null): string {
        $t = self::resolve($theme);
        $fonts = self::fonts();
        $scss = '';
        $scss .= '$instracta-primary: ' . $t['primary'] . ";\n";
        $scss .= '$instracta-accent: ' . $t['accent'] . ";\n";
        $scss .= '$instracta-grad-a: ' . $t['grada'] . ";\n";
        $scss .= '$instracta-grad-b: ' . $t['gradb'] . ";\n";
        $scss .= '$instracta-font-body: ' . $fonts[$t['fontbody']][1] . ";\n";
        $scss .= '$instracta-font-heading: ' . $fonts[$t['fontheading']][1] . ";\n";
        foreach ($t['radius'] as $name => $value) {
            $scss .= '$instracta-r-' . $name . ': ' . $value . ";\n";
        }
        foreach ($t['shadow'] as $name => $value) {
            $scss .= '$instracta-shadow-' . $name . ': ' . $value . ";\n";
        }
        $scss .= '$instracta-container: ' . $t['container'] . ";\n";
        $content = $t['fontcontent'] === 'body' ? $t['fontbody'] : $t['fontcontent'];
        $scss .= '$instracta-font-content: ' . $fonts[$content][1] . ";\n";
        $scss .= '$instracta-read-size: ' . round((int) $t['readsize'] / 16, 4) . "rem;\n";
        $scss .= '$instracta-read-lh: ' . $t['readlh'] . ";\n";
        $scss .= '$instracta-measure: ' . ($t['measure'] === '0' ? 'none' : $t['measure'] . 'ch') . ";\n";

        // Hand the main tokens to Bootstrap so buttons, links, forms and badges follow the brand.
        $scss .= '$primary: $instracta-primary;' . "\n";
        $scss .= '$font-family-sans-serif: $instracta-font-body;' . "\n";
        $scss .= '$headings-font-family: $instracta-font-heading;' . "\n";
        $scss .= '$headings-font-weight: 700;' . "\n";
        $scss .= '$border-radius: $instracta-r-md;' . "\n";
        $scss .= '$border-radius-sm: $instracta-r-sm;' . "\n";
        $scss .= '$border-radius-lg: $instracta-r-lg;' . "\n";
        $scss .= '$border-radius-xl: $instracta-r-xl;' . "\n";
        $scss .= '$btn-border-radius: $instracta-r-btn;' . "\n";
        $scss .= '$btn-border-radius-sm: $instracta-r-btn;' . "\n";
        $scss .= '$btn-border-radius-lg: $instracta-r-btn;' . "\n";
        $scss .= '$dropdown-link-hover-bg: $instracta-primary;' . "\n";
        $scss .= '$font-size-base: 1rem;' . "\n";
        $scss .= '$line-height-base: 1.55;' . "\n";
        return $scss;
    }

    /**
     * The @font-face rules for the bundled fonts that are in use. Moodle rewrites the
     * [[font:theme|file]] placeholders into real URLs when it compiles the CSS.
     *
     * @param \theme_config|null $theme
     * @return string
     */
    public static function font_faces(?\theme_config $theme = null): string {
        $t = self::resolve($theme);
        $fonts = self::fonts();
        $css = '';
        foreach (array_unique([$t['fontbody'], $t['fontheading'], $t['fontcontent'], 'atkinson', 'sourceserif']) as $key) {
            if (!isset($fonts[$key])) {
                continue;
            }
            [$label, $family, $file, $weights] = $fonts[$key];
            if (!$file) {
                continue;
            }
            $name = preg_match('/"([^"]+)"/', $family, $m) ? $m[1] : $label;
            $css .= '@font-face { font-family: "' . $name . '"; font-style: normal; font-display: swap; ' .
                'font-weight: ' . $weights . '; src: url([[font:theme|' . $file . ']]) format("woff2"); }' . "\n";
        }
        return $css;
    }
}
