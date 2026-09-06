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
 * Renderable class for the videoguide view page.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_videoguide\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Renderable class for the videoguide view page.
 *
 * @package    mod_videoguide
 * @copyright  2026 Daniel Ferrada
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_page implements renderable, templatable {
    /** @var stdClass The videoguide instance record. */
    protected $videoguide;
    /** @var array The list of videos for the instance. */
    protected $videos;
    /** @var int The progress percentage. */
    protected $progresspercent;
    /** @var int The number of viewed videos. */
    protected $viewedcount;
    /** @var int The total number of videos. */
    protected $totalvideos;
    /** @var bool Whether the user can manage videos. */
    protected $canmanage;
    /** @var string The display method. */
    protected $displaymethod;

    /**
     * Constructor.
     *
     * @param stdClass $videoguide The videoguide instance record.
     * @param array $videos The list of videos for the instance.
     * @param int $progresspercent The progress percentage.
     * @param int $viewedcount The number of viewed videos.
     * @param int $totalvideos The total number of videos.
     * @param bool $canmanage Whether the user can manage videos.
     * @param string $displaymethod The display method.
     */
    public function __construct(
        $videoguide,
        $videos,
        $progresspercent,
        $viewedcount,
        $totalvideos,
        $canmanage,
        $displaymethod = 'newtab'
    ) {
        $this->videoguide = $videoguide;
        $this->videos = $videos;
        $this->progresspercent = $progresspercent;
        $this->viewedcount = $viewedcount;
        $this->totalvideos = $totalvideos;
        $this->canmanage = $canmanage;
        $this->displaymethod = $displaymethod;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass The data for the template.
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->name = format_string($this->videoguide->name);
        $data->intro = format_text($this->videoguide->intro);
        $data->videos = $this->videos;
        $data->progresspercent = $this->progresspercent;
        $data->viewedcount = $this->viewedcount;
        $data->totalvideos = $this->totalvideos;
        $data->canmanage = $this->canmanage;
        $data->displaymethod = $this->displaymethod;
        $data->hasvideos = !empty($this->videos);
        $data->sesskey = sesskey();
        return $data;
    }
}
