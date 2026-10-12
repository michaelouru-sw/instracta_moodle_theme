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
    'name' => 'Software simulation',
    'tagline' => 'Hands-on labs and system training. Dark, technical and focused on practice.',
    'for' => 'Software training, labs, sandbox practice, product onboarding',
    'swatch' => ['#111827', '#0ea5e9'],
    'settings' => [
        'palette' => 'graphite', 'fontheading' => 'sora', 'fontbody' => 'inter', 'radius' => 'soft', 'shadow' => 'soft',
        'navbarstyle' => 'glass', 'courselayout' => 'focus', 'courseheader' => 'split', 'cardstyle' => 'outline',
        'courseindex' => 'grid', 'colormode' => 'dark',
    ],
    'sections' => [
        t::sec('hero', [
            'layout' => 'split', 'eyebrow' => 'Practice without risk', 'title' => 'Learn the software by using it',
            'subtitle' => 'Guided simulations replicate the real system. Click, make mistakes, repeat, then do it for real with confidence.',
            'btn1text' => 'Start a simulation', 'btn2text' => 'See how it works', 'btn2url' => '#how',
            'showsearch' => 0, 'height' => 'tall', 'bullets' => [['text' => 'Realistic sandbox'], ['text' => 'Instant feedback on every step']],
        ], ['bg' => 'dark']),
        t::sec('logos', ['title' => 'Supports the tools your team uses'], ['bg' => 'none']),
        t::sec('steps', ['eyebrow' => 'The method', 'title' => 'Watch. Try. Master.', 'look' => 'numbered', 'items' => [
            ['icon' => 'eye', 'title' => 'Watch', 'text' => 'A short walkthrough shows the task.'],
            ['icon' => 'computer-mouse', 'title' => 'Try', 'text' => 'Repeat it in a guided simulation.'],
            ['icon' => 'bullseye', 'title' => 'Master', 'text' => 'Complete the assessment without hints.'],
        ]], ['bg' => 'soft', 'anchor' => 'how']),
        t::sec('courses', ['eyebrow' => 'Simulations', 'title' => 'Available labs', 'source' => 'latest', 'limit' => 6, 'columns' => '3']),
        t::sec('features', ['title' => 'Built for hands-on learning', 'columns' => '2', 'cardstyle' => 'outline', 'items' => [
            ['icon' => 'terminal', 'title' => 'Safe sandbox', 'text' => 'Nothing you do here touches live data.', 'url' => ''],
            ['icon' => 'rotate', 'title' => 'Reset and retry', 'text' => 'Start over as many times as you need.', 'url' => ''],
            ['icon' => 'chart-line', 'title' => 'Skill tracking', 'text' => 'See which tasks you have mastered.', 'url' => ''],
            ['icon' => 'headset', 'title' => 'Help when stuck', 'text' => 'Hints, transcripts and a support forum.', 'url' => ''],
        ]], ['bg' => 'soft']),
        t::sec('video', ['eyebrow' => 'Demo', 'title' => 'See a simulation in action', 'ratio' => '16x9']),
        t::sec('cta', ['title' => 'Stop reading manuals. Start doing.', 'text' => 'Log in and launch your first simulation.', 'btn1text' => 'Launch now', 'btn1url' => '/login/index.php', 'align' => 'left'], ['bg' => 'gradient']),
    ],
];
