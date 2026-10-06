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

/**
 * Determines whether a stored file is eligible for AI editing.
 *
 * @package    filter_dixeo_imageeditor
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligibility {
    /**
     * Whether a stored file is eligible for Dixeo editing.
     *
     * @param \stored_file $file
     * @return bool
     */
    public static function is_eligible_stored_file(\stored_file $file): bool {
        if ($file->is_directory()) {
            return false;
        }
        if ($file->is_external_file()) {
            return false;
        }
        if ((int) $file->get_referencefileid() > 0) {
            return false;
        }

        $context = \context::instance_by_id($file->get_contextid(), IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            return false;
        }

        if (!in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE, CONTEXT_BLOCK, CONTEXT_COURSECAT], true)) {
            return false;
        }

        return !self::is_denied_component_filearea($file->get_component(), $file->get_filearea());
    }

    /**
     * Resolve an eligible stored file from a pluginfile URL.
     *
     * @param string $imageurl
     * @return \stored_file|null
     */
    public static function resolve_eligible_file_from_url(string $imageurl): ?\stored_file {
        $file = \local_dixeo\service\image\pluginfile_helper::get_stored_file_from_pluginfile_url($imageurl);
        if (!$file || !self::is_eligible_stored_file($file)) {
            return null;
        }
        return $file;
    }

    /**
     * Whether the component and file area pair is denied.
     *
     * Course overview files and Dixeo chapter covers stay excluded. Tiles
     * section photos are the exception: the course page editor may upload,
     * generate, and replace them, including when the tile still shows an icon.
     *
     * @param string $component
     * @param string $filearea
     * @return bool True when the file must be excluded.
     */
    public static function is_denied_component_filearea(string $component, string $filearea): bool {
        if ($component === 'format_tiles' && $filearea === 'tilephoto') {
            return false;
        }

        $denylist = [
            ['course', 'overviewfiles'],
            ['format_dixeo', 'chapterimage'],
            ['block_dixeo_designer', 'generated_images'],
            ['user', 'icon'],
            ['user', 'profile'],
            ['user', 'newicon'],
        ];

        foreach ($denylist as [$comp, $area]) {
            if ($component === $comp && $filearea === $area) {
                return true;
            }
        }

        if (strpos($component, 'theme_') === 0) {
            return true;
        }

        if ($component === 'mod_h5pactivity') {
            return true;
        }

        if ($component === 'contentbank' || strpos($filearea, 'h5p') !== false) {
            return true;
        }

        return false;
    }
}
