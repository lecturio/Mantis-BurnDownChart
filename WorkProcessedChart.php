<?php

/**
 * @author Louis Grignon
 */
class WorkProcessedChart {
  
  public static function forVersion($version) {
    if ($version == null) {
      return null;
    }

    $dateCreated = version_get_field($version->id, BurnDownChartPlugin::DATE_CREATED_FIELD);
    if ($dateCreated == null || $dateCreated == '') {
      return null;
    }
    
    $dateTarget = $version->date_order;
    if ($dateTarget == null || $dateTarget == '') {
      return null;
    }
    
    return new WorkProcessedChart($version);
  }
  
  private $version;
  
  private $startTimestamp;
  private $targetEndTimestamp;
  private $actualEndTimestamp;
  private $processedWorkByDate;
  private $processedWork;
  private $remainingWork;
  private $issues;
  private $totalWork;
  
  // TODO : have a VersionInfos object
  public $workingDaysOfDevelopment;
  public $theoricVelocity;
  public $actualVelocity;
  
  private $estimatedEndTimestampBasedOnTheoric;
  private $estimatedEndTimestampBasedOnActual;
  
  private $chartEndTimestamp;
  
  private function __construct($version) {
    
    $today = strtotime("today");
    
    $this->version = $version;
    
    $this->startTimestamp = strtotime(version_get_field($version->id, BurnDownChartPlugin::DATE_CREATED_FIELD));
    $this->targetEndTimestamp = $version->date_order;

    $this->actualEndTimestamp = strtotime(version_get_field($this->version->id, BurnDownChartPlugin::DATE_RELEASED_FIELD));
    if ($this->actualEndTimestamp == 0) {
      $this->actualEndTimestamp = null;
    }

    $this->devsEndTimestamp = strtotime(version_get_field($this->version->id, BurnDownChartPlugin::DEVS_END_DATE_FIELD));
    if ($this->devsEndTimestamp == 0) {
      $this->devsEndTimestamp = null;
    }

    $this->workingDaysOfDevelopment = round(getWorkingDays($this->startTimestamp, $this->devsEndTimestamp == null ? $version->date_order : $this->devsEndTimestamp));
    $elapsed_days = round(getWorkingDays($this->startTimestamp, $today));
    
    $this->processedWorkByDate  = getProcessedWorkByDate($version, $this->startTimestamp);
    $this->processedWork = array_sum(array_values($this->processedWorkByDate));
    
    $this->issues = getIssuesByVersion($version);
    $this->totalWork = getTotalStoryPoints($this->issues);
    $this->remainingWork = $this->totalWork - $this->processedWork;
    
    $theoric_resources = version_get_field($this->version->id, BurnDownChartPlugin::ALLOCATED_RESOURCES_FIELD);
    $this->theoricVelocity = $theoric_resources == null ? 1 : $theoric_resources;
    $this->actualVelocity = $elapsed_days > 0 ? $this->processedWork / $elapsed_days : 0;
    
    $remainingWork = $this->totalWork - $this->processedWork;
    $remainingDaysBasedOnTheoric = ceil($remainingWork / $this->theoricVelocity);
    $this->estimatedEndTimestampBasedOnTheoric = addWorkingDays($today, $remainingDaysBasedOnTheoric);
    
    if ($this->actualVelocity > 0) {
      $remainingDaysBasedOnActual = ceil($remainingWork / $this->actualVelocity);
      $this->estimatedEndTimestampBasedOnActual = addWorkingDays($today, $remainingDaysBasedOnActual);
    }

    if ($this->actualEndTimestamp != null) {
      $this->estimatedEndTimestampBasedOnTheoric = $this->actualEndTimestamp;
      $this->estimatedEndTimestampBasedOnActual = $this->actualEndTimestamp;
    }
    
    $this->chartEndTimestamp = max($this->estimatedEndTimestampBasedOnTheoric, $this->estimatedEndTimestampBasedOnActual, $this->targetEndTimestamp, $this->actualEndTimestamp);
    $this->chartEndTimestamp = strtotime("+1 day", $this->chartEndTimestamp);
  }
  
