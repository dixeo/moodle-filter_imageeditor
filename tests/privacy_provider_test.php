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
 * Privacy provider tests for filter_dixeo_imageeditor.
 *
 * @package    filter_dixeo_imageeditor
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_dixeo_imageeditor;

use context_module;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use filter_dixeo_imageeditor\adapter\file_replacer;
use filter_dixeo_imageeditor\privacy\provider;
use local_dixeo\service\image\content\location;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/page/lib.php');

/**
 * Privacy provider tests.
 *
 * @covers \filter_dixeo_imageeditor\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
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

    /**
     * Create a page module with an embedded PNG owned by the current user.
     *
     * @return array{0: location, 1: \context_module}
     */
    private function create_page_image_location(): array {
        global $USER;

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

        return [location::from_stored_file($file), $context];
    }

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    public function test_export_user_data_includes_history_files(): void {
        global $USER;

        [$location, $modcontext] = $this->create_page_image_location();
        $originalhash = $location->get_stored_file()->get_contenthash();

        file_replacer::apply_binary(
            $location,
            self::fixture_jpeg_bytes(),
            (int) $USER->id,
            file_replacer::SOURCE_GENERATED
        );

        $history = file_replacer::get_history_for_location($location);
        $this->assertCount(1, $history);
        $versionid = (int) $history[0]['id'];

        $systemcontext = \context_system::instance();
        $fs = get_file_storage();
        $archived = $fs->get_area_files(
            $systemcontext->id,
            'filter_dixeo_imageeditor',
            'history',
            $versionid,
            'itemid, filepath, filename',
            false
        );
        $this->assertCount(1, $archived);
        $archivedfile = reset($archived);
        $this->assertSame($originalhash, $archivedfile->get_contenthash());

        $contextlist = new approved_contextlist($USER, 'filter_dixeo_imageeditor', [$modcontext->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($modcontext);
        $subcontext = [get_string('privacy:pathversions', 'filter_dixeo_imageeditor')];
        $data = $writer->get_data($subcontext);
        $this->assertNotEmpty($data->versions);
        $this->assertCount(1, $data->versions);

        $files = $writer->get_files($subcontext);
        $this->assertNotEmpty($files);
        $exported = reset($files);
        $this->assertInstanceOf(\stored_file::class, $exported);
        $this->assertSame($originalhash, $exported->get_contenthash());
        $this->assertSame($archivedfile->get_filename(), $exported->get_filename());
    }
}
