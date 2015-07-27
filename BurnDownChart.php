<?php
/**
 * Burn down chart done with roadmap
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

//error_reporting(E_ALL | E_STRICT);

class BurnDownChartPlugin extends MantisPlugin {
  
    const SORTABLE_DATE_FORMAT = 'Y-m-d';
  
	const DATE_CREATED_FIELD = 'date_created';
	const ALLOCATED_RESOURCES_FIELD = 'allocated_resources';
	const DATE_RELEASED_FIELD = 'date_released';
	const DEVS_END_DATE_FIELD = 'devs_end_date';
	
	const RESOLUTION_DATE_FIELD = 'Resolution_Date'; // resolution time
	const INITIAL_ESTIMATE_FIELD = 'Initial_Estimate'; // Temps estimé initial (JH)
	const REMAINING_FIELD = 'Remaining_Work'; //Temps restant (JH)
	const TIME_SPENT_FIELD = 'Time_Spent'; //Temps dépensé (JH)

	public function register() {
		$this->name = 'Burn Down Chart';
		$this->description = 'Generates a burn down chart from the roadmap';
		$this->version = '0.3';
		$this->requires = array(
			'MantisCore' => '1.2.4'
		);

		$this->author = 'Michael Weibel & Louis Grignon';
		$this->contact = 'michael@students.ch;louis.grignon@gmail.com';
		$this->url     = 'http://www.students.ch';
	}

	public function init() {
	}

	public function hooks() {
		event_declare('EVENT_PLUGIN_BURNDOWNCHART_VERSION_EDIT', EVENT_TYPE_OUTPUT);
		return array(
			'EVENT_MENU_MAIN' => 'displayMenuLink',
			'EVENT_PLUGIN_BURNDOWNCHART_VERSION_EDIT' => 'getVersionEditExtensionHtml',
			'EVENT_MANAGE_VERSION_UPDATE' => 'onVersionUpdate',
			'EVENT_UPDATE_BUG' => 'updateOnResolve'
		);
	}

	public function displayMenuLink() {
		return array('<a href="' . plugin_page('index') . '">' . plugin_lang_get('menu') . '</a>');
	}

	public function getVersionEditExtensionHtml() {
		$versionId = gpc_get_int('version_id');
		$dateCreated = version_get_field($versionId, self::DATE_CREATED_FIELD);
		$developmentsEndDate = version_get_field($versionId, self::DEVS_END_DATE_FIELD);
		$allocatedResources = version_get_field($versionId, self::ALLOCATED_RESOURCES_FIELD);

		ob_start();
?>
<tr >
	<td class="category">
		<?php echo plugin_lang_get( 'developmentsEndDate' ) ?>
	</td>
	<td>
		<input type="text" id="<?php echo self::DEVS_END_DATE_FIELD ?>" name="<?php echo self::DEVS_END_DATE_FIELD ?>" size="32" value="<?php echo (date_is_null($developmentsEndDate) ? '' : string_attribute($developmentsEndDate)) ?>" />
		<?php
			date_print_calendar('trigger_' . self::DEVS_END_DATE_FIELD);
			date_finish_calendar(self::DEVS_END_DATE_FIELD, 'trigger_' . self::DEVS_END_DATE_FIELD);
		?>
	</td>
</tr>
<tr <?php echo helper_alternate_class() ?>>
	<td class="category">
		<?php echo plugin_lang_get( 'dateCreated' ) ?>
	</td>
	<td>
		<input type="text" id="<?php echo self::DATE_CREATED_FIELD ?>" name="<?php echo self::DATE_CREATED_FIELD ?>" size="32" value="<?php echo (date_is_null($dateCreated) ? '' : string_attribute($dateCreated)) ?>" />
		<?php
			date_print_calendar('trigger_' . self::DATE_CREATED_FIELD);
			date_finish_calendar(self::DATE_CREATED_FIELD, 'trigger_' . self::DATE_CREATED_FIELD);
		?>
	</td>
</tr>
<tr <?php echo helper_alternate_class() ?>>
	<td class="category">
		<?php echo plugin_lang_get( 'allocatedResources' ) ?>
	</td>
	<td>
		<input type="number" step="0.1" id="<?php echo self::ALLOCATED_RESOURCES_FIELD ?>" name="<?php echo self::ALLOCATED_RESOURCES_FIELD ?>" size="2" value="<?php echo (date_is_null($allocatedResources) ? '' : string_attribute($allocatedResources)) ?>" />
	</td>
</tr>
<?php
		return ob_get_clean();
	}

	public function onVersionUpdate($event, $versionId) {
		version_ensure_exists($versionId);

		 $dateCreated = $_REQUEST[self::DATE_CREATED_FIELD];
        $devsEndDate = $_REQUEST[self::DEVS_END_DATE_FIELD] == '' ? null : $_REQUEST[self::DEVS_END_DATE_FIELD];
        $allocatedResources = $_REQUEST[self::ALLOCATED_RESOURCES_FIELD];

        $table = db_get_table('mantis_project_version_table');

        $query = "UPDATE $table
                          SET
                            " . self::DATE_CREATED_FIELD . " = " . db_param() .",
                            " . self::DEVS_END_DATE_FIELD . " = " . db_param() .",
            " . self::ALLOCATED_RESOURCES_FIELD . " = " . db_param() ."
                          WHERE id=" . db_param();
        db_query_bound($query, array($dateCreated, $devsEndDate, $allocatedResources, $versionId));
		
		// release date
		$released = gpc_get_string('released', null);
		$date_released = version_get_field($versionId, self::DATE_RELEASED_FIELD);
		if ($released == null) {
			$date_released = null;
		} else if (strlen($date_released) == 0) {
			$date_released = date(self::SORTABLE_DATE_FORMAT, time());
		}
		
		if ($date_released == null) {
          $query = "UPDATE $table SET ". self::DATE_RELEASED_FIELD. " = NULL WHERE id=" . db_param();
          db_query_bound($query, array($versionId));
		} else {
          $query = "UPDATE $table SET ". self::DATE_RELEASED_FIELD. " = " . db_param() ." WHERE id=" . db_param();
          db_query_bound($query, array($date_released, $versionId));
		}
	}

  /**
   * @param string $event
   * @param BugData $bugData
   * @param int $bugId
   */
  public function updateOnResolve($event, $bugData, $bugId)
  {
    // update 'date resolved'
    $this->updateBugWithDateResolved($event, $bugData, $bugId);
    // update 'hours remaining'
    $this->updateBugWithHoursRemaining($event, $bugData, $bugId);
  }

	public function updateBugWithDateResolved($event, $bugData, $bugId) {
		$id = custom_field_get_id_from_name(self::RESOLUTION_DATE_FIELD);
		if ($bugData->status == RESOLVED) {
			custom_field_set_value($id, $bugId, time());
		} else {
			// delete value (set it to null)
			if (custom_field_get_value($id, $bugId)) {
				custom_field_set_value($id, $bugId, null);
			}
		}
	}

  /**
   * @param string $event
   * @param BugData $bugData
   * @param int $bugId
   */
  public function updateBugWithHoursRemaining($event, $bugData, $bugId)
  {
    // get custom field id
    $id = custom_field_get_id_from_name(self::REMAINING_FIELD);
    // if issue is resolved set 'hours remaining' to zero
    if ($bugData->status == RESOLVED)
    {
      custom_field_set_value($id, $bugId, 0);
    }
  }

	public function install() {
      $ret = $this->createInitialEstimateCustomField();
  
      if ($ret === true)
      {
        $ret = $this->createDateResolvedCustomField();
      }
  
  	  if ($ret === true)
      {
        $ret = $this->createRemainingCustomField();
      }
      
      if ($ret === true)
      {
      	$ret = $this->createTimeSpentCustomField();
      }
  
      return $ret;
	}

	private function createInitialEstimateCustomField() {
        if (custom_field_get_id_from_name(self::INITIAL_ESTIMATE_FIELD) !== false) {
          return true;
        }

		$id = custom_field_create(self::INITIAL_ESTIMATE_FIELD);

		$definitions = array();
		$definitions['name']             = self::INITIAL_ESTIMATE_FIELD;
		$definitions['type']             = '2';
		$definitions['access_level_r']   = VIEWER;
		$definitions['access_level_rw']  = MANAGER;
		$definitions['length_min']       = 0;
		$definitions['length_max']       = 0;
		$definitions['display_report']   = 1;
		$definitions['display_update']   = 1;
		$definitions['display_resolved'] = 0;
		$definitions['display_closed']   = 0;
		$definitions['require_report']   = 0;
		$definitions['require_update']   = 0;
		$definitions['require_resolved'] = 0;
		$definitions['require_closed']   = 0;
		$definitions['filter_by']        = 0;

		return custom_field_update($id, $definitions);
	}

	private function createDateResolvedCustomField() {
        if (custom_field_get_id_from_name(self::RESOLUTION_DATE_FIELD) !== false) {
        	return true;
        }

		$id = custom_field_create(self::RESOLUTION_DATE_FIELD);

		$definitions = array();
		$definitions['name']             = self::RESOLUTION_DATE_FIELD;
		$definitions['type']             = '8';
		$definitions['access_level_r']   = VIEWER;
		$definitions['access_level_rw']  = ADMINISTRATOR;
		$definitions['length_min']       = 0;
		$definitions['length_max']       = 0;
		$definitions['display_report']   = 0;
		$definitions['display_update']   = 0;
		$definitions['display_resolved'] = 0;
		$definitions['display_closed']   = 0;
		$definitions['require_report']   = 0;
		$definitions['require_update']   = 0;
		$definitions['require_resolved'] = 0;
		$definitions['require_closed']   = 0;
		$definitions['filter_by']        = 0;

		return custom_field_update($id, $definitions);
	}

  /**
   * Creates 'Time Spent' custom field
   * @return bool
   */
  private function createTimeSpentCustomField()
  {
    if (custom_field_get_id_from_name(self::TIME_SPENT_FIELD) !== false) {
    	return true;
    }
    
    $id = custom_field_create(self::TIME_SPENT_FIELD);

    $definitions = array();
    $definitions['name']             = self::TIME_SPENT_FIELD;
    $definitions['type']             = '2';
    $definitions['access_level_r']   = VIEWER;
    $definitions['access_level_rw']  = DEVELOPER;
    $definitions['length_min']       = 0;
    $definitions['length_max']       = 0;
    $definitions['display_report']   = 1;
    $definitions['display_update']   = 1;
    $definitions['display_resolved'] = 1;
    $definitions['display_closed']   = 0;
    $definitions['require_report']   = 0;
    $definitions['require_update']   = 0;
    $definitions['require_resolved'] = 0;
    $definitions['require_closed']   = 0;
    $definitions['filter_by']        = 0;

    return custom_field_update($id, $definitions);
  }

  /**
   * Creates 'Hours remaining' custom field
   * @return bool
   */
  private function createRemainingCustomField()
  {
    if (custom_field_get_id_from_name(self::REMAINING_FIELD) !== false) {
    	return true;
    }
    
    $id = custom_field_create(self::REMAINING_FIELD);

    $definitions = array();
    $definitions['name']             = self::REMAINING_FIELD;
    $definitions['type']             = '2';
    $definitions['access_level_r']   = VIEWER;
    $definitions['access_level_rw']  = DEVELOPER;
    $definitions['length_min']       = 0;
    $definitions['length_max']       = 0;
    $definitions['display_report']   = 1;
    $definitions['display_update']   = 1;
    $definitions['display_resolved'] = 0;
    $definitions['display_closed']   = 0;
    $definitions['require_report']   = 0;
    $definitions['require_update']   = 0;
    $definitions['require_resolved'] = 0;
    $definitions['require_closed']   = 0;
    $definitions['filter_by']        = 0;

    return custom_field_update($id, $definitions);
  }

	public function uninstall()
	{
	  return true;
	}

	public function schema() {
		return array(array(
		'AddColumnSQL', array(
				db_get_table('mantis_project_version_table'), self::DATE_CREATED_FIELD . ' DATE'
		)
		  ), array(
				'AddColumnSQL', array(
						db_get_table('mantis_project_version_table'), self::ALLOCATED_RESOURCES_FIELD . ' FLOAT'
				)
		), array(
				'AddColumnSQL', array(
						db_get_table('mantis_project_version_table'), self::DATE_RELEASED_FIELD . ' DATE'
				)
		), array(
				'AddColumnSQL', array(
						db_get_table('mantis_project_version_table'), self::DEVS_END_DATE_FIELD . ' DATE'
				)
		));
	}
}