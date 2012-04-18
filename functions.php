<?php
/**
 * Functions for burn down chart plugin
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

function getVersionsByProjectId() {
	$versionsByProjectId = array();

	$projectIds = getProjectIds();

	version_cache_array_rows($projectIds);
	foreach ($projectIds as $projectId) {
		$versions = array_reverse(version_get_all_rows($projectId));

		$versionsByProjectId[$projectId] = $versions;
	}

	return $versionsByProjectId;
}

function getProjectIds() {
	$userId = auth_get_current_user_id();
	$projectId = helper_get_current_project();
	$validProjects = array();

	if (ALL_PROJECTS == $projectId) {
		$accessibleProjects = user_get_accessible_projects($userId);
		foreach ($accessibleProjects as $project) {
			$accessibleProjects = array_merge($accessibleProjects, user_get_all_accessible_subprojects($userId, $project));
		}
		$accessibleProjectsToCheck = array_unique($accessibleProjects);

		foreach ($accessibleProjectsToCheck as $currentProjectId) {
			$roadmapViewAccessLevel = config_get('roadmap_view_threshold', null, null, $currentProjectId);
			if (access_has_project_level($roadmapViewAccessLevel, $currentProjectId)) {
				$validProjects[] = $currentProjectId;
			}
		}
	} else {
		access_ensure_project_level(config_get('roadmap_view_threshold'), $projectId);
		$validProjects = user_get_all_accessible_subprojects($userId, $projectId);
		array_unshift($validProjects, $projectId);
	}

	return $validProjects;
}

function getNumberOfIssuesByVersion($version) {
	version_ensure_exists($version->id);

	$bugTable = db_get_table('mantis_bug_table');
	$relationTable = db_get_table('mantis_bug_relationship_table');

	$query = "SELECT COUNT(*) AS c FROM $bugTable AS sbt
						LEFT JOIN $relationTable ON sbt.id=$relationTable.destination_bug_id AND $relationTable.relationship_type=2
						LEFT JOIN $bugTable AS dbt ON dbt.id=$relationTable.source_bug_id
						WHERE sbt.project_id=" . db_param() . " AND sbt.target_version=" . db_param() . " ORDER BY sbt.status ASC, sbt.last_updated DESC";
	$result = db_query_bound($query, array($version->project_id, $version->version));
	$count = db_fetch_array($result);
	return $count['c'];
}

function getIssuesByVersion($version) {
	version_ensure_exists($version->id);

	$bugTable = db_get_table('mantis_bug_table');
	$relationTable = db_get_table('mantis_bug_relationship_table');

	$query = "SELECT sbt.*, $relationTable.source_bug_id, dbt.target_version as parent_version FROM $bugTable AS sbt
						LEFT JOIN $relationTable ON sbt.id=$relationTable.destination_bug_id AND $relationTable.relationship_type=2
						LEFT JOIN $bugTable AS dbt ON dbt.id=$relationTable.source_bug_id
						WHERE sbt.project_id=" . db_param() . " AND sbt.target_version=" . db_param() . " ORDER BY sbt.status ASC, sbt.last_updated DESC";
	$result = db_query_bound($query, array($version->project_id, $version->version));

	$issues = array();
	while ($row = db_fetch_array($result)) {
		$issues[] = $row;
	}

	return $issues;
}

function getTotalStoryPoints($issues) {
	$manDays = 0;
	$customFieldId = custom_field_get_id_from_name(BurnDownChartPlugin::MAN_DAYS_FIELD);
	foreach ($issues as $issue) {
		$manDays += custom_field_get_value($customFieldId, $issue['id']);
	}

	return $manDays;
}

function getNumberOfResolvedIssuesByDate($version) {
	version_ensure_exists($version->id);

	$bugTable = db_get_table('mantis_bug_table');
	$relationTable = db_get_table('mantis_bug_relationship_table');
	$customFieldsTable = db_get_table('mantis_custom_field_string_table');
	$resolvedDateField = custom_field_get_id_from_name(BurnDownChartPlugin::DATE_RESOLVED_FIELD);
	$manDaysField = custom_field_get_id_from_name(BurnDownChartPlugin::MAN_DAYS_FIELD);

	$query = "SELECT DATE(FROM_UNIXTIME(cft.value)) AS resolvedDate, sbt.id FROM $bugTable AS sbt
						LEFT JOIN $relationTable ON sbt.id=$relationTable.destination_bug_id AND $relationTable.relationship_type=2
						LEFT JOIN $bugTable AS dbt ON dbt.id=$relationTable.source_bug_id
						LEFT JOIN $customFieldsTable AS cft ON sbt.id = cft.bug_id AND cft.field_id = $resolvedDateField
						WHERE
							sbt.project_id=" . db_param() . "
							AND sbt.target_version=" . db_param() . "
							AND sbt.status = " . RESOLVED . "
							ORDER BY sbt.status ASC, sbt.last_updated DESC";
	$result = db_query_bound($query, array($version->project_id, $version->version));

	$resolvedIssues = array();
	while ($row = db_fetch_array($result)) {
		$resolvedDate = date(config_get('short_date_format'), strtotime($row['resolvedDate']));
		$manDays = custom_field_get_value($manDaysField, $row['id']);
		if (isset($resolvedIssues[$resolvedDate])) {
			$resolvedIssues[$resolvedDate] += $manDays;
		} else {
			$resolvedIssues[$resolvedDate] = $manDays;
		}
	}

	return $resolvedIssues;
}

/**
 * From http://stackoverflow.com/questions/336127/calculate-business-days
 * @param int $startDate Startdate (unix timestmap)
 * @param int $endDate Enddate (unix timestamp)
 * @return float
 */
function getWorkingDays($startDate, $endDate)
{
  // iik: get end date till midnight
  $endDate = strtotime('midnight', $endDate);

	//The total number of days between the two dates. We compute the no. of seconds and divide it to 60*60*24
	//We add one to inlude both dates in the interval.
	$days = round(($endDate - $startDate) / 86400);

	$no_full_weeks = floor($days / 7);
	$no_remaining_days = fmod($days, 7);

	//It will return 1 if it's Monday,.. ,7 for Sunday
	$the_first_day_of_week = date("N", $startDate);
	$the_last_day_of_week = date("N", $endDate);

	//---->The two can be equal in leap years when february has 29 days, the equal sign is added here
	//In the first case the whole interval is within a week, in the second case the interval falls in two weeks.
	if ($the_first_day_of_week <= $the_last_day_of_week) {
		if ($the_first_day_of_week <= 6 && 6 <= $the_last_day_of_week) {
			$no_remaining_days--;
		}
		if ($the_first_day_of_week <= 7 && 7 <= $the_last_day_of_week) {
			$no_remaining_days--;
		}
	}
	else {
		if ($the_first_day_of_week <= 6) {
			//In the case when the interval falls in two weeks, there will be a weekend for sure
      // iik: there are only two days in the weekend
      $no_remaining_days = $no_remaining_days - 2;
      // $no_remaining_days = $no_remaining_days - 3;
		}
	}

	//The no. of business days is: (number of weeks between the two dates) * (5 working days) + the remainder
	//---->february in none leap years gave a remainder of 0 but still calculated weekends between first and last day, this is one way to fix it
	$workingDays = $no_full_weeks * 5;
	if ($no_remaining_days > 0) {
		$workingDays += $no_remaining_days;
	}

	return $workingDays;
}