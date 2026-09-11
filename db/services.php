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
 * External functions for filter_dixeo_imageeditor.
 *
 * @package    filter_dixeo_imageeditor
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'filter_dixeo_imageeditor_get_editor_context' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\get_editor_context',
        'methodname' => 'execute',
        'description' => 'Get editor modal context for one image location',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
    'filter_dixeo_imageeditor_start_generate' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\start_generate',
        'methodname' => 'execute',
        'description' => 'Start async content image generate job',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
    'filter_dixeo_imageeditor_start_edit' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\start_edit',
        'methodname' => 'execute',
        'description' => 'Start async content image edit job',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
    'filter_dixeo_imageeditor_get_location_status' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\get_location_status',
        'methodname' => 'execute',
        'description' => 'Poll lock status for UX overlay',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
    'filter_dixeo_imageeditor_revert_version' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\revert_version',
        'methodname' => 'execute',
        'description' => 'Revert image to archived version',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
    'filter_dixeo_imageeditor_delete_version' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\delete_version',
        'methodname' => 'execute',
        'description' => 'Delete one archived version from history',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
    'filter_dixeo_imageeditor_apply_manual_edit' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\apply_manual_edit',
        'methodname' => 'execute',
        'description' => 'Apply browser-edited image bytes and archive previous version',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
    'filter_dixeo_imageeditor_apply_upload' => [
        'classname' => 'filter_dixeo_imageeditor\\external\\apply_upload',
        'methodname' => 'execute',
        'description' => 'Apply an uploaded image and archive previous version',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'filter/dixeo_imageeditor:edit',
        'loginrequired' => true,
    ],
];
