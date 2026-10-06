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
 * Tests for eligibility rules.
 *
 * @package    filter_dixeo_imageeditor
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_dixeo_imageeditor;

use filter_dixeo_imageeditor\adapter\eligibility;

/**
 * Tests for eligibility rules.
 *
 * @covers \filter_dixeo_imageeditor\adapter\eligibility
 */
final class eligibility_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_course_overview_is_denied(): void {
        $this->assertTrue(eligibility::is_denied_component_filearea('course', 'overviewfiles'));
    }

    public function test_chapterimage_is_denied(): void {
        $this->assertTrue(eligibility::is_denied_component_filearea('format_dixeo', 'chapterimage'));
    }

    public function test_tiles_section_photo_is_allowed(): void {
        $this->assertFalse(eligibility::is_denied_component_filearea('format_tiles', 'tilephoto'));
    }

    public function test_designer_generated_images_is_denied(): void {
        $this->assertTrue(eligibility::is_denied_component_filearea('block_dixeo_designer', 'generated_images'));
    }

    public function test_mod_page_content_is_allowed(): void {
        $this->assertFalse(eligibility::is_denied_component_filearea('mod_page', 'content'));
    }

    public function test_theme_component_is_denied(): void {
        $this->assertTrue(eligibility::is_denied_component_filearea('theme_boost', 'logo'));
    }
}
