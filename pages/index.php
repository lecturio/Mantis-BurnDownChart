<?php
/**
 * Burn down chart plugin overview
 *
 * @author Michael Weibel <michael@students.ch>
 * @version $Id$
 */

	error_reporting(E_ALL | E_STRICT);

	html_page_top1( plugin_lang_get( 'title' ) );
	html_page_top2();
	require dirname(__FILE__) . '/../functions.php';

	$versionsByProjectId = getVersionsByProjectId();
	$projectCount = count($versionsByProjectId);
?>
	<div class="center">
		<form action="plugin.php" method="get">
			<input type="hidden" name="page" value="<?php echo plugin_get_current() ?>/view"/>					
			<label for="versionId"><?php echo plugin_lang_get('chooseVersion') ?></label>
			<select name="versionId" id="versionId">
<?php
	foreach($versionsByProjectId as $projectId => $versions):
		if (count($versions)):
			if ($projectCount > 1):
				$projectName = project_get_field($projectId, 'name');
				echo "<optgroup label=\"{$projectName}\">";
			endif;

			foreach ($versions as $version):
				echo "  <option value=\"{$version['id']}\">{$version['version']}</option>";
			endforeach;

			if ($projectCount > 1):
				echo '</optgroup>';
			endif;
		endif;
	endforeach;
?>
			</select>
			<br/>
			<input type="submit" name="showBurnDownChart" value="<?php echo plugin_lang_get('showBurnDownChart') ?>"/>
		</form>
	</div>
<?php
	html_page_bottom1( __FILE__ );
?>