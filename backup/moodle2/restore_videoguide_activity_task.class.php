<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restore task for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoguide/backup/moodle2/restore_videoguide_stepslib.php');

/**
 * Restore task for the mod_videoguide module.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_videoguide_activity_task extends restore_activity_task {
    /**
     * Define the restore settings for the activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the restore steps for the activity.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_videoguide_activity_structure_step('videoguide_structure', 'videoguide.xml'));
    }

    /**
     * Define the decode contents for the activity.
     *
     * @return array The decode contents.
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Define the decode rules for the activity.
     *
     * @return array The decode rules.
     */
    public static function define_decode_rules() {
        return [];
    }

    /**
     * Define the restore log rules for the activity.
     *
     * @return array The restore log rules.
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Define the restore log rules for the course.
     *
     * @return array The restore log rules.
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
