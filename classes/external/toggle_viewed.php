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
 * External API for toggling video viewed status.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_videoguide\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/../../lib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class toggle_viewed extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'videoid' => new external_value(PARAM_INT, 'Video ID'),
            'sesskey' => new external_value(PARAM_RAW, 'Session key'),
        ]);
    }

    public static function execute($videoid, $sesskey) {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'videoid' => $videoid,
            'sesskey' => $sesskey,
        ]);

        require_sesskey();

        $video = $DB->get_record('videoguide_videos', ['id' => $params['videoid']], '*', MUST_EXIST);
        $videoguide = $DB->get_record('videoguide', ['id' => $video->videoguideid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('videoguide', $videoguide->id);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/videoguide:view', $context);

        $result = videoguide_toggle_viewed($params['videoid'], $USER->id);

        return ['viewed' => $result];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'viewed' => new external_value(PARAM_INT, 'New viewed status'),
        ]);
    }
}
