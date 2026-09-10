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

namespace filter_dixeo_imageeditor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use filter_dixeo_imageeditor\adapter\file_replacer;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\poll\client_poll;

/**
 * Poll lock status for UX overlay; may apply completed jobs via client_poll.
 *
 * @package    filter_dixeo_imageeditor
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_location_status extends external_api {
    use location_parameters;

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        $location = self::location_parameters_definition();
        return new external_function_parameters(array_merge($location->keys, [
            'acknowledge' => new external_value(PARAM_BOOL, 'Clear terminal lock after read', VALUE_DEFAULT, false),
        ]));
    }

    /**
     * Execute the get_location_status web service.
     *
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $filepath
     * @param string $filename
     * @param int $courseid
     * @param bool $acknowledge
     * @return array
     */
    public static function execute(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        int $courseid,
        bool $acknowledge = false
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => $filepath,
            'filename' => $filename,
            'courseid' => $courseid,
            'acknowledge' => $acknowledge,
        ]);

        $location = self::validate_location($params);
        $target = content_target::from_location($location);
        $job = job_repository::get_active_job($target);

        if (
            $job
            && in_array($job->status, [job_repository::STATUS_PENDING, job_repository::STATUS_PROCESSING], true)
        ) {
            $remotejobid = trim((string) ($job->jobid ?? ''));
            // Batch shortcode jobs are polled by adhoc tasks. Client-side remote
            // polls for every pending wrap flood the API key concurrency limit.
            // Modal (single-image) keeps an eager client poll for snappier UX.
            $origindeferstoremote = in_array(
                (string) ($job->origin ?? ''),
                [job_repository::ORIGIN_SHORTCODE, job_repository::ORIGIN_STRUCTURE],
                true
            );
            if ($remotejobid !== '' && !$origindeferstoremote) {
                client_poll::poll_once(
                    $remotejobid,
                    $target,
                    (int) $job->userid,
                    static function () use ($location): string {
                        return file_replacer::get_current_image_url($location);
                    }
                );
            }
        }

        return job_repository::get_location_status($location, (bool) $params['acknowledge']);
    }

    /**
     * Returns description of method results.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return self::location_status_returns();
    }
}
