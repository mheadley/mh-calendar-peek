<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/**
 * @package mheadley
 * @version 1.0
 */
/*
Plugin Name: MH Calendar Simple Sneak Peek
Plugin URI: http://mheadley.com
Description: A simple calendar plugin that will render days of week/month in shades of color based on how busy you are that day.
Author: Michael Headley
Version: 1.0
Author URI: http://mheadley.com
*/
class mh_calendar_peek_plugin {
  function __construct() {
		add_action('admin_init', array($this, 'mh_calendar_peek_admin_init'));
		add_action( 'admin_menu', array($this, 'mh_calendar_peek_menu' ));
		add_action( 'mh_calendar_peek_plugin_out', array ( $this, 'mh_parse_calendar_obj' ), 10, 1 );
    add_action( 'wp_enqueue_scripts', array( $this, 'mh_calendar_peek_plugin_styles' ) );
  }
	public function mh_calendar_peek_menu() {
		add_options_page( 'MH Calendar Simple Sneak Peek', 'MH Calendar Options', 'manage_options', 'mh-calendar-peek', array($this, 'mh_calendar_peek_options_page' ));
	}
  public function mh_calendar_peek_plugin_styles() {
    wp_register_style( 'mh-calendar-peek-plugin', plugins_url( 'mh-calendar-peek-plugin/css/mh-calendar-peek.css' ) );
    wp_enqueue_style( 'mh-calendar-peek-plugin' );
  }
	private $timezoneString;
	private $calendarParentObj;
	private $errors = array();
	function mh_calendar_peek_options_page() { ?>
	<div>
		<h2>MH Calendar Simple Sneak Peek</h2>
		A simple calendar plugin that will render days of week/month in shades of color based on how busy you are that day.
		<form action="options.php" method="post">
			<?php settings_fields('mh_calendar_peek_options'); ?>
			<?php do_settings_sections('mh-calendar-peek-main'); ?>
			<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
		</form>
	</div>
	<?php
	}
	// add the admin settings and such
	function mh_calendar_peek_admin_init(){
	$options = get_option('mh_calendar_peek_options');
	register_setting( 'mh_calendar_peek_options', 'mh_calendar_peek_options', array($this, 'mh_calendar_peek_options_validate') );
	add_settings_section('plugin_main', 'Sync Settings', array($this, 'mh_plugin_section_text'), 'mh-calendar-peek-main');
	add_settings_field('mh_calendar_peek_url', 'iCal feed', array($this, 'mh_plugin_setting_input'), 'mh-calendar-peek-main', 'plugin_main', array(
    'name' => 'mh_calendar_peek_options[url_string]',
    'fvalue' => $options['url_string'],
		));
	add_settings_field('mh_calendar_peek_start_color', 'Free Color (HEX)', array($this, 'mh_plugin_setting_input'), 'mh-calendar-peek-main', 'plugin_main', array(
    'name' => 'mh_calendar_peek_options[start_color]',
    'fvalue' => $options['start_color'],
		));
	add_settings_field('mh_calendar_peek_end_color', 'Busy Color (HEX)', array($this, 'mh_plugin_setting_input'), 'mh-calendar-peek-main', 'plugin_main', array(
    'name' => 'mh_calendar_peek_options[end_color]',
    'fvalue' => $options['end_color'],
		));
	add_settings_field('mh_calendar_peek_day_start', 'Start of the Work day', array($this, 'mh_plugin_setting_input'), 'mh-calendar-peek-main', 'plugin_main', array(
    'name' => 'mh_calendar_peek_options[start_time]',
    'fvalue' => $options['start_time'],
		'type' => array("select", $this->get_day_hours()),
		));
	add_settings_field('mh_calendar_peek_day_end', 'End of the Work day:', array($this, 'mh_plugin_setting_input'), 'mh-calendar-peek-main', 'plugin_main', array(
    'name' => 'mh_calendar_peek_options[end_time]',
    'fvalue' => $options['end_time'],
		'type' => array("select", $this->get_day_hours())
		));
	add_settings_field('mh_calendar_peek_day_show', 'Days to show:', array($this, 'mh_plugin_setting_input'), 'mh-calendar-peek-main', 'plugin_main', array(
    'name' => 'mh_calendar_peek_options[days_show]',
    'fvalue' => $options['days_show'],
		'type' => array("select", array(5,7,14,28))
		));
	add_settings_field('mh_calendar_peek_cache_time', 'Minutes to cache calendar:', array($this, 'mh_plugin_setting_input'), 'mh-calendar-peek-main', 'plugin_main', array(
    'name' => 'mh_calendar_peek_options[cache_time]',
    'fvalue' => $options['cache_time'],
		'type' => array("select", array(5,15,30,60))
		));
	}

