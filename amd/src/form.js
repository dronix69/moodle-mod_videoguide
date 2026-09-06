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
 * Dynamic form handling for mod_videoguide.
 *
 * @module     mod_videoguide/form
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/templates', 'core/notification'], function(Templates, Notification) {

    var videoIndex = 0;
    var platformData = [];

    /**
     * Convert platform map to an array of {key, label} objects for the template.
     *
     * @param {Object} platforms The platform map (key => label).
     * @return {Object[]} The list of {key, label} objects.
     */
    var buildPlatformList = function(platforms) {
        var list = [];
        for (var key in platforms) {
            list.push({key: key, label: platforms[key]});
        }
        return list;
    };

    var init = function() {
        // Read platforms from data attribute injected by PHP.
        var dataDiv = document.getElementById('videoguide-form-data');
        if (dataDiv) {
            var platformsJson = dataDiv.getAttribute('data-platforms');
            if (platformsJson) {
                try {
                    var platforms = JSON.parse(platformsJson);
                    platformData = buildPlatformList(platforms);
                } catch (e) {
                    Notification.exception(e);
                }
            }
        }
        videoIndex = document.querySelectorAll('.videoguide-video-item').length;

        // Use event delegation for the add button (handles ID changes by Moodle).
        document.addEventListener('click', function(e) {
            var addTarget = e.target.closest('.videoguide-add-video, #addvideo-btn');
            if (addTarget) {
                e.preventDefault();
                addVideoField();
                return;
            }
            var removeTarget = e.target.closest('.videoguide-remove-video');
            if (removeTarget) {
                var fieldsetId = removeTarget.getAttribute('data-fieldset');
                var element = document.getElementById(fieldsetId);
                if (element) {
                    element.remove();
                }
            }
        });
    };

    var addVideoField = function() {
        var container = document.getElementById('videoguide-videos-container');
        if (!container) {
            Notification.exception('videoguide-videos-container not found');
            return;
        }
        var index = videoIndex;

        Templates.render('mod_videoguide/video_fieldset', {
            index: index,
            indexPlusOne: index + 1,
            platforms: platformData
        }).then(function(html) {
            var temp = document.createElement('div');
            temp.innerHTML = html;
            container.appendChild(temp.firstElementChild);
            videoIndex++;
            return true;
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    return {
        init: init,
        addVideoField: addVideoField
    };
});
