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
  require dirname(__FILE__) . '/../WorkProcessedChart.php';

  $versionsByProjectId = getVersionsByProjectId();
  $projectCount = count($versionsByProjectId);

  $versionId = gpc_get_int('versionId', null);
  if ($versionId == null && $projectCount == 1) {
    $projetVersions = array_pop(array_values($versionsByProjectId));
    if (!empty($projetVersions)) {
      $currentVersion = null;
      foreach ($projetVersions as $v) {
        if ($v['released'] == '0') {
          $currentVersion = $v;
          break;
        }
      }

      $versionId = $currentVersion['id'];
    }
  }

  $workProcessedChartData = null;
  $version = $versionId ? version_get($versionId) : null;
  $actualVelocity = '?';
  $theoricVelocity = '?';
  $duration = '?';
  if ($version != null) {
  	access_ensure_project_level(config_get('roadmap_view_threshold'), $version->project_id);
  	
    $workProcessedChart = WorkProcessedChart::forVersion($version);
    $workProcessedChartData = $workProcessedChart->getChartData()->toPrettyString();
    
    $actualVelocity = $workProcessedChart->actualVelocity;
    $theoricVelocity = $workProcessedChart->theoricVelocity;
    $duration = $workProcessedChart->workingDaysOfDevelopment;
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
        $selected = '';
        if ($v['id'] == $versionId) {
          $selected = 'selected="selected"';
        }

        echo "  <option value=\"{$v['id']}\" {$selected}>{$v['version']}</option>";
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
<span class="pagetitle" style="position: relative; display: block; text-align: left;">
  <?php echo printVersionHeader($version, $actualVelocity, $theoricVelocity, $duration) ?>
</span>

<div class="center">
  <div id="burnDownChart"></div>
</div>

<script type="text/javascript"
        src="plugins/BurnDownChart/files/open_flash_chart/js/json/json2.js"></script>
<script type="text/javascript"
        src="plugins/BurnDownChart/files/open_flash_chart/js/swfobject.js"></script>
<script type="text/javascript">
  swfobject.embedSWF('plugins/BurnDownChart/files/open_flash_chart/open-flash-chart.swf', "burnDownChart", "800", "400", "9.0.0");
</script>
<script type="text/javascript">

  function open_flash_chart_data() {
    return JSON.stringify(data);
  }

  var data = <?php echo $workProcessedChartData == null ? 'null' : $workProcessedChartData; ?>;

</script>
<br/>
<?php
  }
html_page_bottom1(__FILE__);

// ob_get_flush();
?>