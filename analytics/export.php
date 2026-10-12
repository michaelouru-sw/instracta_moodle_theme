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
 * CSV export of a Instracta analytics dataset.
 *
 * @package    theme_instracta
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

require_login();
require_capability('theme/instracta:viewanalytics', context_system::instance());
require_sesskey();

$dataset = required_param('dataset', PARAM_ALPHA);
$days = optional_param('days', 30, PARAM_INT);
$categoryid = optional_param('cat', 0, PARAM_INT);

$analytics = new \theme_instracta\analytics\site($days, $categoryid);
$data = $analytics->export_rows($dataset);
if ($data === null) {
    throw new moodle_exception('invalidparameter', 'debug');
}

[$header, $rows] = $data;
$writer = new csv_export_writer();
$writer->set_filename('instracta_' . $dataset . '_' . $analytics->get_days() . 'd_' . date('Ymd'));
$writer->add_data($header);
foreach ($rows as $row) {
    $writer->add_data($row);
}
$writer->download_file();
