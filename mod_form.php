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
 * Teacher configuration form for mod_videoguide.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_videoguide_mod_form extends moodleform_mod {

    protected function definition() {
        global $DB;

        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('videoguidename', 'mod_videoguide'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Video section.
        $mform->addElement('header', 'videossection', get_string('videos', 'mod_videoguide'));

        // Get existing videos if editing.
        $videos = [];
        if ($this->_instance) {
            $videos = videoguide_get_videos($this->_instance);
        }

        // If no videos, create one empty to display.
        if (empty($videos)) {
            $videos = [(object)[
                'title' => '', 'url' => '', 'platform' => 'youtube',
                'description' => '', 'enabled' => 1, 'required' => 0
            ]];
        }

        // Video container.
        $mform->addElement('html', '<div id="videoguide-videos-container">');

        $platforms = [
            'youtube' => get_string('platform_youtube', 'mod_videoguide'),
            'zoom'    => get_string('platform_zoom', 'mod_videoguide'),
            'meet'    => get_string('platform_meet', 'mod_videoguide'),
        ];

        $videoindex = 0;
        foreach ($videos as $video) {
            $this->add_video_fieldset($mform, $videoindex, $video, $platforms);
            $videoindex++;
        }

        $mform->addElement('html', '</div>');

        // Button to add more videos.
        $mform->addElement('button', 'addvideo', get_string('addvideo', 'mod_videoguide'),
            ['id' => 'addvideo-btn', 'class' => 'btn btn-secondary videoguide-add-video']);

        // Pass platform options to the AMD module via data attribute on the container.
        $jsonplatforms = json_encode($platforms, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        $mform->addElement('html', '<div id="videoguide-form-data" data-platforms="' . s($jsonplatforms) . '"></div>');

        global $PAGE;
        $PAGE->requires->js_call_amd('mod_videoguide/form', 'init');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add a video fieldset to the form.
     */
    protected function add_video_fieldset($mform, $index, $video, $platforms) {
        $fieldsetid = 'video_fieldset_' . $index;

        $mform->addElement('html', '<div class="videoguide-video-item" id="' . $fieldsetid . '">');
        $mform->addElement('html', '<div class="card mb-3">');
        $mform->addElement('html', '<div class="card-header d-flex justify-content-between align-items-center">');
        $mform->addElement('html', '<span>' . get_string('video', 'mod_videoguide') . ' #' . ($index + 1) . '</span>');
        $mform->addElement('html', '<button type="button" class="btn btn-danger btn-sm videoguide-remove-video" data-fieldset="' . $fieldsetid . '">' . get_string('remove', 'moodle') . '</button>');
        $mform->addElement('html', '</div>');
        $mform->addElement('html', '<div class="card-body">');

        // Title.
        $mform->addElement('text', 'video_title[' . $index . ']', get_string('videotitle', 'mod_videoguide'));
        $mform->setType('video_title[' . $index . ']', PARAM_TEXT);
        $mform->setDefault('video_title[' . $index . ']', $video->title);

        // URL.
        $mform->addElement('text', 'video_url[' . $index . ']', get_string('videourl', 'mod_videoguide'));
        $mform->setType('video_url[' . $index . ']', PARAM_URL);
        $mform->setDefault('video_url[' . $index . ']', $video->url);

        // Platform.
        $mform->addElement('select', 'video_platform[' . $index . ']', get_string('platform', 'mod_videoguide'), $platforms);
        $mform->setDefault('video_platform[' . $index . ']', $video->platform);

        // Description.
        $mform->addElement('textarea', 'video_description[' . $index . ']', get_string('videodescription', 'mod_videoguide'), 
            ['rows' => 2, 'cols' => 50]);
        $mform->setType('video_description[' . $index . ']', PARAM_TEXT);
        $mform->setDefault('video_description[' . $index . ']', $video->description);

        // Visibility toggle (Bootstrap 5 btn-check).
        // Register hidden field so Moodle processes the POST value.
        $mform->addElement('hidden', "video_enabled[$index]", 0);
        $mform->setType("video_enabled[$index]", PARAM_INT);
        $mform->setDefault("video_enabled[$index]", $video->enabled);

        $visibleid = 'video_enabled_' . $index . '_visible';
        $hiddenid  = 'video_enabled_' . $index . '_hidden';
        $eyeon = $video->enabled ? 'checked' : '';
        $eyeoff = $video->enabled ? '' : 'checked';

        $eyehtml = '<div class="form-group row">';
        $eyehtml .= '<label class="col-form-label col-sm-3">' . get_string('visibility', 'mod_videoguide') . '</label>';
        $eyehtml .= '<div class="col-sm-9">';
        $eyehtml .= '<div class="btn-group" role="group">';
        $eyehtml .= '<input type="radio" class="btn-check" name="video_enabled[' . $index . ']" id="' . $visibleid . '" value="1" autocomplete="off" ' . $eyeon . '>';
        $eyehtml .= '<label class="btn btn-outline-success" for="' . $visibleid . '"><i class="fa fa-eye"></i> ' . get_string('visible', 'mod_videoguide') . '</label>';
        $eyehtml .= '<input type="radio" class="btn-check" name="video_enabled[' . $index . ']" id="' . $hiddenid . '" value="0" autocomplete="off" ' . $eyeoff . '>';
        $eyehtml .= '<label class="btn btn-outline-secondary" for="' . $hiddenid . '"><i class="fa fa-eye-slash"></i> ' . get_string('hidden', 'mod_videoguide') . '</label>';
        $eyehtml .= '</div></div></div>';

        $mform->addElement('html', $eyehtml);

        // Required.
        $mform->addElement('advcheckbox', 'video_required[' . $index . ']', get_string('required', 'mod_videoguide'));
        $mform->setDefault('video_required[' . $index . ']', $video->required);

        $mform->addElement('html', '</div></div></div>');
    }

    /**
     * JavaScript for dynamically adding/removing video fields.
     * Now handled via AMD module mod_videoguide/form.
     */

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['video_title'])) {
            foreach ($data['video_title'] as $index => $title) {
                if (!empty($title) && empty($data['video_url'][$index])) {
                    $errors['video_url[' . $index . ']'] = get_string('urlrequired', 'mod_videoguide');
                }
            }
        }

        return $errors;
    }
}
