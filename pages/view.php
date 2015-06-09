<?php

//error_reporting(E_ALL | E_STRICT);

ob_start();

html_page_top1(plugin_lang_get('title'));
html_page_top2();
require dirname(__FILE__) . '/../functions.php';
require dirname(__FILE__) . '/../viewHelperFunctions.php';
require dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/open-flash-chart.php';
require_once dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/ofc_line.php';
require_once dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/ofc_line_dot.php';
require dirname(__FILE__) . '/../HoursRemainingChart.php';

$versionId = gpc_get_int('versionId');
if (!$versionId) {
  header("Location: " . plugin_page('index'));
}

$version = version_get($versionId);
$dateCreatedTs = strtotime(version_get_field($versionId, BurnDownChartPlugin::DATE_CREATED_FIELD));
access_ensure_project_level(config_get('roadmap_view_threshold'), $version->project_id);

$workingDays = round(getWorkingDays($dateCreatedTs, $version->date_order));
$numberOfIssuesInVersion = getNumberOfIssuesByVersion($version);
$numberOfResolvedIssuesByDate  = getNumberOfResolvedIssuesByDate($version);
$issues = getIssuesByVersion($version);
$totalStoryPoints = getTotalStoryPoints($issues);
$shortDateFormat = config_get('short_date_format');

$currentDayIncrement = 0;
$sprintFinishedDate = date($shortDateFormat, $version->date_order);

$dataLine = new line();
$lineData = array($totalStoryPoints);
$xAxisData = array('Initial');
$storyPointsLeft = $totalStoryPoints;
$today = strtotime("today");
for ($i = 0; ; $i++) {
  $currentDateTs = strtotime("+" . $currentDayIncrement . " days", $dateCreatedTs);
  $currentDate = date($shortDateFormat, $currentDateTs);
  if ($sprintFinishedDate == $currentDate) {
    break;
  }
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
}
$dataLine->set_values($lineData);

$optimalLine = new line();
$optimalLine->set_colour('#018F00');
$optimalManDaysPerDay = $totalStoryPoints / $workingDays;
$optimalLineData = array();
for ($i = 0; $i <= $workingDays; $i++) {
  $optimalLineData[] = round($totalStoryPoints - $i * $optimalManDaysPerDay, 4);
}
$optimalLine->set_values($optimalLineData);

$chart = constructChart('Story Points', $totalStoryPoints, $xAxisData);

$chart->add_element($optimalLine);
$chart->add_element($dataLine);

// Hours remaining chart
$hoursRemainingChart = new HoursRemainingChart();
$hoursRemainingChart->setFrom($dateCreatedTs);
$hoursRemainingChart->setTill($version->date_order);
$hoursRemainingChart->setIssues($issues);
$hoursRemainingChart = $hoursRemainingChart->getChart('Hours remaining');
?>
<script type="text/javascript"
        src="plugins/BurnDownChart/files/open_flash_chart/js/json/json2.js"></script>
<script type="text/javascript"
        src="plugins/BurnDownChart/files/open_flash_chart/js/swfobject.js"></script>
<script type="text/javascript">
  swfobject.embedSWF('plugins/BurnDownChart/files/open_flash_chart/open-flash-chart.swf', "burnDownChart", "600", "400", "9.0.0");
  swfobject.embedSWF("plugins/BurnDownChart/files/open_flash_chart/open-flash-chart.swf", "HRBurnDownChart", "600", "400", "9.0.0", "expressInstall.swf", {"get-data": "get_hours_remaining_data"});
</script>
<script type="text/javascript">

  function open_flash_chart_data() {
    return JSON.stringify(data);
  }

  var data = <?php echo $chart->toPrettyString(); ?>;

  function get_hours_remaining_data() {
    return JSON.stringify(hoursRemainingData);
  }

  var hoursRemainingData = <?php echo $hoursRemainingChart->toPrettyString(); ?>;

</script>
<span class="pagetitle"><?php echo printVersionHeader($version) ?></span>
<br/>
<br/>
<div class="center">
  <h2>Version: <?php echo $version->version; ?></h2>
  <div id="HRBurnDownChart"></div><br /><br />
  <div id="burnDownChart"></div>
</div>
<br/>
<?php

html_page_bottom1(__FILE__);

ob_get_flush();