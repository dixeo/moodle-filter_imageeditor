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
 * Tests for content image audit events.
 *
 * @package    filter_dixeo_imageeditor
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_dixeo_imageeditor;

use context_module;
use filter_dixeo_imageeditor\adapter\file_replacer;
use filter_dixeo_imageeditor\event\content_image_job_started;
use filter_dixeo_imageeditor\event\content_image_updated;
use filter_dixeo_imageeditor\event\content_image_version_deleted;
use filter_dixeo_imageeditor\external\start_generate;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\policy;
use local_dixeo\service\image_generation_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/page/lib.php');

/**
 * Tests for content image audit events.
 *
 * @covers \filter_dixeo_imageeditor\event\content_image_updated
 * @covers \filter_dixeo_imageeditor\event\content_image_version_deleted
 * @covers \filter_dixeo_imageeditor\event\content_image_job_started
 */
final class content_image_events_test extends \advanced_testcase {
    /**
     * Return PNG bytes from core filestorage fixtures.
     */
    private static function fixture_png_bytes(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.png');
    }

    /**
     * Return JPEG bytes from core filestorage fixtures.
     */
    private static function fixture_jpeg_bytes(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.jpg');
    }

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        filter_set_global_state('dixeo_imageeditor', TEXTFILTER_ON);
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE_EDIT, 'local_dixeo');
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
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

    public function test_apply_binary_triggers_updated_event(): void {
        global $USER;

        [$location] = $this->create_page_image_location();
        $sink = $this->redirectEvents();

        file_replacer::apply_binary(
            $location,
            self::fixture_jpeg_bytes(),
            (int) $USER->id,
            file_replacer::SOURCE_UPLOAD
        );

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(content_image_updated::class, $events[0]);
        $this->assertSame(file_replacer::SOURCE_UPLOAD, $events[0]->other['source']);
    }

    public function test_delete_version_triggers_deleted_event(): void {
        global $USER;

        [$location] = $this->create_page_image_location();

        file_replacer::apply_binary(
            $location,
            self::fixture_jpeg_bytes(),
            (int) $USER->id,
            file_replacer::SOURCE_UPLOAD
        );

        $history = file_replacer::get_history_for_location($location);
        $versionid = (int) $history[0]['id'];

        $sink = $this->redirectEvents();
        file_replacer::delete_version($versionid, $location, (int) $USER->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(content_image_version_deleted::class, $events[0]);
        $this->assertSame($versionid, (int) $events[0]->other['versionid']);
    }

    public function test_start_generate_triggers_job_started_event(): void {
        [$location, $courseid] = $this->create_page_image_location();

        $mock = $this->createMock(image_generation_service::class);
        $mock->method('submit_content_image_generate_job')
            ->willReturn(operation_result::pending('remote-job-evt', 'pending', 0));
        service_factory::set_test_image_generation_service($mock);

        $sink = $this->redirectEvents();

        start_generate::execute(
            $location->contextid,
            $location->component,
            $location->filearea,
            $location->itemid,
            $location->filepath,
            $location->filename,
            $courseid,
            'A scenic mountain'
        );

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(content_image_job_started::class, $events[0]);
        $this->assertSame('generate', $events[0]->other['mode']);
        $this->assertTrue(job_repository::has_blocking_job($location));
    }
}
