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
    'name' => 'University',
    'tagline' => 'Faculties, programmes and academic calendar. Formal tone, serif headings, structured layout.',
    'for' => 'Universities, colleges, schools of study',
    'swatch' => ['#312e81', '#d4a017'],
    'settings' => [
        'palette' => 'royal', 'fontheading' => 'sourceserif', 'fontbody' => 'inter', 'radius' => 'sharp', 'shadow' => 'flat',
        'navbarstyle' => 'solid', 'courselayout' => 'reading', 'courseheader' => 'split', 'cardstyle' => 'outline',
        'courseindex' => 'list', 'containerwidth' => '1280', 'colormode' => 'light',
    ],
    'sections' => [
        t::sec('hero', [
            'layout' => 'left', 'eyebrow' => 'Open day: first Saturday of every month', 'title' => 'Where serious learning begins',
            'subtitle' => 'Accredited programmes, expert faculty and a learning platform that supports you from first lecture to graduation.',
            'btn1text' => 'View programmes', 'btn2text' => 'Student login', 'showsearch' => 0, 'height' => 'tall', 'bullets' => [],
        ], ['bg' => 'dark']),
        t::sec('announcements', ['eyebrow' => 'Notice board', 'title' => 'Academic announcements', 'limit' => 3], ['bg' => 'soft', 'padtop' => 'md', 'padbottom' => 'md']),
        t::sec('categories', ['eyebrow' => 'Faculties', 'title' => 'Our faculties and schools', 'subtitle' => 'Choose a faculty to see its programmes and units.', 'columns' => '3', 'limit' => 6]),
        t::sec('split', ['eyebrow' => 'Why study with us', 'title' => 'Teaching that is rigorous and personal',
            'text' => '<p>Small seminar groups, research-active lecturers and online tools that keep every unit organised in one place.</p>',
            'bullets' => [['text' => 'Accredited degrees and diplomas'], ['text' => 'Dedicated academic advisors'], ['text' => 'Library and research resources online']],
            'btntext' => 'Explore student life', 'imageside' => 'left'], ['bg' => 'soft']),
        t::sec('stats', ['title' => 'The university in numbers', 'look' => 'cards', 'items' => [
            ['metric' => 'courses', 'value' => '', 'prefix' => '', 'suffix' => '', 'label' => 'Units online', 'icon' => 'book-open'],
            ['metric' => 'users', 'value' => '', 'prefix' => '', 'suffix' => '', 'label' => 'Active students', 'icon' => 'user-graduate'],
            ['metric' => 'custom', 'value' => '40', 'prefix' => '', 'suffix' => '+', 'label' => 'Programmes', 'icon' => 'graduation-cap'],
            ['metric' => 'custom', 'value' => '95', 'prefix' => '', 'suffix' => '%', 'label' => 'Graduate employment', 'icon' => 'briefcase'],
        ]], ['bg' => 'none']),
        t::sec('courses', ['eyebrow' => 'Open units', 'title' => 'Featured units this semester', 'source' => 'latest', 'limit' => 6, 'columns' => '3']),
        t::sec('team', ['eyebrow' => 'Leadership', 'title' => 'Meet our deans', 'columns' => '4', 'items' => [
            ['photo' => '', 'name' => 'Prof. Jane Doe', 'role' => 'Dean, Faculty of Business', 'bio' => ''],
            ['photo' => '', 'name' => 'Prof. John Smith', 'role' => 'Dean, Faculty of Science', 'bio' => ''],
        ]], ['bg' => 'soft']),
        t::sec('faq', ['title' => 'Student help', 'items' => [
            ['question' => 'How do I access my units?', 'answer' => 'Log in with your student number and open My courses.'],
            ['question' => 'Where do I find my timetable?', 'answer' => 'Use the Calendar from your dashboard. Deadlines appear automatically.'],
            ['question' => 'Who do I contact for support?', 'answer' => 'Use the Help page or contact your programme coordinator.'],
        ]]),
        t::sec('cta', ['title' => 'Applications are open', 'text' => 'Start your application and join the next intake.', 'btn1text' => 'Apply now', 'btn1url' => '/login/signup.php', 'align' => 'left'], ['bg' => 'dark']),
    ],
];