	function mh_plugin_section_text() {
	  echo '<p>These are the main sync settings for the MH calendar peek plugin; modify url and cache times here</p>';
	}

	function mh_plugin_setting_input( $args ) {
    $name = esc_attr( $args['name'] );
		$fvalue = esc_attr( $args['fvalue']);
		if(is_array($args['type'])){
			$type = esc_attr( $args['type'][0]);
			$range =  $args['type'][1];
		} else{ $type = esc_attr( $args['type']); }
		switch ($type){
			case "select";
				echo "<select name='$name'>";
				foreach($range as $value){
					if($value == $fvalue || $value['option'] == $fvalue){$selected = 'SELECTED';} else { $selected = '';}
					if(is_array($value)){
						echo "<option value=". $value['option'] ." $selected>". $value['label']. "</option> ";
					}else {
						echo "<option value='$value' $selected>$value</option> ";
					}
				}
				echo "</select>";
			break;
			default;
				echo "<input type='text' name='$name' value='$fvalue'  size='40'  />";
			break;
		}
	}

	function mh_calendar_peek_options_validate($input) {
		$options = get_option('mh_calendar_peek_options');
		foreach ( $input as $key => $value ) {
			if(!empty($value)){
				$options[$key] = trim($value);
			}
		}
		return $options;
	}
	private function mh_get_calendar_from_endpoint(){
		$defaults = array('cache_time' => 5);
		$options = wp_parse_args(get_option('mh_calendar_peek_options'), $defaults);
    $stale_cache = 'stale_cache_mh_calendar_endpoint_file';

		if ( false === ( $the_body = get_transient( 'mh_calendar_endpoint_file' ) ) ) {
      $response = wp_remote_get(esc_url_raw( $options['url_string'] ));
      if (is_wp_error($response) || ! isset($response['body']) || 200 != $response['response']['code']) {
        $the_body = get_option($stale_cache); //if error fetch stale cache from options set when transient was available
      } else {
        $the_body =  wp_remote_retrieve_body($response);
        $the_body = str_replace("\r\n", "\n", $the_body);
  			$the_body = str_replace("\n ", "", $the_body);
        if (! get_option($stale_cache)) {
          add_option($stale_cache, $the_body, '', 'no'); // googled and found this help reduce memory load
        } else {
          update_option($stalecachename, $the_body);
        }
      }
      set_transient( 'mh_calendar_endpoint_file', $the_body, $options['cache_time'] * 60 );
    }
    return $the_body;
  }

	function ical_time_to_timestamp($time) {
	  $hour = substr($time, 9, 2);
	  if($hour == "")
	    $hour = 0;
	  $min = substr($time, 11, 2);
	  if($min == "")
	    $min = 0;
	  $sec =  substr($time, 13, 2);
	  if($sec == "")
	    $sec = 0;
	  $mon = substr($time, 4, 2);
	  $day = substr($time, 6, 2);
	  $year = substr($time, 0, 4);
	  return gmmktime($hour, $min, $sec, $mon, $day, $year);
	}

	function get_day_hours(){
		$hrs = array();
		for($i = 0; $i < 24; $i+=1){
			$hrs[$i] = array('label'=>"$i:00", 'option'=> $i);
		}
		return $hrs;
	}

