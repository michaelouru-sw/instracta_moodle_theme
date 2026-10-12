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
 * Instracta theme settings.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Admin tools: the page builder and the analytics dashboard.
$ADMIN->add('themes', new admin_externalpage(
    'theme_instracta_builder',
    get_string('b_title', 'theme_instracta'),
    new moodle_url('/theme/instracta/builder/index.php'),
    'theme/instracta:managehome'
));
$ADMIN->add('themes', new admin_externalpage(
    'theme_instracta_analytics',
    get_string('analytics_title', 'theme_instracta'),
    new moodle_url('/theme/instracta/analytics/index.php'),
    'theme/instracta:viewanalytics'
));

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/theme/boost/classes/admin_settingspage_tabs.php');
}

$settings = new theme_boost_admin_settingspage_tabs('themesettinginstracta', get_string('configtitle', 'theme_instracta'));

if ($ADMIN->fulltree) {
    $s = 'theme_instracta';
    $reset = 'theme_reset_all_caches';

    /**
     * Add a setting to a page with its name and description strings, and reset theme caches on change.
     *
     * @param admin_settingpage $page
     * @param admin_setting $setting
     */
    $put = function (admin_settingpage $page, admin_setting $setting) use ($reset) {
        $setting->set_updatedcallback($reset);
        $page->add($setting);
    };
    $name = function (string $key) use ($s) {
        return [get_string($key, $s), get_string($key . '_desc', $s)];
    };
    $options = function (array $keys, string $prefix) use ($s) {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = get_string($prefix . $key, $s);
        }
        return $out;
    };

    $labelled = function (array $map) use ($s) {
        return array_map(function ($key) use ($s) {
            return get_string($key, $s);
        }, $map);
    };

    // Brand and style.
    $page = new admin_settingpage('theme_instracta_brand', get_string('tab_brand', $s));

    $page->add(new admin_setting_heading('instracta_h_colors', get_string('h_colors', $s), ''));
    [$n, $d] = $name('palette');
    $palettes = [];
    foreach (array_keys(theme_instracta\tokens::palettes()) as $key) {
        $palettes[$key] = get_string('palette_' . $key, $s);
    }
    $put($page, new admin_setting_configselect("$s/palette", $n, $d, 'instracta', $palettes));
    [$n, $d] = $name('primarycolor');
    $put($page, new admin_setting_configcolourpicker("$s/primarycolor", $n, $d, ''));
    [$n, $d] = $name('accentcolor');
    $put($page, new admin_setting_configcolourpicker("$s/accentcolor", $n, $d, ''));
    [$n, $d] = $name('gradientstart');
    $put($page, new admin_setting_configcolourpicker("$s/gradientstart", $n, $d, ''));
    [$n, $d] = $name('gradientend');
    $put($page, new admin_setting_configcolourpicker("$s/gradientend", $n, $d, ''));

    $page->add(new admin_setting_heading('instracta_h_type', get_string('h_type', $s), ''));
    $fonts = [];
    foreach (theme_instracta\tokens::fonts() as $key => $font) {
        $fonts[$key] = $font[0];
    }
    [$n, $d] = $name('fontbody');
    $put($page, new admin_setting_configselect("$s/fontbody", $n, $d, 'inter', $fonts));
    [$n, $d] = $name('fontheading');
    $put($page, new admin_setting_configselect("$s/fontheading", $n, $d, 'jakarta', $fonts));

    $page->add(new admin_setting_heading('instracta_h_shape', get_string('h_shape', $s), ''));
    [$n, $d] = $name('radius');
    $put($page, new admin_setting_configselect("$s/radius", $n, $d, 'soft', $options(['sharp', 'soft', 'round', 'pill'], 'radius_')));
    [$n, $d] = $name('shadow');
    $put($page, new admin_setting_configselect("$s/shadow", $n, $d, 'soft', $options(['flat', 'soft', 'dramatic'], 'shadow_')));
    [$n, $d] = $name('containerwidth');
    $put($page, new admin_setting_configselect("$s/containerwidth", $n, $d, '1280', [
        '1140' => '1140 px', '1280' => '1280 px', '1440' => '1440 px', 'fluid' => get_string('width_fluid', $s),
    ]));

    $page->add(new admin_setting_heading('instracta_h_mode', get_string('h_mode', $s), ''));
    [$n, $d] = $name('colormode');
    $put($page, new admin_setting_configselect("$s/colormode", $n, $d, 'auto', $options(['auto', 'light', 'dark'], 'mode_')));
    [$n, $d] = $name('colortoggle');
    $put($page, new admin_setting_configcheckbox("$s/colortoggle", $n, $d, 1));
    [$n, $d] = $name('sitebackground');
    $setting = new admin_setting_configstoredfile("$s/sitebackground", $n, $d, 'sitebackground', 0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp']]);
    $put($page, $setting);
    $settings->add($page);

    // Navbar and footer.
    $page = new admin_settingpage('theme_instracta_nav', get_string('tab_nav', $s));
    $page->add(new admin_setting_heading('instracta_h_navbar', get_string('h_navbar', $s), ''));
    [$n, $d] = $name('navbarstyle');
    $put($page, new admin_setting_configselect("$s/navbarstyle", $n, $d, 'glass', $options(['glass', 'solid', 'transparent'], 'navbar_')));
    [$n, $d] = $name('navbarsticky');
    $put($page, new admin_setting_configcheckbox("$s/navbarsticky", $n, $d, 1));

    $page->add(new admin_setting_heading('instracta_h_footer', get_string('h_footer', $s), ''));
    [$n, $d] = $name('footerenabled');
    $put($page, new admin_setting_configcheckbox("$s/footerenabled", $n, $d, 1));
    [$n, $d] = $name('footerabout');
    $put($page, new admin_setting_configtextarea("$s/footerabout", $n, $d, '', PARAM_TEXT, 60, 3));
    for ($i = 1; $i <= 3; $i++) {
        $put($page, new admin_setting_configtext("$s/footertitle$i", get_string('footertitle', $s, $i),
            get_string('footertitle_desc', $s), '', PARAM_TEXT));
        $put($page, new admin_setting_configtextarea("$s/footerlinks$i", get_string('footerlinks', $s, $i),
            get_string('footerlinks_desc', $s), '', PARAM_RAW, 60, 4));
    }
    foreach (['facebook', 'x', 'instagram', 'linkedin', 'youtube', 'whatsapp'] as $net) {
        $put($page, new admin_setting_configtext("$s/social_$net", get_string('social_' . $net, $s),
            get_string('social_desc', $s), '', PARAM_URL));
    }
    [$n, $d] = $name('footercopyright');
    $put($page, new admin_setting_configtext("$s/footercopyright", $n, $d, '', PARAM_TEXT));
    $settings->add($page);

    // Login page.
    $page = new admin_settingpage('theme_instracta_login', get_string('tab_login', $s));
    [$n, $d] = $name('loginheadline');
    $put($page, new admin_setting_configtext("$s/loginheadline", $n, $d, '', PARAM_TEXT));
    [$n, $d] = $name('loginsubtext');
    $put($page, new admin_setting_configtextarea("$s/loginsubtext", $n, $d, '', PARAM_TEXT, 60, 3));
    [$n, $d] = $name('loginbackground');
    $put($page, new admin_setting_configstoredfile("$s/loginbackground", $n, $d, 'loginbackground', 0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp']]));
    $settings->add($page);

    // Home and dashboard.
    $page = new admin_settingpage('theme_instracta_home', get_string('tab_home', $s));
    $page->add(new admin_setting_description('instracta_tools', get_string('tools', $s),
        html_writer::div(
            html_writer::link(new moodle_url('/theme/instracta/builder/index.php'), get_string('b_title', $s),
                ['class' => 'btn btn-primary me-2']) .
            html_writer::link(new moodle_url('/theme/instracta/analytics/index.php'), get_string('analytics_title', $s),
                ['class' => 'btn btn-secondary me-2']) .
            html_writer::link(new moodle_url('/theme/instracta/sitetemplates.php'), get_string('tpl_title', $s),
                ['class' => 'btn btn-secondary']),
            'mb-3'
        )));
    [$n, $d] = $name('homebuilder');
    $put($page, new admin_setting_configcheckbox("$s/homebuilder", $n, $d, 1));
    [$n, $d] = $name('learnerstrip');
    $put($page, new admin_setting_configcheckbox("$s/learnerstrip", $n, $d, 1));
    $settings->add($page);

    // Reading and courses.
    $page = new admin_settingpage('theme_instracta_reading', get_string('tab_reading', $s));
    $page->add(new admin_setting_heading('instracta_h_reading', get_string('h_reading', $s), ''));
    [$n, $d] = $name('readingsize');
    $put($page, new admin_setting_configselect("$s/readingsize", $n, $d, '17',
        ['16' => '16 px', '17' => '17 px', '18' => '18 px', '19' => '19 px']));
    [$n, $d] = $name('readinglh');
    $put($page, new admin_setting_configselect("$s/readinglh", $n, $d, '1.7',
        ['1.6' => '1.6', '1.7' => '1.7', '1.8' => '1.8', '1.9' => '1.9']));
    [$n, $d] = $name('readingmeasure');
    $put($page, new admin_setting_configselect("$s/readingmeasure", $n, $d, '68', [
        '58' => get_string('rt_measure_narrow', $s), '68' => get_string('rt_measure_default', $s),
        '78' => get_string('rt_measure_wide', $s), '0' => get_string('rt_measure_full', $s),
    ]));
    [$n, $d] = $name('readingfont');
    $put($page, new admin_setting_configselect("$s/readingfont", $n, $d, 'body', [
        'body' => get_string('rt_font_default', $s), 'atkinson' => 'Atkinson Hyperlegible',
        'sourceserif' => 'Source Serif',
    ]));
    [$n, $d] = $name('readingtools');
    $put($page, new admin_setting_configcheckbox("$s/readingtools", $n, $d, 1));

    $page->add(new admin_setting_heading('instracta_h_courselayouts', get_string('h_courselayouts', $s), ''));
    [$n, $d] = $name('learnerlayouts');
    $put($page, new admin_setting_configcheckbox("$s/learnerlayouts", $n, $d, 1));
    [$n, $d] = $name('courselayout');
    $put($page, new admin_setting_configselect("$s/courselayout", $n, $d, 'reading',
        $labelled(\theme_instracta\reading::course_layouts())));
    [$n, $d] = $name('courseheader');
    $put($page, new admin_setting_configselect("$s/courseheader", $n, $d, 'hero',
        $labelled(\theme_instracta\reading::course_headers())));
    [$n, $d] = $name('cardstyle');
    $put($page, new admin_setting_configselect("$s/cardstyle", $n, $d, 'outline',
        $labelled(\theme_instracta\reading::card_styles())));
    [$n, $d] = $name('courseindex');
    $put($page, new admin_setting_configselect("$s/courseindex", $n, $d, 'grid',
        $labelled(\theme_instracta\reading::index_layouts())));
    $settings->add($page);

    // Menus and notices.
    $page = new admin_settingpage('theme_instracta_menus', get_string('tab_menus', $s));
    $page->add(new admin_setting_heading('instracta_h_menus', get_string('h_menus', $s), get_string('h_menus_desc', $s)));
    [$n, $d] = $name('menuitems');
    $put($page, new admin_setting_configtextarea("$s/menuitems", $n, $d, '', PARAM_RAW, 60, 8));
    [$n, $d] = $name('mobilebar');
    $put($page, new admin_setting_configcheckbox("$s/mobilebar", $n, $d, 0));
    [$n, $d] = $name('mobilebaritems');
    $put($page, new admin_setting_configtextarea("$s/mobilebaritems", $n, $d,
        theme_instracta\features::MOBILE_BAR_DEFAULT, PARAM_RAW, 60, 5));
    [$n, $d] = $name('catalogue');
    $put($page, new admin_setting_configcheckbox("$s/catalogue", $n, $d, 1));
    [$n, $d] = $name('catcolors');
    $put($page, new admin_setting_configtextarea("$s/catcolors", $n, $d, '', PARAM_RAW, 60, 4));

    $page->add(new admin_setting_heading('instracta_h_notices', get_string('h_notices', $s), get_string('h_notices_desc', $s)));
    for ($i = 1; $i <= 3; $i++) {
        $page->add(new admin_setting_heading("instracta_h_notice$i", get_string('notice_n', $s, $i), ''));
        $put($page, new admin_setting_configtextarea("$s/notice{$i}_text", get_string('notice_text', $s),
            get_string('notice_text_desc', $s), '', PARAM_RAW, 60, 2));
        $put($page, new admin_setting_configselect("$s/notice{$i}_type", get_string('notice_type', $s), '', 'info',
            $options(['info', 'success', 'warning', 'danger'], 'nt_')));
        $put($page, new admin_setting_configselect("$s/notice{$i}_audience", get_string('notice_audience', $s), '', 'all',
            $options(['all', 'user', 'guest', 'teacher', 'admin'], 'aud_')));
        $put($page, new admin_setting_configselect("$s/notice{$i}_pages", get_string('notice_pages', $s), '', 'all',
            $options(['all', 'home', 'dashboard', 'course'], 'np_')));
        $put($page, new admin_setting_configtext("$s/notice{$i}_from", get_string('notice_from', $s),
            get_string('notice_date_desc', $s), '', PARAM_TEXT, 20));
        $put($page, new admin_setting_configtext("$s/notice{$i}_to", get_string('notice_to', $s),
            get_string('notice_date_desc', $s), '', PARAM_TEXT, 20));
        $put($page, new admin_setting_configcheckbox("$s/notice{$i}_dismiss", get_string('notice_dismiss', $s), '', 1));
    }
    $settings->add($page);

    // Helpers and pages.
    $page = new admin_settingpage('theme_instracta_helpers', get_string('tab_helpers', $s));
    $page->add(new admin_setting_heading('instracta_h_helpers', get_string('h_helpers', $s), ''));
    foreach (['activitybar' => 1, 'readprogress' => 1, 'readtime' => 1, 'teacherhints' => 1, 'backtotop' => 1, 'extlinks' => 0,
        'cookie' => 0] as $key => $default) {
        [$n, $d] = $name($key);
        $put($page, new admin_setting_configcheckbox("$s/$key", $n, $d, $default));
    }
    [$n, $d] = $name('cookietext');
    $put($page, new admin_setting_configtextarea("$s/cookietext", $n, $d, '', PARAM_TEXT, 60, 2));
    [$n, $d] = $name('cookielink');
    $put($page, new admin_setting_configtext("$s/cookielink", $n, $d, '', PARAM_URL));

    $page->add(new admin_setting_heading('instracta_h_pages', get_string('h_pages', $s), get_string('h_pages_desc', $s)));
    foreach (['about', 'contact', 'help', 'accessibility'] as $key) {
        $page->add(new admin_setting_configtext("$s/page_{$key}_title", get_string('page_title_n', $s, get_string('pg_' . $key, $s)),
            '', '', PARAM_TEXT));
        $page->add(new admin_setting_confightmleditor("$s/page_{$key}_content", get_string('page_content_n', $s,
            get_string('pg_' . $key, $s)), '', ''));
    }
    $page->add(new admin_setting_description('instracta_transfer', get_string('transfer_title', $s),
        html_writer::link(new moodle_url('/theme/instracta/transfer.php'), get_string('transfer_title', $s),
            ['class' => 'btn btn-secondary'])));
    $settings->add($page);

    // Advanced.
    $page = new admin_settingpage('theme_instracta_advanced', get_string('tab_advanced', $s));
    [$n, $d] = $name('scsspre');
    $put($page, new admin_setting_scsscode("$s/scsspre", $n, $d, '', PARAM_RAW));
    [$n, $d] = $name('scss');
    $put($page, new admin_setting_scsscode("$s/scss", $n, $d, '', PARAM_RAW));
    $settings->add($page);
}
