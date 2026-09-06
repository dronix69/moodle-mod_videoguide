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
 * AMD module for mod_videoguide student view.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/config'], function(Ajax, Notification, Config) {

    var init = function() {
        // Toggle viewed/not viewed — native event listener, zero jQuery.
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.toggle-view-btn');
            if (!btn) {
                return;
            }
            e.preventDefault();
            var videoid = btn.dataset.videoid;
            var row = btn.closest('tr');

            // AJAX call to toggle viewed status.
            Ajax.call([{
                methodname: 'mod_videoguide_toggle_viewed',
                args: {
                    videoid: videoid,
                    sesskey: Config.sesskey
                }
            }])[0].done(function(response) {
                if (response.viewed) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-success');
                    btn.querySelector('i').classList.remove('fa-circle-o');
                    btn.querySelector('i').classList.add('fa-check-circle');
                    row.classList.add('table-success', 'viewed-row');
                } else {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                    btn.querySelector('i').classList.remove('fa-check-circle');
                    btn.querySelector('i').classList.add('fa-circle-o');
                    row.classList.remove('table-success', 'viewed-row');
                }

                // Reload page to update progress bar.
                location.reload();

            }).fail(function(error) {
                Notification.exception(error);
            });
        });

        // Animación de entrada para filas (CSS transitions, zero jQuery)
        var rows = document.querySelectorAll('.videoguide-table tbody tr');
        for (var i = 0; i < rows.length; i++) {
            rows[i].classList.add('fade-in-row');
        }
    };

    return {
        init: init
    };
});
