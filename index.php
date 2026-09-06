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
 * List all videoguide instances in a course.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('mod/videoguide:view', $context);

$PAGE->set_url('/mod/videoguide/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

if (!$cms = get_coursemodules_in_course('videoguide', $course->id)) {
    echo $OUTPUT->notification(get_string('novideos', 'mod_videoguide'));
    echo $OUTPUT->footer();
    exit;
}

$instanceids = array_map(fn($cm) => $cm->instance, $cms);

if (!empty($instanceids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
    $allguides = $DB->get_records_list('videoguide', 'id', $instanceids);
    $allvideos = $DB->get_records_select('videoguide_videos', "videoguideid $insql", $inparams);
    $allprogress = $DB->get_records_select(
        'videoguide_progress',
        "videoguideid $insql AND userid = :userid",
        array_merge($inparams, ['userid' => $USER->id])
    );
} else {
    $allguides = [];
    $allvideos = [];
    $allprogress = [];
}

$videosbyguide = [];
foreach ($allvideos as $v) {
    $videosbyguide[$v->videoguideid][] = $v;
}

$progressbyguide = [];
foreach ($allprogress as $p) {
    $progressbyguide[$p->videoguideid][$p->videoid] = $p->viewed;
}

$table = new html_table();
$table->head = [
    get_string('videoguidename', 'mod_videoguide'),
    get_string('videos', 'mod_videoguide'),
    get_string('yourprogress', 'mod_videoguide'),
];

foreach ($cms as $cm) {
    $guidevideos = $videosbyguide[$cm->instance] ?? [];
    $guideprogress = $progressbyguide[$cm->instance] ?? [];
    $total = count($guidevideos);
    $viewed = count(array_filter($guideprogress, fn($v) => $v == 1));
    $pct = $total > 0 ? round(($viewed / $total) * 100) . '%' : '-';

    $url = new moodle_url('/mod/videoguide/view.php', ['id' => $cm->id]);
    $table->data[] = [
        html_writer::link($url, format_string($allguides[$cm->instance]->name ?? '')),
        $total,
        "{$viewed} / {$total} ({$pct})",
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
