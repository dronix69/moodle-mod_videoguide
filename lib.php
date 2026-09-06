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
 * Library of core functions for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Return the list of features supported by this module.
 */
function videoguide_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Add a new videoguide instance.
 */
function videoguide_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = time();
    if (empty($moduleinstance->displaymethod) || !in_array($moduleinstance->displaymethod, videoguide_get_display_methods())) {
        $moduleinstance->displaymethod = 'newtab';
    }

    $id = $DB->insert_record('videoguide', $moduleinstance);

    // Save associated videos.
    videoguide_save_videos($id, $moduleinstance);

    return $id;
}

/**
 * Update an existing instance.
 */
function videoguide_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    if (empty($moduleinstance->displaymethod) || !in_array($moduleinstance->displaymethod, videoguide_get_display_methods())) {
        $moduleinstance->displaymethod = 'newtab';
    }

    $DB->update_record('videoguide', $moduleinstance);

    // Update associated videos.
    videoguide_save_videos($moduleinstance->instance, $moduleinstance);

    return true;
}

/**
 * Delete an instance.
 */
function videoguide_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('videoguide', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('videoguide_videos', ['videoguideid' => $id]);
    $DB->delete_records('videoguide_progress', ['videoguideid' => $id]);
    $DB->delete_records('videoguide', ['id' => $id]);

    return true;
}

/**
 * Save/update videos from the form.
 */
function videoguide_save_videos($videoguideid, $data) {
    global $DB;

    $DB->delete_records('videoguide_videos', ['videoguideid' => $videoguideid]);

    $titles = optional_param_array('video_title', [], PARAM_TEXT);
    $urls = optional_param_array('video_url', [], PARAM_URL);
    $platforms = optional_param_array('video_platform', [], PARAM_TEXT);
    $descriptions = optional_param_array('video_description', [], PARAM_TEXT);
    $enableds = optional_param_array('video_enabled', [], PARAM_INT);
    $requireds = optional_param_array('video_required', [], PARAM_INT);

    if (empty($titles)) {
        return;
    }

    $count = count($titles);

    for ($i = 0; $i < $count; $i++) {
        if (empty($titles[$i])) {
            continue;
        }

        $video = new stdClass();
        $video->videoguideid = $videoguideid;
        $video->title        = $titles[$i];
        $video->url          = $urls[$i] ?? '';
        $video->platform     = $platforms[$i] ?? 'youtube';
        $video->description  = $descriptions[$i] ?? '';
        $video->enabled      = (isset($enableds[$i]) && $enableds[$i] == 1) ? 1 : 0;
        $video->required     = (isset($requireds[$i]) && $requireds[$i] == 1) ? 1 : 0;
        $video->sortorder    = $i;
        $video->timecreated  = time();

        $DB->insert_record('videoguide_videos', $video);
    }
}

/**
 * Get videos for an instance.
 */
function videoguide_get_videos($videoguideid) {
    global $DB;
    return $DB->get_records('videoguide_videos', ['videoguideid' => $videoguideid], 'sortorder ASC');
}

/**
 * Valid display (opening) methods for the activity.
 */
function videoguide_get_display_methods() {
    return ['newtab', 'modal', 'popup'];
}

/**
 * Build an embeddable URL for the given video, when possible.
 *
 * @param string $url Original video URL.
 * @param string $platform One of: youtube, zoom, meet.
 * @return string URL suitable for an iframe, or the original URL as fallback.
 */
function videoguide_get_embed_url($url, $platform) {
    if ($platform === 'youtube') {
        if (preg_match('~^https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $matches)) {
            return 'https://www.youtube-nocookie.com/embed/' . $matches[1] . '?rel=0';
        }
    }
    return $url;
}

/**
 * Get user progress for an instance.
 */
function videoguide_get_user_progress($videoguideid, $userid) {
    global $DB;
    return $DB->get_records_menu(
        'videoguide_progress',
        ['videoguideid' => $videoguideid, 'userid' => $userid],
        '',
        'videoid, viewed'
    );
}

/**
 * Toggle video viewed status.
 */
function videoguide_toggle_viewed($videoid, $userid) {
    global $DB;

    $video = $DB->get_record('videoguide_videos', ['id' => $videoid]);
    if (!$video) {
        return false;
    }

    $existing = $DB->get_record('videoguide_progress', [
        'videoid' => $videoid,
        'userid' => $userid,
    ]);

    if ($existing) {
        $existing->viewed = $existing->viewed ? 0 : 1;
        $existing->timemodified = time();
        $DB->update_record('videoguide_progress', $existing);
        return $existing->viewed;
    } else {
        $record = new stdClass();
        $record->videoguideid = $video->videoguideid;
        $record->videoid = $videoid;
        $record->userid = $userid;
        $record->viewed = 1;
        $record->timemodified = time();
        $DB->insert_record('videoguide_progress', $record);
        return 1;
    }
}
