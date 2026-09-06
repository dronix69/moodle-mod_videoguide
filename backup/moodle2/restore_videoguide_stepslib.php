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
 * Restore structure step for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class restore_videoguide_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('videoguide', '/activity/videoguide');
        $paths[] = new restore_path_element('videoguide_video', '/activity/videoguide/videos/video');

        if ($userinfo) {
            $paths[] = new restore_path_element('videoguide_progress', '/activity/videoguide/progress/prog');
        }

        return $this->prepare_activity_structure($paths);
    }

    public function process_videoguide($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = time();
        $data->timemodified = time();

        $newid = $DB->insert_record('videoguide', $data);
        $this->apply_activity_instance($newid);
    }

    public function process_videoguide_video($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->videoguideid = $this->get_new_parentid('videoguide');
        $data->timecreated = time();

        $newid = $DB->insert_record('videoguide_videos', $data);
        $this->set_mapping('videoguide_video', $oldid, $newid, true);
    }

    public function process_videoguide_progress($data) {
        global $DB;

        $data = (object)$data;
        $data->videoguideid = $this->get_new_parentid('videoguide');
        $data->videoid = $this->get_mappingid('videoguide_video', $data->videoid);
        $data->timemodified = time();

        $DB->insert_record('videoguide_progress', $data);
    }

    protected function after_execute() {
        $this->add_related_files('mod_videoguide', 'intro', null);
    }
}
