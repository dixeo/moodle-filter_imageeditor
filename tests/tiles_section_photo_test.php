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
 * Tests for tiles section photo create and replace.
 *
 * @package    filter_dixeo_imageeditor
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_dixeo_imageeditor;

use filter_dixeo_imageeditor\adapter\file_replacer;
use filter_dixeo_imageeditor\adapter\tiles_section_photo;
use format_tiles\local\format_option;
use local_dixeo\service\image\content\location;

/**
 * Tests for tiles section photo create and replace.
 *
 * @covers \filter_dixeo_imageeditor\adapter\tiles_section_photo
 * @covers \filter_dixeo_imageeditor\adapter\file_replacer
 */
final class tiles_section_photo_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        if (!class_exists(\format_tiles\local\tile_photo::class)) {
            $this->markTestSkipped('format_tiles is not installed');
        }
        set_config('allowphototiles', 1, 'format_tiles');
    }

    /**
     * PNG bytes accepted by the image editor.
     */
    private static function fixture_png_bytes(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.png');
    }

    /**
     * Create a tiles course and return its first content section.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \context_course}
     */
    private function create_tiles_section(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 2]);
        $DB->set_field('course', 'format', 'tiles', ['id' => $course->id]);
        $course = get_course($course->id);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        return [$course, $section, $context];
    }

    /**
     * Build the reserved location for a section that has no photo yet.
     *
     * @param \context_course $context
     * @param \stdClass $section
     * @param int $courseid
     * @return location
     */
    private function pending_location(\context_course $context, \stdClass $section, int $courseid): location {
        return new location(
            (int) $context->id,
            'format_tiles',
            'tilephoto',
            (int) $section->id,
            '/tilephoto/',
            tiles_section_photo::pending_filename((int) $section->id),
            $courseid
        );
    }

    public function test_first_image_replaces_section_icon(): void {
        global $USER;

        $this->setAdminUser();
        [$course, $section, $context] = $this->create_tiles_section();
        format_option::set(
            (int) $course->id,
            format_option::OPTION_SECTION_ICON,
            (int) $section->id,
            'smile-o'
        );

        $location = $this->pending_location($context, $section, (int) $course->id);
        file_replacer::apply_binary($location, self::fixture_png_bytes(), (int) $USER->id, file_replacer::SOURCE_UPLOAD);

        $stored = $location->get_stored_file();
        $this->assertNotNull($stored);
        $this->assertSame(tiles_section_photo::pending_filename((int) $section->id), $stored->get_filename());
        $this->assertSame(
            $stored->get_filename(),
            format_option::get((int) $course->id, format_option::OPTION_SECTION_PHOTO, (int) $section->id)
        );
        $this->assertNull(
            format_option::get((int) $course->id, format_option::OPTION_SECTION_ICON, (int) $section->id)
        );
    }

    public function test_existing_section_photo_is_replaced_in_place(): void {
        global $CFG, $USER;

        $this->setAdminUser();
        [$course, $section, $context] = $this->create_tiles_section();
        $location = $this->pending_location($context, $section, (int) $course->id);
        file_replacer::apply_binary($location, self::fixture_png_bytes(), (int) $USER->id, file_replacer::SOURCE_GENERATED);
        $original = $location->get_stored_file();
        $this->assertNotNull($original);

        $jpeg = (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.jpg');
        file_replacer::apply_binary($location, $jpeg, (int) $USER->id, file_replacer::SOURCE_UPLOAD);

        $replaced = $location->get_stored_file();
        $this->assertNotNull($replaced);
        $this->assertSame($original->get_filename(), $replaced->get_filename());
        $this->assertNotSame($original->get_contenthash(), $replaced->get_contenthash());
        $this->assertSame(
            $replaced->get_filename(),
            format_option::get((int) $course->id, format_option::OPTION_SECTION_PHOTO, (int) $section->id)
        );
    }

    public function test_unreserved_filename_without_a_file_is_rejected(): void {
        $this->setAdminUser();
        [$course, $section, $context] = $this->create_tiles_section();
        $location = new location(
            (int) $context->id,
            'format_tiles',
            'tilephoto',
            (int) $section->id,
            '/tilephoto/',
            'other-photo.jpg',
            (int) $course->id
        );

        $this->expectException(\moodle_exception::class);
        file_replacer::apply_binary($location, self::fixture_png_bytes(), 2, file_replacer::SOURCE_UPLOAD);
    }
}
