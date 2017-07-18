<?php
/*
Plugin Name: FeedBurnerCount
Description: A well-optimized and reliable plugin that connects to the FeedBurner Awareness API to retrieve your readers count, that you can print out in plain text.
Author: Guillermo Rauch
Author URI: http://devthought.com
Plugin URI: http://devthought.com/projects/wordpress/feedburnercount/
Version: 0.1
*/

/**
 * FeedBurnerCount
 *
 * @author Guillermo Rauch
 * @version $Id$
 * @copyright Devthought, 21 March, 2009
 * @package feedburnercount
 **/

class FeedBurnerCount {
	
	/**
	 * Constructor
	 *
	 * @return void
	 * @author Guillermo Rauch
	 */
	function FeedBurnerCount(){		
		if ($this->isSetup()){
			$this->retrieve();
			$this->count = get_option('fbc_count');	
		} else {
			$this->count = '<!-- Plugin not setup -->';
		}
		
		add_action('activate_feedburnercount.php', array(&$this, 'onActivate'));
		add_action('deactivate_feedburnercount.php', array(&$this, 'onDeactivate'));		
		add_action('admin_menu', array(&$this, 'onMenuConfigure'));
	}
	
	/**
	 * Called when the plugin is activated
	 *
	 * @return void
	 * @author Guillermo Rauch
	 */
	function onActivate(){
		$olfb = get_option('feedburner_settings');
		
		add_option('fbc_uri', ($olfb && isset($olfb['feedburner_url'])) ? str_replace('http://feeds.feedburner.com/', '', $olfb['feedburner_url']) : '');
		add_option('fbc_count', '');
		add_option('fbc_fallback_text', '');
		add_option('fbc_every', '3 hours');
		add_option('fbc_last_checked', '');
		add_option('fbc_average_timeago', '');
	}

	/**
	 * Called when the plugin is deactivated
	 *
	 * @return void
	 * @author Guillermo Rauch
	 */
	function onDeactivate()
	{
		delete_option('fbc_uri');
		delete_option('fbc_count');
		delete_option('fbc_fallback_text');
		delete_option('fbc_every');
		delete_option('fbc_last_checked');
		delete_option('fbc_average_timeago');
	}
	
	/**
	 * Configures the admin menu
	 *
	 * @return void
	 * @author Guillermo Rauch
	 */
	function onMenuConfigure()
	{
		add_submenu_page('options-general.php', 'FeedBurner Count', 'FeedBurnerCount', 8, __FILE__, array(&$this, 'administrate'));		
	}
	
	/**
	 * Determines if the plugin is ready to work
	 *
	 * @return boolean $setup
	 * @author Guillermo Rauch
	 */
	function isSetup()
	{
		return !!get_option('fbc_uri');
	}
	
	/**
	 * Generates a time in seconds from a string (1 hour => 3600)
	 *
	 * @param string $text 
	 * @return integer $interval
	 * @author Guillermo Rauch
	 */
	function toSeconds($text)
	{
		if (is_numeric($text)) return $text;
		$text = strtotime('+' . ltrim($text, '+-'), 0);
		return $text > 0 ? $text : null;
	}
	
	/**
	 * Returns the arithmetic mean of the array values
	 *
	 * @param string $values 
	 * @return integer $mean
	 * @author Guillermo Rauch
	 */
	function arrayMean($values, $round = true)
	{
		if (!sizeof($values)) return 0;
		$result = array_sum($values) / sizeof($values);
		return ($round) ? round($result) : $result;
	}
	
	/**
	 * Returns the FeedBurner URI to make the request
	 *
	 * @return string $uri
	 * @author Guillermo Rauch
	 */
	function getFeedBurnerUri()
	{
		$uri = 'https://feedburner.google.com/api/awareness/1.0/GetFeedData?uri=' . urlencode(get_option('fbc_uri'));
		
		if (get_option('fbc_average_timeago'))
			$uri .= '&dates=' . urlencode(date('Y-m-d', time() - $this->toSeconds(get_option('fbc_average_timeago'))) . ',' . date('Y-m-d'));
		
		return $uri;
	}
	
