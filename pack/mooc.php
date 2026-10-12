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
    'name' => 'MOOC platform',
    'tagline' => 'Open courses for large, global cohorts. Big promise, social proof, clear enrol path.',
    'for' => 'Open online courses, public learners',
    'swatch' => ['#2563eb', '#06b6d4'],
    'settings' => [
        'palette' => 'ocean', 'fontheading' => 'jakarta', 'fontbody' => 'inter', 'radius' => 'round', 'shadow' => 'soft',
        'navbarstyle' => 'glass', 'courselayout' => 'timeline', 'courseheader' => 'hero', 'cardstyle' => 'elevated',
        'courseindex' => 'grid', 'colormode' => 'auto',
    ],
    'sections' => [
        t::sec('hero', [
            'layout' => 'center', 'eyebrow' => 'Free to join. Learn at your pace.', 'title' => 'World-class courses, open to everyone',
            'subtitle' => 'Join learners from around the world. Pick a course, learn with your cohort and earn a certificate you can share.',
            'btn1text' => 'Explore courses', 'btn2text' => 'Create free account', 'btn2url' => '/login/signup.php',
            'showsearch' => 1, 'height' => 'tall', 'bullets' => [['text' => 'Start any time'], ['text' => 'Verified certificates'], ['text' => 'Active discussion forums']],
        ]),
        t::sec('stats', ['look' => 'plain'], ['bg' => 'none']),
        t::sec('courses', ['eyebrow' => 'Trending now', 'title' => 'Most popular courses', 'source' => 'popular', 'limit' => 8, 'columns' => '4', 'layout' => 'carousel']),
        t::sec('steps', ['title' => 'From sign-up to certificate', 'look' => 'icons', 'items' => [
            ['icon' => 'user-plus', 'title' => 'Join for free', 'text' => 'Create your account in a minute.'],
            ['icon' => 'play', 'title' => 'Learn with your cohort', 'text' => 'Watch, practise and discuss each week.'],
            ['icon' => 'certificate', 'title' => 'Earn a certificate', 'text' => 'Finish the course and share your result.'],
        ]], ['bg' => 'soft']),
        t::sec('categories', ['title' => 'Find your subject', 'columns' => '4', 'limit' => 8], ['bg' => 'none']),
        t::sec('testimonials', ['title' => 'Learners from every continent', 'layout' => 'slider']),
        t::sec('faq', ['title' => 'Before you enrol', 'subtitle' => 'Everything about how open courses work.'], ['bg' => 'soft']),
        t::sec('cta', ['title' => 'Your next skill is one click away', 'text' => 'Join the community and start your first course today.',
            'btn1text' => 'Join for free', 'btn2text' => 'Browse courses', 'btn2url' => '/course/index.php']),
    ],
];
