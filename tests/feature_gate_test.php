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
 * Tests for feature_gate.
 *
 * @package    filter_dixeo_imageeditor
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_dixeo_imageeditor;

use filter_dixeo_imageeditor\adapter\feature_gate;
use local_dixeo\service\image\policy;

/**
 * Tests for feature gate checks.
 *
 * @covers \filter_dixeo_imageeditor\adapter\feature_gate
 */
final class feature_gate_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_is_globally_enabled_when_filter_disabled(): void {
        filter_set_global_state('dixeo_imageeditor', TEXTFILTER_DISABLED);
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE_EDIT, 'local_dixeo');

        $this->assertFalse(feature_gate::is_globally_enabled());
    }

    public function test_is_globally_enabled_when_filter_off_but_available(): void {
        filter_set_global_state('dixeo_imageeditor', TEXTFILTER_OFF);
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE_EDIT, 'local_dixeo');

        $this->assertTrue(feature_gate::is_globally_enabled());
    }

    public function test_is_globally_enabled_when_content_mode_disabled(): void {
        filter_set_global_state('dixeo_imageeditor', TEXTFILTER_ON);
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_DISABLED, 'local_dixeo');

        $this->assertTrue(feature_gate::is_globally_enabled());
    }

    public function test_is_globally_enabled_when_global_dixeo_image_off(): void {
        filter_set_global_state('dixeo_imageeditor', TEXTFILTER_ON);
        set_config('image_generation_enabled', 0, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_DISABLED, 'local_dixeo');

        $this->assertTrue(feature_gate::is_globally_enabled());
    }
}