  public function getChartData() {
    
    // format dates for x axis
    $x_axis = array('Initial');
    $timestamps = $this->getXAxisTimestamps();
    for ($i = 0; $i < count($timestamps); $i++) {
      $x_axis[$i + 1] = date(config_get('short_date_format'), $timestamps[$i]);
    }
    
    $chart = constructChart(plugin_lang_get('chartTitle'), $this->totalWork, $x_axis);
    $this->addOptimalLineToChart($chart);
    $this->addTargetDateLineToChart($chart);
    $this->addEndDateLineToChart($chart);
    $this->addDevsEndLineToChart($chart);
    $this->addEstimatedLines($chart);
    $this->addProcessedWorkLineToChart($chart);
    
    return $chart;
  }
  
  private $xAxisTimestampsCache;
  
  private function &getXAxisTimestamps() {
    
    if ($this->xAxisTimestampsCache != null) {
      return $this->xAxisTimestampsCache;
    }
    
    $endDate = date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, $this->chartEndTimestamp);
    
    // TODO : adjust step for big range
    $xAxisDates = array();
    $currentDayIncrement = 0;
    while (true) {
    	$currentDateTs = strtotime("+" . $currentDayIncrement . " days", $this->startTimestamp);
    	if (date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, $currentDateTs) > $endDate) {
    		break;
    	}
    	
    	if (date("N", $currentDateTs) < 6) {
    		$xAxisDates[] = $currentDateTs;
    	}
    	
