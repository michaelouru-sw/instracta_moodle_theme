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
 * Storage and revision handling for page builder layouts.
 *
 * A page has at most one draft and one published row. Publishing archives the previous
 * published row, so the last revisions can be restored into the draft.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string Table name. */
    const TABLE = 'theme_instracta_page';

    /** @var int Archived revisions kept per page. */
    const KEEP_REVISIONS = 25;

    /**
     * Fetch a row by status.
     *
     * @param string $pagekey
     * @param string $status draft or published
     * @return \stdClass|null
     */
    protected static function row(string $pagekey, string $status): ?\stdClass {
        global $DB;
        $rows = $DB->get_records(self::TABLE, ['pagekey' => $pagekey, 'status' => $status], 'id DESC', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /**
     * Decode the layout stored in a row.
     *
     * @param \stdClass|null $row
     * @return array|null
     */
    protected static function decode(?\stdClass $row): ?array {
        if (!$row) {
            return null;
        }
        $decoded = json_decode($row->layout, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The published layout, or null if none has been published.
     *
     * @param string $pagekey
     * @return array|null
     */
    public static function get_published(string $pagekey = 'home'): ?array {
        return self::decode(self::row($pagekey, 'published'));
    }

    /**
     * The working draft. Falls back to the published layout, then to the default layout.
     *
     * @param string $pagekey
     * @return array
     */
    public static function get_draft(string $pagekey = 'home'): array {
        $draft = self::decode(self::row($pagekey, 'draft'));
        if ($draft !== null) {
            return $draft;
        }
        return self::get_published($pagekey) ?? self::default_layout();
    }

    /**
     * Whether the draft differs from what is published.
     *
     * @param string $pagekey
     * @return bool
     */
    public static function has_unpublished_changes(string $pagekey = 'home'): bool {
        $draft = self::row($pagekey, 'draft');
        if (!$draft) {
            return false;
        }
        $published = self::row($pagekey, 'published');
        return !$published || $published->layout !== $draft->layout;
    }

    /**
     * Save the draft. The layout is sanitised first.
     *
     * @param array $layout raw layout from the browser
     * @param int $userid
     * @param string $pagekey
     * @return array the sanitised layout that was stored
     */
    public static function save_draft(array $layout, int $userid, string $pagekey = 'home'): array {
        global $DB;
        $clean = registry::sanitize_layout($layout);
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();

        $draft = self::row($pagekey, 'draft');
        if ($draft) {
            $draft->layout = $json;
            $draft->usermodified = $userid;
            $draft->timemodified = $now;
            $DB->update_record(self::TABLE, $draft);
        } else {
            $DB->insert_record(self::TABLE, (object) [
                'pagekey' => $pagekey,
                'status' => 'draft',
                'layout' => $json,
                'note' => '',
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        return $clean;
    }

    /**
     * Publish the draft.
     *
     * @param int $userid
     * @param string $note optional revision note
     * @param string $pagekey
     */
    public static function publish(int $userid, string $note = '', string $pagekey = 'home'): void {
        global $DB;
        $draft = self::row($pagekey, 'draft');
        if (!$draft) {
            return;
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $current = self::row($pagekey, 'published');
        if ($current) {
            $current->status = 'archived';
            $DB->update_record(self::TABLE, $current);
        }
        $DB->insert_record(self::TABLE, (object) [
            'pagekey' => $pagekey,
            'status' => 'published',
            'layout' => $draft->layout,
            'note' => \core_text::substr(clean_param($note, PARAM_TEXT), 0, 250),
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Prune old archived revisions.
        $archived = $DB->get_fieldset_select(self::TABLE, 'id', 'pagekey = ? AND status = ?', [$pagekey, 'archived'], 'id DESC');
        if (count($archived) > self::KEEP_REVISIONS) {
            $DB->delete_records_list(self::TABLE, 'id', array_slice($archived, self::KEEP_REVISIONS));
        }
        $transaction->allow_commit();
    }

    /**
     * Revision history, newest first: the published row followed by archived rows.
     *
     * @param string $pagekey
     * @param int $limit
     * @return array of ['id', 'status', 'note', 'user', 'time', 'timeformatted', 'sections']
     */
    public static function revisions(string $pagekey = 'home', int $limit = 20): array {
        global $DB;
        $sql = "SELECT p.id, p.status, p.note, p.timecreated, p.layout, u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {" . self::TABLE . "} p
             LEFT JOIN {user} u ON u.id = p.usermodified
                 WHERE p.pagekey = :pagekey AND p.status IN ('published', 'archived')
              ORDER BY p.id DESC";
        $out = [];
        foreach ($DB->get_records_sql($sql, ['pagekey' => $pagekey], 0, $limit) as $row) {
            $layout = json_decode($row->layout, true);
            $out[] = [
                'id' => (int) $row->id,
                'status' => $row->status,
                'note' => (string) $row->note,
                'user' => $row->firstname === null ? '' : fullname($row),
                'time' => (int) $row->timecreated,
                'timeformatted' => userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'sections' => is_array($layout) && !empty($layout['sections']) ? count($layout['sections']) : 0,
            ];
        }
        return $out;
    }

    /**
     * Copy a past revision into the draft. Nothing is published.
     *
     * @param int $revisionid
     * @param int $userid
     * @param string $pagekey
     * @return array|null the restored layout, or null if the revision does not exist
     */
    public static function restore(int $revisionid, int $userid, string $pagekey = 'home'): ?array {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $revisionid, 'pagekey' => $pagekey]);
        if (!$row || $row->status === 'draft') {
            return null;
        }
        $layout = json_decode($row->layout, true);
        return is_array($layout) ? self::save_draft($layout, $userid, $pagekey) : null;
    }

    /**
     * Throw away the draft: it becomes a copy of what is published (or the default layout).
     *
     * @param int $userid
     * @param string $pagekey
     * @return array the layout now in the draft
     */
    public static function discard_draft(int $userid, string $pagekey = 'home'): array {
        $source = self::get_published($pagekey) ?? self::default_layout();
        return self::save_draft($source, $userid, $pagekey);
    }

    /**
     * Make sure the home page has a published layout (called on install and when needed).
     */
    public static function ensure_seeded(): void {
        global $USER;
        if (self::row('home', 'published')) {
            return;
        }
        $userid = isset($USER->id) ? (int) $USER->id : 0;
        self::save_draft(self::default_layout(), $userid);
        self::publish($userid, get_string('revision_default', 'theme_instracta'));
    }

    /**
     * The default home page: a complete, ready-to-use landing page.
     *
     * @return array
     */
    public static function default_layout(): array {
        $sections = [
            registry::new_section('hero'),
            registry::new_section('stats'),
            registry::new_section('courses', ['source' => 'popular', 'title' => 'Featured courses']),
            registry::new_section('features'),
            registry::new_section('categories'),
            registry::new_section('steps'),
            registry::new_section('testimonials'),
            registry::new_section('announcements'),
            registry::new_section('faq'),
            registry::new_section('cta'),
        ];
        return ['v' => 1, 'sections' => $sections];
    }
}
