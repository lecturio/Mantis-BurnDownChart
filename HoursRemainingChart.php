<?php

/**
 * @author iik
 */
class HoursRemainingChart
{
  /**
   * @var DateTime
   */
  private $from;

  /**
   * @var DateTime
   */
  private $till;

  /**
   * @var array
   */
  private $issues;

  /**
   * @var int
   */
  private $initialHours;

  /**
   * @var array
   */
  private $xAxisData;

  /**
   * @var array
   */
  private $remainingHoursData;

  /**
   * Set from date
   * @param int $from
   */
  public function setFrom($from)
  {
    $this->from = new DateTime(date('Y-m-d 00:00:00', $from));
  }

  /**
   * Set from till
   * @param int $till
   */
  public function setTill($till)
  {
    $this->till = new DateTime(date('Y-m-d 00:00:00', $till));
  }

  /**
   * Set issues
   * @param array $issues
   */
  public function setIssues($issues)
  {
    $this->issues = $issues;
  }

  /**
   * Create chart object
   * @param string $title
   * @return open_flash_chart
   */
  public function getChart($title)
  {
    // prepare data
    $this->prepareData();

    // get max value
    $maxValue = (empty($this->remainingHoursData))
      ? $this->initialHours
      : max($this->initialHours, max($this->remainingHoursData));
    // create chart object
    $result = constructChart($title, $maxValue, $this->xAxisData);

    // add optimal line
    $optimalData = $this->getOptimalData();
    if (!empty($optimalData))
    {
      $optimalLine = new line();
      $optimalLine->set_colour('#018F00');
      $optimalLine->set_values($optimalData);
      $result->add_element($optimalLine);
    }

    // add hours remaining line
    if (!empty($this->remainingHoursData))
    {
      $remainingHoursLine = new line();
      $remainingHoursLine->set_colour('#CC0707');
      $remainingHoursLine->set_values($this->remainingHoursData);
      $result->add_element($remainingHoursLine);
    }

    return $result;
  }

  /**
   *
   */
  private function prepareData()
  {
    $this->initialHours = 0;
    $this->remainingHoursData = array();
    $this->xAxisData = array();

    $remainingHoursFieldId = custom_field_get_id_from_name(BurnDownChartPlugin::HOURS_REMAINING_FIELD);

    $dtInitial = null;

    // set blank remaining hours data
    $dtTmp = clone $this->from;
    do
    {
      if ($dtTmp->format('N') < 6)
      {
        // set initial date
        if (is_null($dtInitial))
        {
          $dtInitial = clone $dtTmp;
        }
        $this->xAxisData[] = $dtTmp->format('d.m.Y');
        $this->remainingHoursData[$dtTmp->format('Ymd')] = 0;
      }

      $dtTmp->modify('+1 day');
    }
    while ($dtTmp < $this->till);

    // get issues data
    $issuesData = array();
    foreach ($this->issues as $issue)
    {
      $history = history_get_events_array($issue['id']);
      $dtSubmited = new DateTime(date('Y-m-d', $issue['date_submitted']));

      // get historical info
      $issueData = array();
      $initialHours = array();
      foreach ($history as $event)
      {
        if ($event['note'] == BurnDownChartPlugin::HOURS_REMAINING_FIELD)
        {
          // get event date
          $dtEvent = new DateTime($event['date']);
          $dtEvent->setTime(0, 0, 0);

          // if event date is greater then till date - break
          if ($dtEvent > $this->till)
          {
            break;
          }

          // get values
          $values = array();
          eregi('([0-9\.]*) => ([0-9\.]*)', $event['change'], $values);
          $previousValue = isset($values[1]) ? (float) $values[1] : 0;
          $value = isset($values[2]) ? (float) $values[2] : 0;

          // if there is not previous event create it as first
          if (empty($issueData))
          {
            $this->addChronologicalEventValue($issueData, $dtSubmited, $previousValue);
            $this->addInitialHours($initialHours, $dtInitial, $dtSubmited, $previousValue);
          }

          // add current event
          $this->addChronologicalEventValue($issueData, $dtEvent, $value);
          $this->addInitialHours($initialHours, $dtInitial, $dtEvent, $value);
        }
      }

      // if there is no history events check for current value
      if (empty($issueData))
      {
        $value = (float) custom_field_get_value($remainingHoursFieldId, $issue['id']);
        if ($value > 0)
        {
          $this->addChronologicalEventValue($issueData, $dtSubmited, $value);
          $this->addInitialHours($initialHours, $dtInitial, $dtSubmited, $value);
        }
      }

      // if there is events add to issues data array
      if (!empty($issueData))
      {
        $issuesData[] = $issueData;
      }

      // if there is initial hours add tyo global
      if (!empty($initialHours))
      {
        $this->initialHours += max($initialHours);
      }
    }

    // merge issues data
    foreach ($issuesData as $issueData)
    {
      $value = 0;

      $dtTmp = clone $this->from;
      do
      {
        // get new value if exists
        if (isset($issueData[$dtTmp->format('Ymd')]))
        {
          $value = $issueData[$dtTmp->format('Ymd')];
        }
        // set the new value if date is valid
        if (array_key_exists($dtTmp->format('Ymd'), $this->remainingHoursData))
        {
          $this->remainingHoursData[$dtTmp->format('Ymd')] += $value;
        }
        // inc day
        $dtTmp->modify('+1 day');
      }
      while ($dtTmp <= $this->till);
    }

    $this->remainingHoursData = array_values($this->remainingHoursData);

    // add initial point
    array_unshift($this->remainingHoursData, $this->initialHours);
    array_unshift($this->xAxisData, 'Initial');
  }

  /**
   * @param array $data
   * @param DateTime $dtEvent
   * @param float $value
   */
  private function addChronologicalEventValue(&$data, $dtEvent, $value)
  {
    if ($dtEvent < $this->from)
    {
      $data[$this->from->format('Ymd')] = round($value, 4);
    }
    else if ($dtEvent <= $this->till)
    {
      $data[$dtEvent->format('Ymd')] = round($value, 4);
    }
  }

  /**
   * @param array $initialHours
   * @param DateTime $dtInitial
   * @param DateTime $dtValue
   * @param int $value
   */
  private function addInitialHours(&$initialHours, $dtInitial, $dtValue, $value)
  {
    if ($dtValue <= $dtInitial)
    {
      $initialHours[] = $value;
    }
  }

  /**
   * @return array
   */
  private function getOptimalData()
  {
    $result = array();

    // calculate days count
    $cntDays = count($this->remainingHoursData);
    if (($this->initialHours > 0) && ($cntDays > 0))
    {
      // calculate optima hours per day
      $perDay = $this->initialHours / ($cntDays - 1);
      // create data array
      for ($i = 0; $i < $cntDays; $i++)
      {
        $result[$i] = round($this->initialHours - ($i * $perDay), 4);
      }
    }

    return $result;
  }
}