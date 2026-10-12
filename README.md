# Instracta — premium theme for Moodle 5.0

A Boost child theme for Moodle 5.0 with an Instracta brand look, reading-first typography, six course page layouts, a drag and drop home page builder, site analytics and learner dashboards. All code is original.

## Install
1. Copy this folder to `<moodle>/theme/instracta` (the folder must be named `instracta`).
2. Visit *Site administration → Notifications* and finish the upgrade.
3. *Site administration → Appearance → Themes* → choose **Instracta**.
4. Open *Appearance → Themes → Instracta* for settings, then **Home page builder**.

## What you get
- **Reading clarity:** 17 px text, 1.7 line height, 68-character lines, Atkinson Hyperlegible and serif options, high contrast, reduced motion and focus mode. A Reading tools button lets every learner change these and their own layouts.
- **Course layouts:** Classic, Reading, Timeline, Cards, Compact and One section at a time; four course headers (banner, split, minimal, none); four course card styles (outline, raised, cover, list); three course list views. Admins set the defaults on *Reading & courses*; learners can override them.
- **Brand and style:** Instracta navy and orange by default, 8 palettes or your own colours, 4 bundled fonts (no external requests), corner style, depth, content width, glass / solid / transparent navbar, light and dark mode with a user switch.
- **Home page builder:** 20 section types, live preview (desktop, tablet, mobile), drag to reorder, undo/redo, autosaved draft, publish with notes, restore earlier versions, per-section background, spacing, audience and mobile/desktop visibility.
- **Analytics:** KPIs with trend, activity and enrolment charts, activity types, hour × weekday heatmap, top courses, completion rates, CSV export. Needs the standard log store.
- **Learner dashboard:** progress, streak and resume strip on *Dashboard*.
- **Login page, footer and social links** are customisable from settings.

## New in 1.5

- Full-width course pages: title rail plus multi-column activity grid, wide activity pages, and a sticky course map (section chips with progress and scroll tracking).
- Site administration redesign: category tiles, card settings with label rail, sticky save bar, refined tables.

## New in 1.4

- Course index drawer redesigned: guide-line tree, accent marker on the current item, round close button and a slim pill show/hide toggle.

- Course presentation redesign: immersive course header with glass chips and progress panel, tiled sections with numbered badges, activity rows with icon tiles, modern course cards with a floating menu, and lifted course index tiles. Works with every course layout, header, card and index setting, in light and dark.

## New in 1.3

- Redesigned navigation bar (pill links, round tool buttons, tidy menus and notification panel) that never wraps at tablet width.
- Redesigned calendar: wide month grid, today highlight, colour-coded event chips, compact dashboard block, mobile view.
- Analytics charts now fit their containers; doughnut colour error fixed.
- Login page layout fixed (brand panel no longer clips the headline).
- Course listing cards, messaging page, activity header and text contrast fixes in light and dark mode.

## New in 1.2

- **Site templates** (Site administration > Appearance > Themes > Instracta > Home > Site templates, or "..." menu in the builder): six ready-made platforms, each with its own design and home page: MOOC, University, Learning & Development, Kids school, Software simulation, Bootcamp academy. Apply design, home page, or both. The previous home page stays in History and the last template can be undone.
- Export your site as a template file and import it on another site.
- Redesigned learner dashboard: greeting, continue-learning card, progress-first course list, weekly activity and upcoming deadlines.
- Fixed: errors when editing home page sections.

## New in 1.1
- **Menus:** add links and drop-down menus to the main menu, filtered by audience (everyone, logged in, guests, teachers, admins). Optional mobile bottom bar.
- **Category colours:** give a course category (and its sub-categories) its own brand colours.
- **Notices:** up to three banners with start and end dates, an audience, a page type and a close button.
- **Course catalogue** (`/theme/instracta/catalogue.php`): search, category filter, sort, grid or list. Each course has a **landing page** with summary, syllabus, teachers, price (when the Course payment method is used) and an enrol button.
- **Activity bar:** previous and next activity with position in the course. **Reading progress bar** and **estimated reading time**.
- **Teacher hints:** hidden course, guest access, open self enrolment, role switching, enrolled and completed counts.
- **Static pages:** About, Contact, Help and Accessibility, edited in the settings.
- **Helpers:** back to top, external link marks, cookie notice.
- **Settings import and export**, with a configuration check, at `/theme/instracta/transfer.php`.

## Permissions
- `theme/instracta:managehome` — use the page builder (managers by default).
- `theme/instracta:viewanalytics` — see analytics (managers by default).

## Notes
- Clear caches after installing (*Purge all caches*).
- The builder removes scripts from pasted HTML. Images are stored in the theme's media library.
- Licensed GPL v3 or later. Bundled fonts: SIL OFL 1.1.
