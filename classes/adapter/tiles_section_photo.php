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

use local_dixeo\service\image\content\location;

/**
 * Tiles section photos the image editor may create or replace.
 *
 * A section that still shows an icon has no stored photo. The editor uses a
 * reserved filename for that section until the first generate or upload
 * writes the file and records it as the section photo.
 *
 * @package    filter_dixeo_imageeditor
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tiles_section_photo {
    /**
     * Reserved filename for a section that does not yet have a photo.
     *
     * @param int $sectionid course_sections.id
     * @return string
     */
    public static function pending_filename(int $sectionid): string {
        return 'tile-photo-' . $sectionid . '.jpg';
    }

    /**
     * Whether photo tiles can be written on this site.
     *
     * @return bool
     */
    public static function photos_allowed(): bool {
        return class_exists(\format_tiles\local\tile_photo::class)
            && (bool) get_config('format_tiles', 'allowphototiles');
    }

    /**
     * Location for a section icon that has no photo file yet.
     *
     * The course id is taken from the section row. The client course id is ignored.
     *
     * @param array $params Location parameters from an external call.
     * @return location|null
     */
    public static function pending_location(array $params): ?location {
        global $DB;

        if (!self::photos_allowed()) {
            return null;
        }

        $paramsapi = \format_tiles\local\tile_photo::file_api_params();
        $sectionid = (int) ($params['itemid'] ?? 0);
        if ($sectionid < 1) {
            return null;
        }
        if ((string) ($params['component'] ?? '') !== $paramsapi['component']) {
            return null;
        }
        if ((string) ($params['filearea'] ?? '') !== $paramsapi['filearea']) {
            return null;
        }
        if ((string) ($params['filepath'] ?? '') !== $paramsapi['filepath']) {
            return null;
        }
        if ((string) ($params['filename'] ?? '') !== self::pending_filename($sectionid)) {
            return null;
        }

        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'id, course, section', IGNORE_MISSING);
        if (!$section || (int) $section->section < 1) {
            return null;
        }

        $course = get_course((int) $section->course);
        if ($course->format !== 'tiles') {
            return null;
        }

        $context = \context_course::instance((int) $section->course);
        if ((int) ($params['contextid'] ?? 0) !== (int) $context->id) {
            return null;
        }

        return new location(
            (int) $context->id,
            $paramsapi['component'],
            $paramsapi['filearea'],
            $sectionid,
            $paramsapi['filepath'],
            self::pending_filename($sectionid),
            (int) $section->course
        );
    }

    /**
     * Whether this location is a tiles section photo that has not been stored yet.
     *
     * @param location $location
     * @return bool
     */
    public static function is_pending(location $location): bool {
        if ($location->get_stored_file()) {
            return false;
        }

        return self::pending_location([
            'contextid' => $location->contextid,
            'component' => $location->component,
            'filearea' => $location->filearea,
            'itemid' => $location->itemid,
            'filepath' => $location->filepath,
            'filename' => $location->filename,
            'courseid' => $location->courseid,
        ]) !== null;
    }

    /**
     * Store the first photo for a section and record it so the icon is replaced.
     *
     * @param location $location
     * @param string $binary
     * @param int $userid
     * @return void
     */
    public static function create(location $location, string $binary, int $userid): void {
        if (!self::is_pending($location)) {
            throw new \moodle_exception('error_not_eligible', 'filter_dixeo_imageeditor');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimetype = $finfo->buffer($binary);
        if (!is_string($mimetype) || $mimetype === '' || $mimetype === 'application/octet-stream') {
            $mimetype = 'image/jpeg';
        }

        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => $location->contextid,
            'component' => $location->component,
            'filearea' => $location->filearea,
            'itemid' => $location->itemid,
            'filepath' => $location->filepath,
            'filename' => $location->filename,
            'userid' => $userid,
            'mimetype' => $mimetype,
        ], $binary);

        $context = \context::instance_by_id($location->contextid);
        $photo = new \format_tiles\local\tile_photo($context, $location->itemid);
        $photo->set_file($file);
    }

    /**
     * Section name used as the generation title when no photo file exists yet.
     *
     * @param location $location
     * @return string
     */
    public static function section_title(location $location): string {
        global $DB;

        $section = $DB->get_record('course_sections', ['id' => $location->itemid], '*', IGNORE_MISSING);
        if (!$section) {
            return get_string('contentimagetitlefallback', 'local_dixeo');
        }

        $course = get_course($location->courseid);
        $name = get_section_name($course, $section);
        $name = trim(strip_tags((string) $name));
        if ($name === '') {
            return get_string('contentimagetitlefallback', 'local_dixeo');
        }

        return $name;
    }
}
