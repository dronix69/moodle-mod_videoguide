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
 * Backup structure step for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class backup_videoguide_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $videoguide = new backup_nested_element('videoguide', ['id'], [
            'course', 'name', 'intro', 'introformat', 'displaymethod', 'timecreated', 'timemodified',
        ]);

        $videos = new backup_nested_element('videos');
        $video = new backup_nested_element('video', ['id'], [
            'videoguideid', 'title', 'url', 'platform', 'description',
            'enabled', 'required', 'sortorder', 'timecreated',
        ]);

        $progress = new backup_nested_element('progress');
        $progitem = new backup_nested_element('prog', ['id'], [
            'videoguideid', 'videoid', 'userid', 'viewed', 'timemodified',
        ]);

        $videoguide->add_child($videos);
        $videos->add_child($video);
        $videoguide->add_child($progress);
        $progress->add_child($progitem);

        $videoguide->set_source_table('videoguide', ['id' => backup::VAR_ACTIVITYID]);
        $video->set_source_table('videoguide_videos', ['videoguideid' => backup::VAR_PARENTID]);
        $progitem->set_source_table('videoguide_progress', ['videoguideid' => backup::VAR_PARENTID]);

        return $this->prepare_activity_structure($videoguide);
    }
}
