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
 * Privacy API implementation for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_videoguide\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\core_userlist_provider, \core_privacy\local\request\plugin\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'videoguide_progress',
            [
                'videoguideid' => 'privacy:metadata:videoguide_progress:videoguideid',
                'videoid' => 'privacy:metadata:videoguide_progress:videoid',
                'userid' => 'privacy:metadata:videoguide_progress:userid',
                'viewed' => 'privacy:metadata:videoguide_progress:viewed',
                'timemodified' => 'privacy:metadata:videoguide_progress:timemodified',
            ],
            'privacy:metadata:videoguide_progress'
        );
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoguide} vg ON vg.id = cm.instance
                  JOIN {videoguide_progress} vp ON vp.videoguideid = vg.id
                 WHERE vp.userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videoguide',
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT vp.userid
                  FROM {videoguide_progress} vp
                  JOIN {course_modules} cm ON cm.instance = vp.videoguideid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoguide', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $progress = self::get_progress_for_user($cm->instance, $userid);
            if (!empty($progress)) {
                $data = (object) [
                    'videos_viewed' => $progress,
                ];
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:videoguide_progress', 'mod_videoguide')],
                    $data
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('videoguide', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_progress_for_instance($cm->instance);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoguide', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_progress_for_user($cm->instance, $userid);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('videoguide', $context->instanceid);
        if (!$cm) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_progress_for_user($cm->instance, $userid);
        }
    }

    private static function get_progress_for_user(int $videoguideid, int $userid): array {
        global $DB;
        return $DB->get_records_menu(
            'videoguide_progress',
            ['videoguideid' => $videoguideid, 'userid' => $userid],
            '',
            'videoid, viewed'
        );
    }

    private static function delete_progress_for_instance(int $videoguideid) {
        global $DB;
        $DB->delete_records('videoguide_progress', ['videoguideid' => $videoguideid]);
    }

    private static function delete_progress_for_user(int $videoguideid, int $userid) {
        global $DB;
        $DB->delete_records('videoguide_progress', [
            'videoguideid' => $videoguideid,
            'userid' => $userid,
        ]);
    }
}