	private function mh_format_calendar(){
		global $timezoneString;
		$calendar_file = $this->mh_get_calendar_from_endpoint();

		$icsData = explode("BEGIN:", $calendar_file);
		 foreach($icsData as $key => $value) {
			$icsItemsMeta[$key] = explode("\n", $value);
		 }
		 foreach($icsItemsMeta as $key => $value) {
  		 foreach($value as $subKey => $subValue) {
    		 if ($subValue != "") {
  				 if ($key != 0 && $subKey == 0) {
  						if (!($subValue == "VCALENDAR" || $subValue == "VEVENT")) {
  							continue 2;
  						}
  							$icsDates[$key]["BEGIN"] =  $subValue;
  				 } else {
  						$subValueArr = explode(":", $subValue, 2);
  						switch(true) {
    						case ( -1 < strpos($subValueArr[0], "DTSTART")):
    							$icsDates[$key]["DTSTART"] =  $this->ical_time_to_timestamp($subValueArr[1]);
                break;
    						case ( -1 < strpos($subValueArr[0], "DTEND")):
    							$icsDates[$key]["DTEND"] =  $this->ical_time_to_timestamp($subValueArr[1]);
    							//$icsDates[$key][$subValueArr[0]] = $subValueArr[1];
    						break;
    						case ( -1 < strpos($subValueArr[0], "X-WR-TIMEZONE")):
    							try{ date_default_timezone_set($subValueArr[1]); }
    							catch(Exception $e){ $timezoneString = $subValueArr[1]; }
    							$icsDates[$key][$subValueArr[0]] = $subValueArr[1];
    						break;
                case ( -1 < strpos($subValueArr[0], "RRULE")):
                  $rrulesArr = explode(";", $subValueArr[1]);
                  $rrules = array();
                  //messy but readable; go through twice and use identifiers as keys
                  foreach($rrulesArr as $rsubkey => $rsubvalue) { $rrulesSubArr[$rsubkey] = explode("=", $rsubvalue); }
                  foreach($rrulesSubArr as $subvalue) { $rrules[$subvalue[0]] = $subvalue[1]; }
    							$icsDates[$key][$subValueArr[0]] = $rrules;
    						break;
    						default:
    							 //$icsDates[$key][$subValueArr[0]] = $subValueArr[1];
                   //$icsDates[$key][$subValueArr[0]] = strpos($subValueArr[0], "DTSTART");
    						break;
  						}
  				 }
    		 }
  		 }
		 }
		return $icsDates;
	}
	function get_week($today){
		$defaults = array('start_time' => 9, 'end_time' => 17, 'days_show' => 7 );
		$options = wp_parse_args(get_option('mh_calendar_peek_options'), $defaults);
		$ds_hour = intval($options['start_time']);
		$de_hour = intval($options['end_time']);
		$days_show = intval($options['days_show']);
		$days = array();
		$i = 0;
		if($ds_hour == $de_hour){ return false; }

		if($de_hour > $ds_hour){
			$day_start_buffer = $ds_hour;
			$full_day = $de_hour - $ds_hour;
		}else{
			$day_start_buffer = (-1 * (24 - $ds_hour));
			$full_day = ((24 - $ds_hour) + $de_hour);
		}
		while ($days_show > $i) {
			$days[$i] = array(
				'START' => strtotime("+" . $day_start_buffer ." hours", $today),
				'END' => (strtotime("+" . ($day_start_buffer + $full_day)  . " hours", $today))
			);
			$today = strtotime("+1 day", $today);
			$i++;
		}
		return $days;
	}
  function does_recur_today($day, $event){
    $defaults = array("INTERVAL" => 1, "COUNT" => 1);
    $options = wp_parse_args($event["RRULE"], $defaults);
    $offset_tz = date('Z', $day[0]);
    $day_start = $day[0];
    $day_end = $day[1];
    $event_start = $event["DTSTART"]  - $offset_tz;
    $event_end = $event["DTEND"]  - $offset_tz;
    $count = $options["COUNT"] * $options["INTERVAL"];
    //check if today is before first occurance
    if($day_end < $event_start){return false;}
    //check on 1st occurance recurrance as well
    switch ($options["FREQ"]) {
      case 'MONTHLY':
        $modulus = round(($day_start - $event_start)/(28*24*60*60)) % $options["INTERVAL"];
        $test = ((-1 < strpos($options["BYMONTHDAY"], date('j', $day_start)))? true : false);
        $mod = "+". $count . " months";
      break;
      case 'WEEKLY':
        $modulus = round(($day_start - $event_start )/(7*24*60*60)) % $options["INTERVAL"];
        $test = ((-1 < stripos($options["BYDAY"], substr(date('D', $day_start),0, 2)))? true : false);
        $mod = "+". $count . " weeks";
      break;
      case 'YEARLY':
        $modulus = round(( $day_start - $event_start)/(365*24*60*60)) % $options["INTERVAL"];
        //gmmktime to blend time with current year hack
        $event_end_adjusted = gmmktime(date("H", $event_end), date("i", $event_end), date("s", $event_end), date("m", $event_end), date("j", $event_end), date("Y", $day_start ));
        $test = (($day_start < $event_end_adjusted )? true : false);
        $mod = "+". $count . " years";
      break;
      case 'DAILY':
        $modulus = round(($day_start - $event_start)/(24*60*60)) % $options["INTERVAL"];
        $test = true; //today is a day ;-)
        $mod = "+". $count . " days";
      break;
      default:
        # default nada please note...
      break;
    }
    if(!isset($options["UNTIL"])){ $options["UNTIL"] = strtotime($mod, $event_start); }
    else { $options["UNTIL"] = $this->ical_time_to_timestamp($options["UNTIL"]); }

    if($test == true && ($day_start < $options["UNTIL"]) ){
      if($modulus == 0){return true;}
    }
    return false;
  }
	function get_day_busy($event, $day){
		$busy_meter = 0;
		$full_day = $day[1] - $day[0];
		//reset offsets for outside of day because it doesn't matter how long it is past the current day ;-)
		if($event[0] < $day[0]){ $event[0] = $day[0];}
		if($event[1] > $day[1]){ $event[1] = $day[1];}
		if (($day[0] >= $event[0]) && ($day[1] <= $event[1]) ) {
			$busy_meter = 100;
		}else{
			$busy_meter = floor((($event[1] - $event[0])/$full_day) * 100);
		}
		return $busy_meter;
	}
	function week_busy($events){
		$start_day = strtotime("today 12:00AM");
		$days = $this->get_week($start_day);
		$busy_week = array();
		$week_events = array();
		foreach  ($days as $key => $value) {
			$start = $value["START"];
			$end = $value["END"];
			foreach ($events as $subKey => $subValue){
				$week_events[$key] = $week_events[$key] + 0;
        if (isset($subValue["RRULE"])) {
          $offset_tz = date('Z', $subValue["DTSTART"]);
          //check if recurs
          if($this->does_recur_today(array($start, $end), $subValue) === true){
            $week_events[$key] = $week_events[$key] + $this->get_day_busy(array(strtotime(strval(date('h:i A ', $subValue["DTSTART"] - $offset_tz)), $start), strtotime(strval(date('h:i A ', $subValue["DTEND"] - $offset_tz)), $end)), array($start, $end));
          }
			  }else{
          if (($start > $subValue["DTEND"]) || ($end < $subValue["DTSTART"]) ) {
            //continue;
          }
  				else {
  					$week_events[$key] = $week_events[$key] + $this->get_day_busy(array($subValue["DTSTART"], $subValue["DTEND"]), array($start, $end));
  				}
        }
      //reset to 100 since that's the max
      if($week_events[$key] > 100 ){ $week_events[$key] = 100;} // reset because we don't care if it's outside of 100, you are busy!
		}
  }
    //$busy_week[] =  $events;
    //print_r($busy_week);
		return $week_events;
	}
	function decorate_days($busyArray){
		$defaults = array('default_start_color' => "6aff51", 'default_end_color' => "ff4216");
		$options = wp_parse_args(get_option('mh_calendar_peek_options'), $defaults);
		if(empty($options['start_color'])){$options['start_color'] = $options['default_start_color'];}
		if(empty($options['end_color'])){$options['end_color'] = $options['default_end_color'];}
		$theColorBegin = substr($options['start_color'], -6);
		$theColorEnd = substr($options['end_color'], -6);
		$html = "";

		function gradient($startcol,$endcol,$graduation=100){
			if($graduation == 100){return $endcol; }
			if($graduation == 0){return $startcol; }
		  $RedOrigin = hexdec(substr($startcol,0,2));
		  $GrnOrigin = hexdec(substr($startcol,2,2));
		  $BluOrigin = hexdec(substr($startcol,4,2));
		  $GradientSizeRed = (hexdec(substr($endcol,0,2))-$RedOrigin)/100; //Graduation Size Red
		  $GradientSizeGrn = (hexdec(substr($endcol,2,2))-$GrnOrigin)/100;
		  $GradientSizeBlu = (hexdec(substr($endcol,4,2))-$BluOrigin)/100;
		    $RetVal =
		    str_pad(dechex($RedOrigin+($GradientSizeRed*$graduation)),2,'0',STR_PAD_LEFT) .
		    str_pad(dechex($GrnOrigin+($GradientSizeGrn*$graduation)),2,'0',STR_PAD_LEFT) .
		    str_pad(dechex($BluOrigin+($GradientSizeBlu*$graduation)),2,'0',STR_PAD_LEFT);
		  return $RetVal;
		}

		$html .= '<div class="busy-days has-'. count($busyArray) .'days ">';
		foreach($busyArray as $key => $value){
			$html .= '<span data-busy="'. $value . '" class="day'.$key.'" style="background-color: #'. gradient($theColorBegin, $theColorEnd, $value) .';">&nbsp;</span>'; //generate gradient at exact percentage of busy meter quickly
		}
		$html .= '</div>';
		return $html;
	}
	function mh_parse_calendar_obj(){
		global $timezoneString;
		$defaults = array('gen_display_error' => "something went wrong please check settings. try refreshing page if all is correct.");
		$options = wp_parse_args(get_option('mh_calendar_peek_options'), $defaults);
		$calendarObj = $this->mh_format_calendar(); //get calendar (and parse)
		//check if we got array if not can't parse
		if(!is_array($calendarObj)){
			return print_r("<span class='error'>". $calendarObj ."</span>");
		}
		$week = $this->week_busy($calendarObj);
		$cal = $this->decorate_days($week);
		return print_r($cal);
	}
}
new mh_calendar_peek_plugin();
function mh_calendar_peek_plugin_template_display(){
	do_action( 'mh_calendar_peek_plugin_out', 50 );
}
?>
