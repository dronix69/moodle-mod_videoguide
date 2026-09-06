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

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

class view_page implements renderable, templatable {

    protected $videoguide;
    protected $videos;
    protected $progresspercent;
    protected $viewedcount;
    protected $totalvideos;
    protected $canmanage;

    public function __construct($videoguide, $videos, $progresspercent, $viewedcount, $totalvideos, $canmanage) {
        $this->videoguide = $videoguide;
        $this->videos = $videos;
        $this->progresspercent = $progresspercent;
        $this->viewedcount = $viewedcount;
        $this->totalvideos = $totalvideos;
        $this->canmanage = $canmanage;
    }

    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->name = format_string($this->videoguide->name);
        $data->intro = format_text($this->videoguide->intro);
        $data->videos = $this->videos;
        $data->progresspercent = $this->progresspercent;
        $data->viewedcount = $this->viewedcount;
        $data->totalvideos = $this->totalvideos;
        $data->canmanage = $this->canmanage;
        $data->hasvideos = !empty($this->videos);
        $data->sesskey = sesskey();
        return $data;
    }
}
