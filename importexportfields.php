<?php
// This file is part of the Checklist plugin for Moodle - http://moodle.org/
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
 * Fields to include when importing/exporting checklist items
 * @copyright Davo Smith <moodle@davosmith.co.uk>
 * @package mod_checklist
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$separator = ',';

// Fieldname => output string.
$fields = [
    'displaytext' => 'Item text',
    'indent' => 'Indent',
    'itemoptional' => 'Type (0 - normal; 1 - optional; 2 - heading)',
    'duetime' => 'Due Time (timestamp)',
    'colour' => 'Colour (red; orange; green; purple; black)',
    'linkcourseid' => 'Courseid (optional - link to this course)',
    'linkurl' => 'URL (optional - link to this URL)',
    'openlinkinnewwindow' => 'Open link in new window (optional - 0 - no; 1 - yes)',
];
