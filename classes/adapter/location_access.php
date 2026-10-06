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

namespace filter_dixeo_imageeditor\adapter;

use local_dixeo\service\image\pluginfile_helper;

/**
 * Course and module access checks for stored file locations.
 *
 * @package    filter_dixeo_imageeditor
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class location_access {
    /**
     * Require login and filter edit capability for a stored file's course/module.
     *
     * @param \stored_file $file
     * @return int Course id.
     */
    public static function require_edit_access_for_file(\stored_file $file): int {
        $courseid = pluginfile_helper::resolve_course_id_for_file($file);
        if ($courseid < 1) {
            throw new \moodle_exception('error_not_eligible', 'filter_dixeo_imageeditor');
        }

        $filecontext = \context::instance_by_id($file->get_contextid(), IGNORE_MISSING);
        if (!$filecontext) {
            throw new \moodle_exception('error_not_eligible', 'filter_dixeo_imageeditor');
        }

        if ($filecontext->contextlevel === CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(null, $filecontext->instanceid, 0, false, MUST_EXIST);
            $course = get_course($cm->course);
            require_login($course, false, $cm);
        } else {
            $course = get_course($courseid);
            require_login($course);
        }

        feature_gate::require_filter_edit($courseid);

        return $courseid;
    }

    /**
     * Require login and filter edit capability for a course-scoped location.
     *
     * Used when a tiles section still shows an icon and has no photo file yet.
     *
     * @param int $courseid
     * @return void
     */
    public static function require_edit_access_for_course(int $courseid): void {
        $course = get_course($courseid);
        require_login($course);
        feature_gate::require_filter_edit($courseid);
    }
}