	/**
	 * Checks if it should retrieve now
	 *
	 * @return boolean $check
	 * @author Guillermo Rauch
	 */
	function checkRetrieve()
	{
		if (!get_option('fbc_every') || !get_option('fbc_last_checked')) return true;
		if ((time() - $this->toSeconds(get_option('fbc_every'))) > get_option('fbc_last_checked')) return true;				
		return false;
	}
	
	/**
	 * Retrieves the count from FeedBurner with Snoopy
	 *
	 * @return boolean $force Force to retrieve
	 * @author Guillermo Rauch
	 */
	function retrieve($force = false)
	{
		if ($force || $this->checkRetrieve())
		{
			update_option('fbc_last_checked', time());
			$count = null;
			$response = '';
			
			if (function_exists('wp_remote_get')){
				$response = @wp_remote_get($this->getFeedBurnerUri());
				$response = (is_array($response) && isset($response['body'])) ? $response['body'] : false;
			} else {
				$handler = @fopen($this->getFeedBurnerUri(), 'r');
				if ($handler){
					while (!feof($handler)) $response .= fgets($handler);
					fclose($handler);
				}
			}
			
			if ($response){
				preg_match_all('/circulation=\"(\d+)\"/m', $response, $values);
				if (!is_array($values) || !isset($values[1]) || !$values[1]) $count = null;
				else $count = $this->arrayMean($values[1]);	
			}

			$this->setCount($count);			
		}
	}
	
	/**
	 * Updates the count variable. If the supplied value is not valid and there's a feedback text, 
	 * it's updated to the feedback text. If not, no update takes place, resorting to the last valid value.
	 *
	 * @param string $count 
	 * @param string $update 
	 * @return void
	 * @author Guillermo Rauch
	 */
	function setCount($count, $update = true){
		$count = (is_numeric($count) && $count > 0) ? $count : null; 
		if (is_null($count))
			$update = !!($count = get_option('fbc_fallback_text'));				
		if ($update){
			$this->count = $count;
			update_option('fbc_count', $count);
		} 
	}
	
