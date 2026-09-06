<?php
// mod/videoguide/db/upgrade.php
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
 * Database upgrade script for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_videoguide_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061100) {
        // New tables for videoguide plugin
        $table = new xmldb_table('videoguide');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', array('primary' => true, 'notnull' => true, 'sequence' => true));
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', array('notnull' => true, 'sequence' => false));
        $table->add_field('intro', XMLDB_TYPE_TEXT, null, array('notnull' => false, 'sequence' => false));
        $table->add_field('introformat', XMLDB_TYPE_INTEGER, '4', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));

        $table->add_index('course', XMLDB_INDEX_UNIQUE, false, 'course');

        $dbman->create_table($table);

        $table = new xmldb_table('videoguide_videos');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', array('primary' => true, 'notnull' => true, 'sequence' => true));
        $table->add_field('videoguideid', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', array('notnull' => true, 'sequence' => false));
        $table->add_field('url', XMLDB_TYPE_TEXT, null, array('notnull' => true, 'sequence' => false));
        $table->add_field('platform', XMLDB_TYPE_CHAR, '20', array('notnull' => true, 'default' => 'youtube', 'sequence' => false));
        $table->add_field('description', XMLDB_TYPE_TEXT, null, array('notnull' => false, 'sequence' => false));
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', array('notnull' => true, 'default' => 1, 'sequence' => false));
        $table->add_field('required', XMLDB_TYPE_INTEGER, '1', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));

        $table->add_key('videoguideid', XMLDB_KEY_FOREIGN, 'videoguide', array('videoguideid' => 'id'));
        $table->add_index('videoguideid', XMLDB_INDEX_UNIQUE, false, 'videoguideid');

        $dbman->create_table($table);

        $table = new xmldb_table('videoguide_progress');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', array('primary' => true, 'notnull' => true, 'sequence' => true));
        $table->add_field('videoguideid', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('videoid', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('viewed', XMLDB_TYPE_INTEGER, '1', array('notnull' => true, 'default' => 0, 'sequence' => false));
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', array('notnull' => true, 'default' => 0, 'sequence' => false));

        $table->add_index('videoid_userid', XMLDB_INDEX_UNIQUE, true, 'videoid, userid');

        $dbman->create_table($table);
    }

    return $oldversion;
}