    	$currentDayIncrement++;
    }
    
    $this->xAxisTimestampsCache = $xAxisDates;
     
    return $xAxisDates;
  }
  
  private function addProcessedWorkLineToChart(open_flash_chart $chart) {
    
    $shortDateFormat = config_get('short_date_format');
    
    $lineData = array($this->totalWork);
    $storyPointsLeft = $this->totalWork;
    
    $timestamps = $this->getXAxisTimestamps();
    
    $todaySortableDate = date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, time());
    
    // TODO : adjust step for big range
    $currentDayIncrement = 0;
    foreach ($timestamps as $currentTimestamp) {
    	
    	if (date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, $currentTimestamp) > $todaySortableDate) {
    		break;
    	}

    	$currentDate = date($shortDateFormat, $currentTimestamp);
  		if (isset($this->processedWorkByDate[$currentDate])) {
  			$storyPointsLeft -= $this->processedWorkByDate[$currentDate];
  		}
		$lineData[] = $storyPointsLeft;
    }
    
    $dataLine = new line();
    $dataLine->set_values($lineData);
    $dataLine->set_key(plugin_lang_get('workProcessed'), 10);
    
    $chart->add_element($dataLine);
  }
  
  private function addOptimalLineToChart(open_flash_chart $chart) {
    $optimalLine = new line();
    $optimalLine->set_colour('#018F00');
    $optimalLine->set_key(plugin_lang_get('optimalLine'), 10);
    
    $optimalManDaysPerDay = $this->totalWork / ($this->workingDaysOfDevelopment + 1);
    $optimalLineData = array($this->totalWork);
    for ($i = 0; $i <= $this->workingDaysOfDevelopment; $i++) {
    	$optimalLineData[] = round($this->totalWork - ($i + 1) * $optimalManDaysPerDay, 4);
    }
    $optimalLine->set_values($optimalLineData);
    
    $chart->add_element($optimalLine);
  }
  
  private function addTargetDateLineToChart(open_flash_chart $chart) {
    
    $color = $this->isDeadlineReached() ? '#FF2222' : '#CCCCCC';
    
    $targetDateIndex = $this->getDateIndex($this->targetEndTimestamp);
    
    $targetDateLine = new scatter_line( $color, 2 );
    $def = new hollow_dot();
    $def->size(1)->halo_size(1);
    $targetDateLine->set_default_dot_style( $def );
    $targetDateLine->set_key(plugin_lang_get('targetReleaseDate'), 10);
    $targetDateLine->set_values(array(
        new scatter_value( $targetDateIndex, 0 ),
        new scatter_value( $targetDateIndex, $this->totalWork )
    ));
    $chart->add_element($targetDateLine);
  }
  
  private function addEndDateLineToChart(open_flash_chart $chart) {
    if ($this->actualEndTimestamp == null) {
      return;      
    }
    
    $color = '#CCCCCC';
    
    $endDateIndex = $this->getDateIndex($this->actualEndTimestamp);
    
    $endDateLine = new scatter_line( $color, 2 );
    $def = new hollow_dot();
    $def->size(1)->halo_size(1);
    $endDateLine->set_default_dot_style( $def );
    $endDateLine->set_key(plugin_lang_get('endReleaseDate'), 10);
    $endDateLine->set_values(array(
        new scatter_value( $endDateIndex, 0 ),
        new scatter_value( $endDateIndex, $this->totalWork )
    ));
    $chart->add_element($endDateLine);
  }
  
  private function addDevsEndLineToChart(open_flash_chart $chart) {
    if ($this->devsEndTimestamp == null) {
      return;      
    }
    
    $color = '#CCCCCC';
    
    $endDateIndex = $this->getDateIndex($this->devsEndTimestamp);
    
    $endDateLine = new scatter_line( $color, 2 );
    $def = new hollow_dot();
    $def->size(1)->halo_size(1);
    $endDateLine->set_default_dot_style( $def );
    $endDateLine->set_key(plugin_lang_get('developmentsEndDate'), 10);
    $endDateLine->set_values(array(
        new scatter_value( $endDateIndex, 0 ),
        new scatter_value( $endDateIndex, $this->totalWork )
    ));
    $chart->add_element($endDateLine);
  }
  
  private function addEstimatedLines(open_flash_chart $chart) {
    if (date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, strtotime("today")) >= date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, $this->chartEndTimestamp)) {
      return; // release is closed
    }
    
    $today_index = $this->getDateIndex(strtotime("today"));
    
    $color = '#AAAAAA';
    $theoricLine = new scatter_line( $color, 2 );
    $def = new hollow_dot();
    $def->size(1)->halo_size(1);
    $theoricLine->set_default_dot_style( $def );
    $theoricLine->set_key(plugin_lang_get('estimatedBasedOnTheoric'), 10);
    $theoricData = array();
    for ($i = $today_index; $i < count($this->getXAxisTimestamps()); $i++) {
    	$theoricData[] = new scatter_value( $i, max(0, round($this->remainingWork - (($i - $today_index) * $this->theoricVelocity), 4)) );
    }
    
    $theoricLine->set_values($theoricData);
    $chart->add_element($theoricLine);
    
    if ($this->actualVelocity > 0) {
      $color = '#777777';
      $actualLine = new scatter_line( $color, 2 );
      $def = new hollow_dot();
      $def->size(1)->halo_size(1);
      $actualLine->set_default_dot_style( $def );
      $actualLine->set_key(plugin_lang_get('estimatedBasedOnActual'), 10);
      $actualData = array();
      for ($i = $today_index; $i < count($this->getXAxisTimestamps()); $i++) {
      	$actualData[] = new scatter_value( $i, max(0, round($this->remainingWork - (($i - $today_index) * $this->actualVelocity), 4)) );
      }
      
      $actualLine->set_values($actualData);
      $chart->add_element($actualLine);
    }
  }
  
  private function isDeadlineReached() {
    return $this->chartEndTimestamp > $this->devsEndTimestamp;
  }
  
  private function getDateIndex($dateTimestamp) {
    $sortableDate = date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, $dateTimestamp);
    
    $i = 0;
    $dateIndex = null;
    $timestamps = $this->getXAxisTimestamps();
    foreach ($timestamps as $timestamp) {
    	if ($sortableDate == date(BurnDownChartPlugin::SORTABLE_DATE_FORMAT, $timestamp)) {
    		$dateIndex = $i;
    		break;
    	}
    	$i++;
    }
    
    return $dateIndex + 1;
  }
}