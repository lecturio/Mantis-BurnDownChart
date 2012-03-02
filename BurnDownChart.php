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
	const DATE_CREATED_FIELD = 'date_created';
	const DATE_RESOLVED_FIELD = 'Date resolved';
	const MAN_DAYS_FIELD      = 'Story points';
  const HOURS_REMAINING_FIELD = 'Hours remaining';

	public function register() {
		$this->name = 'Burn Down Chart';
		$this->description = 'Generates a burn down chart from the roadmap';
		$this->version = '0.2';
		$this->requires = array(
			'MantisCore' => '1.2.4'
		);

		$this->author = 'Michael Weibel';
		$this->contact = 'michael@students.ch';
		$this->url     = 'http://www.students.ch';
	}

	public function init() {
	}

	public function hooks() {
		event_declare('EVENT_PLUGIN_BURNDOWNCHART_VERSION_DATE_CREATED', EVENT_TYPE_OUTPUT);
		return array(
			'EVENT_MENU_MAIN' => 'displayMenuLink',
			'EVENT_PLUGIN_BURNDOWNCHART_VERSION_DATE_CREATED' => 'displayDateCreatedField',
			'EVENT_MANAGE_VERSION_UPDATE' => 'updateVersionWithDateCreated',
			'EVENT_UPDATE_BUG' => 'updateBugWithDateResolved'
		);
	}

	public function displayMenuLink() {
		return array('<a href="' . plugin_page('index') . '">' . plugin_lang_get('menu') . '</a>');
	}

	public function displayDateCreatedField() {
		$versionId = gpc_get_int('version_id');
		$dateCreated = version_get_field($versionId, self::DATE_CREATED_FIELD);

		ob_start();
?>
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
<?php
		return ob_get_clean();
	}

	public function updateVersionWithDateCreated($event, $versionId) {
		version_ensure_exists($versionId);

		$dateCreated = gpc_get_string(self::DATE_CREATED_FIELD);

		$table = db_get_table('mantis_project_version_table');

		$query = "UPDATE $table
				  SET " . self::DATE_CREATED_FIELD . " = " . db_param() ."
				  WHERE id=" . db_param();
		db_query_bound($query, array($dateCreated, $versionId));
	}

	public function updateBugWithDateResolved($event, $bugData, $bugId) {
		$id = custom_field_get_id_from_name(self::DATE_RESOLVED_FIELD);
		if ($bugData->status == RESOLVED) {
			custom_field_set_value($id, $bugId, time());
		} else {
			// delete value (set it to null)
			if (custom_field_get_value($id, $bugId)) {
				custom_field_set_value($id, $bugId, null);
			}
		}
	}

	public function install() {
    $ret = $this->createManDaysCustomField();

    if ($ret === true)
    {
      $ret = $this->createDateResolvedCustomField();
    }

    if ($ret === true)
    {
      $ret = $this->createHoursRemainingCustomField();
    }

    return $ret;
	}

	private function createManDaysCustomField() {
		$id = custom_field_create(self::MAN_DAYS_FIELD);

		$definitions = array();
		$definitions['name']             = self::MAN_DAYS_FIELD;
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
		$id = custom_field_create(self::DATE_RESOLVED_FIELD);

		$definitions = array();
		$definitions['name']             = self::DATE_RESOLVED_FIELD;
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
   * Creates 'Hours remaining' custom field
   * @return bool
   */
  private function createHoursRemainingCustomField()
  {
    $id = custom_field_create(self::HOURS_REMAINING_FIELD);

    $definitions = array();
    $definitions['name']             = self::HOURS_REMAINING_FIELD;
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

	public function uninstall() {
		$id = custom_field_get_id_from_name(self::MAN_DAYS_FIELD);
		$ret = custom_field_destroy($id);
		if ($ret === true) {
			$id = custom_field_get_id_from_name(self::DATE_RESOLVED_FIELD);
			return custom_field_destroy($id);
		}
		return false;
	}

	public function schema() {
		return array(array(
			'AddColumnSQL', array(
				db_get_table('mantis_project_version_table'), self::DATE_CREATED_FIELD . ' DATE'
			)
		));
	}
}