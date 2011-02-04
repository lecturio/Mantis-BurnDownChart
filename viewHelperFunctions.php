<?php
/**
 * View helper functions for burn down chart
 *
 * BurnDownChart is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BurnDownChart is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BurnDownChart.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Michael Weibel <michael@students.ch>
 * @license http://www.gnu.org/licenses/gpl.html
 */

function printVersionHeader($version) {
	$projectId = $version->project_id;
	$versionId = $version->id;
	$versionName = $version->version;
	$projectName = project_get_field($projectId, 'name');

	$releaseTitle = '<a href="roadmap_page.php?project_id=' . $projectId . '">' . string_display_line($projectName) . '</a> - <a href="roadmap_page.php?version_id=' . $versionId . '">' . string_display_line($versionName) . '</a>';

	if (config_get('show_roadmap_dates')) {
		$versionTimestamp = $version->date_order;

		$scheduledReleaseDate = ' (' . lang_get('scheduled_release') . ' ' . string_display_line(date(config_get('short_date_format'), $versionTimestamp)) . ')';
	} else {
		$scheduledReleaseDate = '';
	}

	echo '<tt>';
	echo '<br />', $releaseTitle, $scheduledReleaseDate, lang_get('word_separator'), print_bracket_link('view_all_set.php?type=1&temporary=y&' . FILTER_PROPERTY_PROJECT_ID . '=' . $projectId . '&' . filter_encode_field_and_value(FILTER_PROPERTY_TARGET_VERSION, $versionName), lang_get('view_bugs_link')), '<br />';

	$t_release_title_without_hyperlinks = $projectName . ' - ' . $versionName . $scheduledReleaseDate;
	echo utf8_str_pad('', utf8_strlen($t_release_title_without_hyperlinks), '='), '<br />';
}

function constructChart($versionName, $totalStoryPoints, $xAxisData) {
	$title = new title($versionName);

	$y = new y_axis();
	$y->set_range(0, $totalStoryPoints, 1);

	$xLabels = new x_axis_labels();
	$xLabels->set_steps(1);
	$xLabels->set_vertical();
	$xLabels->set_colour('#000000');
	$xLabels->set_labels($xAxisData);

	$x = new x_axis();
	$x->set_offset(false);
	// Add the X Axis Labels to the X Axis
	$x->set_labels($xLabels);

	$chart = new open_flash_chart();
	$chart->set_title($title);
	$chart->add_y_axis($y);
	$chart->set_x_axis($x);

	return $chart;
}