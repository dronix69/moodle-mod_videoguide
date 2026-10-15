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

/**
 * Return the list of features supported by this module.
 *
 * @param string $feature The feature to check.
 * @return bool|null Whether the feature is supported, or null if unknown.
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
 *
 * @param stdClass $moduleinstance The data submitted from the form.
 * @param mod_videoguide_mod_form $mform The form object.
 * @return int The id of the newly created instance.
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
 *
 * @param stdClass $moduleinstance The data submitted from the form.
 * @param mod_videoguide_mod_form $mform The form object.
 * @return bool True on success.
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
 *
 * @param int $id The id of the instance to delete.
 * @return bool True on success.
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
 *
 * @param int $videoguideid The id of the videoguide instance.
 * @param stdClass $data The data submitted from the form.
 */
function videoguide_save_videos($videoguideid, $data) {
    global $DB;

    $titles       = isset($data->video_title) ? (array) $data->video_title : [];
    $ids          = isset($data->video_id) ? (array) $data->video_id : [];
    $urls         = isset($data->video_url) ? (array) $data->video_url : [];
    $platforms    = isset($data->video_platform) ? (array) $data->video_platform : [];
    $descriptions = isset($data->video_description) ? (array) $data->video_description : [];
    $enableds     = isset($data->video_enabled) ? (array) $data->video_enabled : [];
    $requireds    = isset($data->video_required) ? (array) $data->video_required : [];

    // Existing videos, keyed by id, so we can preserve ids and student progress.
    $existing = $DB->get_records('videoguide_videos', ['videoguideid' => $videoguideid], '', 'id');
    $kept = [];
    $sortorder = 0;

    foreach ($titles as $index => $title) {
        $title = trim((string) $title);
        if ($title === '') {
            continue;
        }

        $videoid = isset($ids[$index]) ? (int) $ids[$index] : 0;

        $video = new stdClass();
        $video->videoguideid = $videoguideid;
        $video->title        = $title;
        $video->url          = (string) ($urls[$index] ?? '');
        $video->platform     = (string) ($platforms[$index] ?? 'youtube');
        $video->description  = (string) ($descriptions[$index] ?? '');
        $video->enabled      = !empty($enableds[$index]) ? 1 : 0;
        $video->required     = !empty($requireds[$index]) ? 1 : 0;
        $video->sortorder    = $sortorder++;

        if ($videoid && isset($existing[$videoid])) {
            // Update in place: the video id is preserved and progress is kept.
            $video->id = $videoid;
            $DB->update_record('videoguide_videos', $video);
            $kept[$videoid] = true;
        } else {
            $video->timecreated = time();
            $newid = $DB->insert_record('videoguide_videos', $video);
            $kept[$newid] = true;
        }
    }

    // Remove videos the teacher deleted, along with their progress records.
    foreach ($existing as $videoid => $unused) {
        if (!isset($kept[$videoid])) {
            $DB->delete_records('videoguide_progress', ['videoid' => $videoid]);
            $DB->delete_records('videoguide_videos', ['id' => $videoid]);
        }
    }
}

/**
 * Get videos for an instance.
 *
 * @param int $videoguideid The id of the videoguide instance.
 * @return array The list of video records.
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
        $pattern = '~^https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~';
        if (preg_match($pattern, $url, $matches)) {
            return 'https://www.youtube-nocookie.com/embed/' . $matches[1] . '?rel=0';
        }
    }
    return $url;
}

/**
 * Get user progress for an instance.
 *
 * @param int $videoguideid The id of the videoguide instance.
 * @param int $userid The id of the user.
 * @return array The list of progress records.
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
 *
 * @param int $videoid The id of the video.
 * @param int $userid The id of the user.
 * @return bool|int The new viewed status, or false if the video does not exist.
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
