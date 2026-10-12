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
    'name' => 'Bootcamp academy',
    'tagline' => 'Cohorts, outcomes and career results. Bold gradient, strong calls to action.',
    'for' => 'Bootcamps, professional academies, certification providers',
    'swatch' => ['#7c3aed', '#ec4899'],
    'settings' => [
        'palette' => 'aurora', 'fontheading' => 'jakarta', 'fontbody' => 'inter', 'radius' => 'round', 'shadow' => 'dramatic',
        'navbarstyle' => 'glass', 'courselayout' => 'compact', 'courseheader' => 'hero', 'cardstyle' => 'cover',
        'courseindex' => 'grid', 'colormode' => 'auto',
    ],
    'sections' => [
        t::sec('hero', [
            'layout' => 'left', 'eyebrow' => 'Next cohort starts soon', 'title' => 'Change careers in 12 weeks',
            'subtitle' => 'Project-based programmes taught by working professionals, with mentoring and career support until you are hired.',
            'btn1text' => 'See programmes', 'btn2text' => 'Book a call', 'btn2url' => '/theme/instracta/page.php?page=contact',
            'showsearch' => 0, 'height' => 'screen', 'bullets' => [['text' => 'Live mentor sessions'], ['text' => 'Portfolio projects'], ['text' => 'Career coaching']],
        ], ['bg' => 'gradient']),
        t::sec('stats', ['look' => 'cards', 'items' => [
            ['metric' => 'custom', 'value' => '92', 'prefix' => '', 'suffix' => '%', 'label' => 'Hired within 6 months', 'icon' => 'briefcase'],
            ['metric' => 'users', 'value' => '', 'prefix' => '', 'suffix' => '+', 'label' => 'Alumni', 'icon' => 'user-graduate'],
            ['metric' => 'custom', 'value' => '12', 'prefix' => '', 'suffix' => ' weeks', 'label' => 'To job ready', 'icon' => 'clock'],
            ['metric' => 'custom', 'value' => '4.8', 'prefix' => '', 'suffix' => '/5', 'label' => 'Student rating', 'icon' => 'star'],
        ]], ['bg' => 'none', 'padtop' => 'md']),
        t::sec('courses', ['eyebrow' => 'Programmes', 'title' => 'Pick your track', 'source' => 'popular', 'limit' => 6, 'columns' => '3']),
        t::sec('steps', ['eyebrow' => 'Your journey', 'title' => 'From beginner to hired', 'look' => 'numbered', 'items' => [
            ['icon' => 'book', 'title' => 'Learn', 'text' => 'Weekly lessons and live classes.'],
            ['icon' => 'hammer', 'title' => 'Build', 'text' => 'Real projects for your portfolio.'],
            ['icon' => 'handshake', 'title' => 'Get hired', 'text' => 'Interview practice and employer introductions.'],
        ]], ['bg' => 'dark']),
        t::sec('testimonials', ['eyebrow' => 'Alumni', 'title' => 'They did it. You can too.', 'layout' => 'slider']),
        t::sec('team', ['eyebrow' => 'Mentors', 'title' => 'Learn from people who do the job', 'columns' => '4', 'items' => [
            ['photo' => '', 'name' => 'Mentor name', 'role' => 'Senior engineer', 'bio' => ''],
            ['photo' => '', 'name' => 'Mentor name', 'role' => 'Product lead', 'bio' => ''],
        ]], ['bg' => 'soft']),
        t::sec('faq', ['title' => 'Questions before you apply']),
        t::sec('cta', ['title' => 'Your seat is waiting', 'text' => 'Cohorts are small. Apply today.', 'btn1text' => 'Apply now', 'btn1url' => '/login/signup.php', 'btn2text' => 'Talk to an advisor', 'btn2url' => '/theme/instracta/page.php?page=contact']),
    ],
];
