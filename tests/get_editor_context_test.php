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
 * Tests for get_editor_context external.
 *
 * @package    filter_dixeo_imageeditor
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_dixeo_imageeditor;

use context_module;
use filter_dixeo_imageeditor\external\get_editor_context;
use filter_dixeo_imageeditor\adapter\image_util;
use local_dixeo\service\image\content\location;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\policy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/page/lib.php');

/**
 * Tests for the get_editor_context external function.
 *
 * @covers \filter_dixeo_imageeditor\external\get_editor_context
 */
final class get_editor_context_test extends \advanced_testcase {
    /**
     * Return PNG bytes from core filestorage fixtures.
     */
    private static function fixture_png_bytes(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.png');
    }

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        filter_set_global_state('dixeo_imageeditor', TEXTFILTER_ON);
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE_EDIT, 'local_dixeo');
    }

    /**
     * Create a page module with an embedded PNG for tests.
     *
     * @return array{0: location, 1: int}
     */
    private function create_page_image_location(): array {
        global $USER;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $context = context_module::instance($page->cmid);

        $draftitemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'embedded.png',
        ], self::fixture_png_bytes());

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_page',
            'content',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );

        $file = $fs->get_file($context->id, 'mod_page', 'content', 0, '/', 'embedded.png');
        $this->assertNotFalse($file);

        return [location::from_stored_file($file), (int) $course->id];
    }

    public function test_get_editor_context_returns_expected_payload(): void {
        [$location, $courseid] = $this->create_page_image_location();

        $result = get_editor_context::execute(
            $location->contextid,
            $location->component,
            $location->filearea,
            $location->itemid,
            $location->filepath,
            $location->filename,
            $courseid
        );

        $this->assertNotEmpty($result['imageurl']);
        $this->assertNotEmpty($result['current_contenthash']);
        $this->assertIsArray($result['history']);
        $this->assertTrue($result['policy_can_generate']);
        $this->assertTrue($result['policy_can_edit']);
        $this->assertTrue($result['cap_can_generate']);
        $this->assertTrue($result['cap_can_edit']);
        $this->assertFalse($result['locked']);
        $this->assertSame(image_util::get_web_image_accept_attribute(), $result['upload_accept']);
        $this->assertArrayHasKey('status', $result['location_status']);
    }

    public function test_get_editor_context_reports_locked_flag(): void {
        global $USER;

        [$location, $courseid] = $this->create_page_image_location();
        job_repository::create_job($location, 'job-123', (int) $USER->id);

        $result = get_editor_context::execute(
            $location->contextid,
            $location->component,
            $location->filearea,
            $location->itemid,
            $location->filepath,
            $location->filename,
            $courseid
        );

        $this->assertTrue($result['locked']);
        $this->assertSame(job_repository::STATUS_PENDING, $result['location_status']['status']);
    }

    /**
     * Data provider for content mode policy flag tests.
     *
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function content_mode_policy_provider(): array {
        return [
            'disabled' => [policy::MODE_DISABLED, false, false],
            'generate' => [policy::MODE_GENERATE, true, false],
            'generate_edit' => [policy::MODE_GENERATE_EDIT, true, true],
        ];
    }

    /**
     * Test editor context policy flags per content mode.
     *
     * @param string $mode
     * @param bool $expectgenerate
     * @param bool $expectedit
     * @dataProvider content_mode_policy_provider
     */
    public function test_get_editor_context_policy_flags_per_content_mode(
        string $mode,
        bool $expectgenerate,
        bool $expectedit
    ): void {
        set_config('image_generation_content_mode', $mode, 'local_dixeo');

        [$location, $courseid] = $this->create_page_image_location();

        $result = get_editor_context::execute(
            $location->contextid,
            $location->component,
            $location->filearea,
            $location->itemid,
            $location->filepath,
            $location->filename,
            $courseid
        );

        $this->assertSame($expectgenerate, $result['policy_can_generate']);
        $this->assertSame($expectedit, $result['policy_can_edit']);
    }
}
