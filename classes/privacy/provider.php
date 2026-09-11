<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy API implementation for filter_dixeo_imageeditor.
 *
 * @package    filter_dixeo_imageeditor
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_dixeo_imageeditor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for version history metadata and archived history files.
 *
 * @package    filter_dixeo_imageeditor
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored metadata for the privacy API.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'filter_dixeo_imageeditor_version',
            [
                'filename' => 'privacy:metadata:filename',
                'source' => 'privacy:metadata:source',
                'usermodified' => 'privacy:metadata:usermodified',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:versiontable'
        );

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:historyfiles');

        $collection->add_external_location_link(
            'local_dixeo',
            [
                'prompt' => 'privacy:metadata:local_dixeo:prompt',
                'images' => 'privacy:metadata:local_dixeo:images',
            ],
            'privacy:metadata:local_dixeo'
        );

        return $collection;
    }

    /**
     * Return contexts containing user version history data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.id IN (
                       SELECT DISTINCT v.contextid
                         FROM {filter_dixeo_imageeditor_version} v
                        WHERE v.usermodified = :useridversion
                 )";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'useridversion' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Populate the userlist for a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $contextid = (int) $context->id;

        $sql = "SELECT DISTINCT v.usermodified AS userid
                  FROM {filter_dixeo_imageeditor_version} v
                 WHERE v.contextid = :contextidversion";

        $userlist->add_from_sql('userid', $sql, [
            'contextidversion' => $contextid,
        ]);
    }

    /**
     * Export version history data and archived history files for approved contexts.
     *
     * History binaries live in the system context file area keyed by version id;
     * they are attached to the content context export where the version row lives.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $fs = get_file_storage();
        $systemcontext = \context_system::instance();
        $versionsubcontext = [get_string('privacy:pathversions', 'filter_dixeo_imageeditor')];

        foreach ($contextlist as $context) {
            $versions = $DB->get_records('filter_dixeo_imageeditor_version', [
                'contextid' => $context->id,
                'usermodified' => $userid,
            ], 'timecreated ASC');

            if (empty($versions)) {
                continue;
            }

            $exportversions = [];
            $writer = writer::with_context($context);
            foreach ($versions as $version) {
                $exportversions[] = (object) [
                    'filename' => $version->filename,
                    'source' => $version->source,
                    'timecreated' => transform::datetime($version->timecreated),
                ];

                $historyfiles = $fs->get_area_files(
                    $systemcontext->id,
                    'filter_dixeo_imageeditor',
                    'history',
                    (int) $version->id,
                    'itemid, filepath, filename',
                    false
                );
                foreach ($historyfiles as $file) {
                    $writer->export_file($versionsubcontext, $file);
                }
            }

            $writer->export_data($versionsubcontext, (object) ['versions' => $exportversions]);
        }
    }

    /**
     * Delete all version history data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        self::delete_versions(['contextid' => $context->id]);
    }

    /**
     * Delete version history data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            self::delete_versions([
                'contextid' => $context->id,
                'usermodified' => $userid,
            ]);
        }
    }

    /**
     * Delete version history data for approved users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();

        foreach ($userlist->get_userids() as $userid) {
            self::delete_versions([
                'contextid' => $context->id,
                'usermodified' => $userid,
            ]);
        }
    }

    /**
     * Delete version rows and their archived history files.
     *
     * @param array $conditions filter_dixeo_imageeditor_version conditions.
     */
    private static function delete_versions(array $conditions): void {
        global $DB;

        $versionids = $DB->get_fieldset_select(
            'filter_dixeo_imageeditor_version',
            'id',
            implode(' AND ', array_map(static fn(string $field): string => "$field = :$field", array_keys($conditions))),
            $conditions
        );

        if ($versionids) {
            $fs = get_file_storage();
            $systemcontextid = \context_system::instance()->id;
            foreach ($versionids as $versionid) {
                $fs->delete_area_files($systemcontextid, 'filter_dixeo_imageeditor', 'history', (int) $versionid);
            }
        }

        $DB->delete_records('filter_dixeo_imageeditor_version', $conditions);
    }
}