	/**
	 * Called when user goes to options page
	 *
	 * @return void
	 * @author Guillermo Rauch
	 */
	function administrate()
	{
		if (isset($_GET['force']) && $_GET['force']) $this->retrieve(true);
		
		$data = array();
		$errors = array();
		
		if (isset($_POST['fbc_options'])){
			$errors = array();
			$data = $_POST['fbc_options'];
			$data['fallback_text'] = $_POST['fbc_unavailable'] == 'text' ? $data['fallback_text'] : '';
			$data['average_timeago'] = (isset($_POST['fbc_average']) && $_POST['fbc_average']) ? $data['average_timeago'] : '';
			
			if (!$data['uri']) $errors[] = 'The FeedBurner identifier is required';
			if (!$this->toSeconds($data['every'])) $errors[] = 'Revise the checking frequency';
			if (isset($_POST['fbc_average']) && $_POST['fbc_average'] && !$this->toSeconds($data['average_timeago'])) 
				$errors[] = 'Revise the average calculation date setting';			
			if (!sizeof($errors)) {
				if (!$this->isSetup()) {
					update_option('fbc_count', '<!-- Awaiting first fetch -->');
					$this->retrieve(true);
				}
				foreach ($data as $key => $value) update_option('fbc_' . $key, $value);
			}
		}		
		?>
		<div class="wrap">
		<div id="icon-tools" class="icon32"><br /></div>
		<h2>FeedBurnerCount</h2>
		<p>This is the administration page of the <strong>FeedBurnerCount</strong> plugin. You can customize the plugin settings below</p>
		<p>To include the FeedBurner readers count in your templates use <code>&lt;?php echo fbc_count() ?&gt;</code></p>		
		<p>Make sure the <strong>Awareness API</strong> is enabled in your FeedBurner account (in the Publicize settings)</p>
		
		<h3>Current data</h3>	
		<table class="form-table">
			<tr>
				<th>Count</th>
				<td><code><?php echo htmlentities(fbc_count()) ?></code> <a href="options-general.php?page=<?php echo plugin_basename(__FILE__) ?>&amp;force=true" class="button">Update now</a></td>
			</tr>
			
			<tr>
				<th>Last checked</th>
				<td><?php echo get_option('fbc_last_checked') ? date('F j, Y, g:i:s a', get_option('fbc_last_checked')) : 'Never' ?></td>
			</tr>
			
			<tr>
				<th>Generated API URI</th>
				<td><code><?php echo $this->getFeedBurnerUri() ?></code></td>
			</tr>
		</table>
		
		<h3>Options</h3>
		<?php if ($errors): ?>
		<div class="error">
			<?php foreach($errors as $error): ?>
			<p><?php echo $error ?></p>
			<?php endforeach ?>
		</div>
		<?php endif ?>
		<form action="options-general.php?page=<?php echo plugin_basename(__FILE__) ?>" method="post" accept-charset="utf-8">
			<table class="form-table">
				<tr>
					<th><label for="fbc_options_uri">Your FeedBurner feed identifier</label></th>
					<td><input type="text" name="fbc_options[uri]" value="<?php echo isset($data['uri']) ? $data['uri'] : get_option('fbc_uri') ?>" id="fbc_options_uri"></td>
				</tr>
				<tr>
					<th><label for="fbc_options_every">Check every</label></th>
					<td><input type="text" name="fbc_options[every]" value="<?php echo isset($data['every']) ? $data['every'] : get_option('fbc_every') ?>" id="fbc_options_every" /> <br />Use a number of seconds or a valid <a href="http://php.net/strtotime">string</a> (examples: <strong>1 hour</strong>, <strong>2 days</strong>, <strong>4 weeks</strong>, not using words for numbers)</td>
				</tr>
				<tr>
					<th><label for="fbc_average">Calculate average</label></th>
					<td><input type="checkbox" name="fbc_average" value="1" <?php if((isset($_POST['fbc_average']) && $_POST['fbc_average']) || get_option('fbc_average_timeago')) echo 'checked="checked"'; ?> id="fbc_average"> <label for="fbc_average_timeago">of the last</label> <input type="text" name="fbc_options[average_timeago]" value="<?php echo isset($data['average_timeago']) ? $data['average_timeago'] : get_option('fbc_average_timeago') ?>" id="fbc_average_timeago" /><br />Use a number of seconds or a valid <a href="http://php.net/strtotime">string</a> (examples: <strong>15 days</strong>, <strong>2 months</strong>, <strong>4 weeks</strong>, not using words for numbers)</td>
				</tr>
				<tr>
					<th>If count is unavailable</th>
					<td><input type="radio" name="fbc_unavailable" value="last" id="fbc_unavailable_last" <?php if((isset($_POST['fbc_unavailable']) && $_POST['fbc_unavailable'] == 'last') || !get_option('fbc_fallback_text')) echo 'checked="checked"' ?> /> <label for="fbc_unavailable_last">keep the last result</label><br />
							<input type="radio" name="fbc_unavailable" value="text" id="fbc_unavailable_text" <?php if((isset($_POST['fbc_unavailable']) && $_POST['fbc_unavailable'] == 'text') || get_option('fbc_fallback_text')) echo 'checked="checked"' ?> /> <label for="fbc_unavailable_text">use this text: </label>
							<input type="text" name="fbc_options[fallback_text]" value="<?php echo isset($data['fallback_text']) ? $data['fallback_text'] : get_option('fbc_fallback_text') ?>" id="fbc_options_fallback_text" />
					</td>
				</tr>
			</table>
			<p><input type="submit" class="button" value="Save" /></p>
		</form>
		</div>
		<?php
	}
	
}

/**
 * FeedBurnerCount instance
 *
 * @author Guillermo Rauch
 */
global $FeedBurnerCount;
$FeedBurnerCount = new FeedBurnerCount();

/**
 * Handy function to get the count quickly
 *
 * @return void
 * @author Guillermo Rauch
 */
function fbc_count(){
	global $FeedBurnerCount;
	return $FeedBurnerCount->count;
}
?>