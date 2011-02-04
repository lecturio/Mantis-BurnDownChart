<?php
/**
 * View burn down chart
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
 * @version $Id$
 */
//error_reporting(E_ALL | E_STRICT);

ob_start();

html_page_top1(plugin_lang_get('title'));
html_page_top2();
require dirname(__FILE__) . '/../functions.php';
require dirname(__FILE__) . '/../viewHelperFunctions.php';
require dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/open-flash-chart.php';
require_once dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/ofc_line.php';
require_once dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/ofc_line_dot.php';

$versionId = gpc_get_int('versionId');
if (!$versionId) {
	header("Location: " . plugin_page('index'));
}

$version = version_get($versionId);
$dateCreatedTs = strtotime(version_get_field($versionId, BurnDownChartPlugin::DATE_CREATED_FIELD));
access_ensure_project_level(config_get('manage_project_threshold'), $version->project_id);

$workingDays = round(getWorkingDays($dateCreatedTs, $version->date_order));
$numberOfIssuesInVersion = getNumberOfIssuesByVersion($version);
$numberOfResolvedIssuesByDate  = getNumberOfResolvedIssuesByDate($version);
$issues = getIssuesByVersion($version);
$totalStoryPoints = getTotalStoryPoints($issues);
$shortDateFormat = config_get('short_date_format');

$currentDayIncrement = 0;
$sprintFinishedDate = date($shortDateFormat, $version->date_order);

$dataLine = new line();
$lineData = array();
$xAxisData = array();
$storyPointsLeft = $totalStoryPoints;
$today = strtotime("today");
for ($i = 0; ; $i++) {
	$currentDateTs = strtotime("+" . $currentDayIncrement . " days", $dateCreatedTs);
	$currentDate = date($shortDateFormat, $currentDateTs);
	if (date("N", $currentDateTs) < 6) {
		$xAxisData[] = $currentDate;
		if (isset($numberOfResolvedIssuesByDate[$currentDate])) {
			$storyPointsLeft -= $numberOfResolvedIssuesByDate[$currentDate];
		}
		if ($today >= $currentDateTs) {
			$lineData[] = $storyPointsLeft;
		}
	}
	$currentDayIncrement++;
	if ($sprintFinishedDate == $currentDate) {
		break;
	}
}
$dataLine->set_values($lineData);

$optimalLine = new line();
$optimalLine->set_colour('#018F00');
$optimalManDaysPerDay = $totalStoryPoints / ($workingDays - 1);
$optimalLineData = array();
for ($i = 0; $i < $workingDays; $i++) {
	$optimalLineData[] = $totalStoryPoints - $i * $optimalManDaysPerDay;
}
$optimalLine->set_values($optimalLineData);

$chart = constructChart($version->version, $totalStoryPoints, $xAxisData);

$chart->add_element($optimalLine);
$chart->add_element($dataLine);
?>
<script type="text/javascript"
        src="plugins/BurnDownChart/files/open_flash_chart/js/json/json2.js"></script>
<script type="text/javascript"
        src="plugins/BurnDownChart/files/open_flash_chart/js/swfobject.js"></script>
<script type="text/javascript">
	swfobject.embedSWF('plugins/BurnDownChart/files/open_flash_chart/open-flash-chart.swf', "burnDownChart", "600", "400", "9.0.0");
</script>
<script type="text/javascript">

	function open_flash_chart_data() {
		return JSON.stringify(data);
	}

	var data = <?php echo $chart->toPrettyString(); ?>;

</script>
<span class="pagetitle"><?php echo printVersionHeader($version) ?></span>
<br/>
<br/>
<div class="center">
	<div id="burnDownChart"></div>
</div>
<br/>
<?php

html_page_bottom1(__FILE__);

ob_get_flush();