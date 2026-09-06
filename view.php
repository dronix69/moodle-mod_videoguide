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
 * Student view page for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $SITE;

$id = optional_param('id', 0, PARAM_INT);
$v  = optional_param('v', 0, PARAM_INT);
$toggle = optional_param('toggle', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('videoguide', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $videoguide = $DB->get_record('videoguide', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($v) {
    $videoguide = $DB->get_record('videoguide', ['id' => $v], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $videoguide->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('videoguide', $videoguide->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingparam', 'error', '', 'id or v');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoguide:view', $context);

if ($toggle && confirm_sesskey()) {
    $result = videoguide_toggle_viewed($toggle, $USER->id);
    echo json_encode(['status' => 'success', 'viewed' => $result]);
    exit;
}

$PAGE->set_url('/mod/videoguide/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($videoguide->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_videoguide/videoguide', 'init');

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$event = \mod_videoguide\event\course_module_viewed::create([
    'context' => $context,
    'objectid' => $videoguide->id,
]);
$event->add_record_snapshot('videoguide', $videoguide);
$event->trigger();

$videos = videoguide_get_videos($videoguide->id);
$progress = videoguide_get_user_progress($videoguide->id, $USER->id);
$canmanage = has_capability('mod/videoguide:managevideos', $context);

$displaymethod = !empty($videoguide->displaymethod) &&
    in_array($videoguide->displaymethod, videoguide_get_display_methods()) ? $videoguide->displaymethod : 'newtab';

$videodata = [];
$totalvideos = 0;
$viewedcount = 0;

foreach ($videos as $video) {
    if (!$canmanage && !$video->enabled) {
        continue;
    }

    $isviewed = !empty($progress[$video->id]);
    if ($isviewed) {
        $viewedcount++;
    }
    $totalvideos++;

    $platformicon = '';
    $platformclass = '';
    switch ($video->platform) {
        case 'youtube':
            $platformicon = 'fa-youtube';
            $platformclass = 'platform-youtube';
            break;
        case 'zoom':
            $platformicon = 'fa-video';
            $platformclass = 'platform-zoom';
            break;
        case 'meet':
            $platformicon = 'fa-google';
            $platformclass = 'platform-meet';
            break;
    }

    $toggleurl = new moodle_url('/mod/videoguide/view.php', [
        'id' => $cm->id,
        'toggle' => $video->id,
        'sesskey' => sesskey(),
    ]);

    $videodata[] = [
        'id' => $video->id,
        'title' => format_string($video->title),
        'url' => $video->url,
        'embedurl' => videoguide_get_embed_url($video->url, $video->platform),
        'platform' => $video->platform,
        'platformicon' => $platformicon,
        'platformclass' => $platformclass,
        'description' => format_text($video->description),
        'enabled' => $video->enabled,
        'required' => $video->required,
        'viewed' => $isviewed,
        'newtab' => ($displaymethod === 'newtab'),
        'toggleurl' => $toggleurl->out(false),
    ];
}

$progresspercent = $totalvideos > 0 ? round(($viewedcount / $totalvideos) * 100) : 0;

$output = $PAGE->get_renderer('mod_videoguide');
$renderable = new \mod_videoguide\output\view_page(
    $videoguide,
    $videodata,
    $progresspercent,
    $viewedcount,
    $totalvideos,
    $canmanage,
    $displaymethod
);

echo $output->header();
echo $output->render($renderable);
echo $output->footer();
