<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
// Moodle is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
// of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

/**
 * Site template.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use theme_instracta\sitetemplates as t;
return [
    'name' => 'Learning & Development',
    'tagline' => 'Corporate academy for staff. Role-based paths, compliance first, results-focused.',
    'for' => 'Companies, HR and training teams',
    'swatch' => ['#15803d', '#0d9488'],
    'settings' => [
        'palette' => 'forest', 'fontheading' => 'manrope', 'fontbody' => 'inter', 'radius' => 'soft', 'shadow' => 'soft',
        'navbarstyle' => 'solid', 'courselayout' => 'cards', 'courseheader' => 'minimal', 'cardstyle' => 'cover',
        'courseindex' => 'compact', 'colormode' => 'auto', 'footerabout' => 'Learning and Development Academy. Build the skills your role needs.',
    ],
    'sections' => [
        t::sec('hero', [
            'layout' => 'split', 'eyebrow' => 'Learning and Development Academy', 'title' => 'Grow your skills. Grow with the company.',
            'subtitle' => 'Your learning paths, mandatory training and professional development in one place.',
            'btn1text' => 'My learning', 'btn1url' => '/my/', 'btn2text' => 'Browse the catalogue', 'btn2url' => '/course/index.php',
            'showsearch' => 1, 'height' => 'auto', 'bullets' => [['text' => 'Role based learning paths'], ['text' => 'Track compliance in real time']],
        ]),
        t::sec('announcements', ['eyebrow' => 'From L&D', 'title' => 'Important notices', 'limit' => 3], ['padtop' => 'md', 'padbottom' => 'md', 'audience' => 'loggedin']),
        t::sec('features', ['eyebrow' => 'Learning paths', 'title' => 'Start from your role', 'columns' => '4', 'cardstyle' => 'outline', 'items' => [
            ['icon' => 'user-tie', 'title' => 'Managers', 'text' => 'Lead teams, feedback and performance.', 'url' => '/course/index.php'],
            ['icon' => 'headset', 'title' => 'Customer teams', 'text' => 'Service skills and product knowledge.', 'url' => '/course/index.php'],
            ['icon' => 'laptop-code', 'title' => 'Technical staff', 'text' => 'Tools, systems and best practice.', 'url' => '/course/index.php'],
            ['icon' => 'shield-halved', 'title' => 'Everyone', 'text' => 'Compliance, safety and security basics.', 'url' => '/course/index.php'],
        ]], ['bg' => 'soft']),
        t::sec('courses', ['eyebrow' => 'Required', 'title' => 'Mandatory training', 'source' => 'latest', 'limit' => 3, 'columns' => '3', 'showsummary' => 0]),
        t::sec('stats', ['title' => 'Learning across the company', 'look' => 'cards', 'items' => [
            ['metric' => 'users', 'value' => '', 'prefix' => '', 'suffix' => '', 'label' => 'Active learners', 'icon' => 'users'],
            ['metric' => 'completions', 'value' => '', 'prefix' => '', 'suffix' => '', 'label' => 'Completions', 'icon' => 'circle-check'],
            ['metric' => 'courses', 'value' => '', 'prefix' => '', 'suffix' => '', 'label' => 'Programmes', 'icon' => 'book-open'],
            ['metric' => 'custom', 'value' => '4.7', 'prefix' => '', 'suffix' => '/5', 'label' => 'Average rating', 'icon' => 'star'],
        ]], ['bg' => 'none']),
        t::sec('courses', ['eyebrow' => 'Recommended', 'title' => 'Build new skills', 'source' => 'popular', 'limit' => 6, 'layout' => 'carousel']),
        t::sec('faq', ['title' => 'Need help?', 'items' => [
            ['question' => 'How do I see my required courses?', 'answer' => 'Open your dashboard. Required courses appear first with their due dates.'],
            ['question' => 'Where do I get my certificates?', 'answer' => 'Completed courses list their certificate on the course page and your profile.'],
        ]], ['bg' => 'soft']),
    ],
];
