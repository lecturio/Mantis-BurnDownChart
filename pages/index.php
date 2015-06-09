<?php
/**
 * Charts pages
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
 * @author Louis Grignon <louis.grignon@gmail.com>
 * @license http://www.gnu.org/licenses/gpl.html
 * @version 2
 */

  error_reporting(E_ALL | E_STRICT);

  html_page_top1( plugin_lang_get( 'title' ) );
  html_page_top2();

  require dirname(__FILE__) . '/../functions.php';
  require dirname(__FILE__) . '/../viewHelperFunctions.php';
  require dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/open-flash-chart.php';
  require_once dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/ofc_line.php';
  require_once dirname(__FILE__) . '/../files/open_flash_chart/php-ofc-library/ofc_line_dot.php';
  require dirname(__FILE__) . '/../HoursRemainingChart.php';

  $versionsByProjectId = getVersionsByProjectId();
  $projectCount = count($versionsByProjectId);

  $versionId = gpc_get_int('versionId', null);
  if ($versionId == null && $projectCount == 1) {
    $projetVersions = array_pop(array_values($versionsByProjectId));
    if (!empty($projetVersions)) {
    	$lastVersion = array_pop($projetVersions);
    	$versionId = $lastVersion['id'];
    }
  }

  $version = $versionId ? version_get($versionId) : null;
  if ($version != null) {
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
  
  	$chart = constructChart('Work processed', $totalStoryPoints, $xAxisData);
  
  	$chart->add_element($optimalLine);
  	$chart->add_element($dataLine);
  
  	// Hours remaining chart
  	$hoursRemainingChart = new HoursRemainingChart();
  	$hoursRemainingChart->setFrom($dateCreatedTs);
  	$hoursRemainingChart->setTill($version->date_order);
  	$hoursRemainingChart->setIssues($issues);
  	$hoursRemainingChart = $hoursRemainingChart->getChart('Hours remaining');
  }
  
?>
<br/>
  <div class="center">
    <form action="plugin.php" method="get" id="chooseVersionForm">
      <input type="hidden" name="page" value="<?php echo plugin_get_current() ?>/index"/>
      <label for="versionId"><?php echo plugin_lang_get('chooseVersion') ?></label>
      <select name="versionId" id="versionId" onchange="document.getElementById('chooseVersionForm').submit();">
<?php
  echo "  <option value=\"\"></option>";
  foreach($versionsByProjectId as $projectId => $versions) {
    if (count($versions)) {
      if ($projectCount > 1) {
        $projectName = project_get_field($projectId, 'name');
        echo "<optgroup label=\"{$projectName}\">";
	  }

      // iik: sort last versions on top
      $versions = array_reverse($versions);

      foreach ($versions as $v){
        echo "  <option value=\"{$v['id']}\">{$v['version']}</option>";
      }
      
      if ($projectCount > 1) {
        echo '</optgroup>';
      }
    }
  }
?>
      </select>
    </form>
  </div>
<?php 
  if ($version != null) {
?>
 <br/>
<span class="pagetitle"><?php echo printVersionHeader($version) ?></span>
<br/>
<div class="center">
  <h2>Version: <?php echo $version->version; ?></h2>
  <div id="HRBurnDownChart"></div><br /><br />
  <div id="burnDownChart"></div>
</div>

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

  var data = <?php echo $chart == null ? 'null' : $chart->toPrettyString(); ?>;

  function get_hours_remaining_data() {
    return JSON.stringify(hoursRemainingData);
  }

  var hoursRemainingData = <?php echo $hoursRemainingChart == null ? 'null' : $hoursRemainingChart->toPrettyString(); ?>;

</script>
<br/>
<?php
  }
html_page_bottom1(__FILE__);

// ob_get_flush();
?>