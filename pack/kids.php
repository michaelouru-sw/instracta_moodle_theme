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
    'name' => 'Kids school',
    'tagline' => 'Playful, big, friendly. Large rounded shapes, warm colours and simple words for young learners and parents.',
    'for' => 'Primary schools, tutoring centres, homeschool',
    'swatch' => ['#f59e0b', '#e11d48'],
    'settings' => [
        'palette' => 'sunset', 'fontheading' => 'sora', 'fontbody' => 'atkinson', 'radius' => 'pill', 'shadow' => 'dramatic',
        'navbarstyle' => 'solid', 'courselayout' => 'cards', 'courseheader' => 'hero', 'cardstyle' => 'elevated',
        'courseindex' => 'grid', 'colormode' => 'light', 'readingsize' => '19', 'readinglh' => '1.8', 'readingfont' => 'atkinson',
    ],
    'sections' => [
        t::sec('hero', [
            'layout' => 'center', 'eyebrow' => 'Hello, young explorers!', 'title' => 'Learning is an adventure',
            'subtitle' => 'Fun lessons, colourful activities and friendly teachers, all in one safe place.',
            'btn1text' => 'Start learning', 'btn1url' => '/login/index.php', 'btn2text' => 'For parents', 'btn2url' => '/theme/instracta/page.php?page=about',
            'showsearch' => 0, 'height' => 'tall', 'bullets' => [['text' => 'Safe and ad free'], ['text' => 'Games and quizzes'], ['text' => 'Stars for every win']],
        ], ['bg' => 'gradient']),
        t::sec('categories', ['eyebrow' => 'Pick a subject', 'title' => 'What do you want to learn today?', 'columns' => '4', 'limit' => 8, 'showcount' => 0], ['bg' => 'none']),
        t::sec('features', ['title' => 'Why children love it', 'columns' => '3', 'cardstyle' => 'card', 'items' => [
            ['icon' => 'gamepad', 'title' => 'Learn by playing', 'text' => 'Games and challenges that make lessons fun.', 'url' => ''],
            ['icon' => 'star', 'title' => 'Earn stars', 'text' => 'Celebrate every step with badges and stars.', 'url' => ''],
            ['icon' => 'hands-holding-child', 'title' => 'Kind teachers', 'text' => 'Friendly help whenever you get stuck.', 'url' => ''],
        ]], ['bg' => 'soft']),
        t::sec('courses', ['eyebrow' => 'New adventures', 'title' => 'Fun lessons to try', 'source' => 'latest', 'limit' => 6, 'columns' => '3']),
        t::sec('split', ['eyebrow' => 'For parents', 'title' => 'See how your child is doing',
            'text' => '<p>Follow progress, see finished lessons and celebrate achievements together. Everything is private and safe.</p>',
            'bullets' => [['text' => 'Progress at a glance'], ['text' => 'Safe, moderated space'], ['text' => 'Works on tablets and phones']], 'imagestyle' => 'shadow'], ['bg' => 'none']),
        t::sec('testimonials', ['eyebrow' => 'Happy families', 'title' => 'What parents say', 'layout' => 'grid'], ['bg' => 'soft']),
        t::sec('cta', ['title' => 'Ready for the first lesson?', 'text' => 'Ask a grown up to help you log in.', 'btn1text' => 'Let\'s go', 'btn1url' => '/login/index.php']),
    ],
];
