<?php

/*
Plugin Name: Flash Video Player
Version: 2.1
Plugin URI: http://www.mac-dev.net
Description: Simplifies the process of adding video to a WordPress blog. Powered by Jeroen Wijering's FLV Media Player and SWFObject by Geoff Stearns.
Author: Joshua Eldridge
Author URI: http://www.mac-dev.net

Flash Video Plugin for Wordpress Copyright 2007  Joshua Eldridge

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

1) Includes Jeroen Wijering's FLV Media Player (Creative Commons "BY-NC-SA" License) v3.16
   Website: http://www.jeroenwijering.com/?item=JW_FLV_Player
   License: http://creativecommons.org/licenses/by-nc-sa/2.0/
2) Includes Geoff Stearns' SWFObject Javascript Library (MIT License) v2.0
   Website: http://code.google.com/p/swfobject/
   License: http://www.opensource.org/licenses/mit-license.php
*/


$videoid = 0;
$site_url = get_option('siteurl');


function FlashVideo_Parse($content) {
	$content = preg_replace_callback("/\[flashvideo ([^]]*)\/\]/i", "FlashVideo_Render", $content);
	return $content;
}

function FlashVideo_Render($matches) {
	global $videoid, $site_url;
	$output = '';
	$rss_output = '';
	$matches[1] = str_replace(array('&#8221;','&#8243;'), '', $matches[1]);
	preg_match_all('/(\w*)=(.*?) /i', $matches[1], $attributes);
	$arguments = array();

	foreach ( (array) $attributes[1] as $key => $value ) {
		// Strip out legacy quotes
		$arguments[$value] = str_replace('"', '', $attributes[2][$key]);
	}

	if ( !array_key_exists('filename', $arguments) ) {
		return '<div style="background-color:#f99; padding:10px;">Error: Required parameter "filename" is missing!</div>';
		exit;
	}

	$options = get_option('FlashVideoSettings');

	/* Override inline parameters */
	if ( !array_key_exists('displayheight', $arguments) ) {
		if( $options[4][1]['v'] == '' ) {
			if( !array_key_exists('height', $arguments) ) {
				$options[4][1]['v'] = $options[0][0]['v'] - 20;
			} else {
				$options[4][1]['v'] = $arguments['height'] - 20;
			}
		}
	} else {
		$options[4][1]['v'] = $arguments['displayheight'];
	}
	
	
	if ( array_key_exists('width', $arguments) ) {
		$options[0][1]['v'] = $arguments['width'];
	}
	if ( array_key_exists('height', $arguments) ) {
		$options[0][0]['v'] = $arguments['height'];
	}
	if ( array_key_exists('image', $arguments) ) {
		// Respect remote images
		if(strpos($arguments['image'], 'http://') === false) {
			$arguments['image'] = $site_url . '/' . $arguments['image'];
		}
		// If an image is found, embed it in the RSS feed.
		$rss_output .= '<img src="' . $arguments['image'] . '" />';
	} else {
		if ($options[0][2]['v'] == '') {
			// Place the default image, since there isn't one set.
			$rss_output .= '<img src="' . $site_url . '/' . 'wp-content/plugins/flash-video-player/default_video_player.gif" />';
		} else {
			$rss_output .= '<img src="' . $options[0][2]['v'] . '" />';
		}
	}
	if(strpos($arguments['filename'], 'http://') !== false || strpos($arguments['filename'], 'rtmp://') !== false) {
		// This is a remote file, so leave it alone but clean it up a little
		$arguments['filename'] = str_replace('&#038;','&',$arguments['filename']);
	} else {
		$arguments['filename'] = $site_url . '/' . $arguments['filename'];
	}
	
	$output .= "\n" . '<span id="video' . $videoid . '" class="flashvideo">' . "\n";
   	$output .= '<a href="http://www.macromedia.com/go/getflashplayer">Get the Flash Player</a> to see this player.</span>' . "\n";
    	$output .= '<script type="text/javascript">' . "\n";
	$output .= 'var s' . $videoid . ' = new SWFObject("' . $options[0][5]['v'] . '","n' . $videoid . '","' . $options[0][1]['v'] . '","' . $options[0][0]['v'] . '","7");' . "\n";
	$output .= 's' . $videoid . '.addParam("allowfullscreen","true");' . "\n";
	$output .= 's' . $videoid . '.addParam("allowscriptaccess","always");' . "\n";
	$output .= 's' . $videoid . '.addParam("wmode","opaque");' . "\n";
	$output .= 's' . $videoid . '.addVariable("javascriptid","n' . $videoid . '");' . "\n";
	for ( $i=0; $i<count($options);$i++ ) {
		foreach ( (array) $options[$i] as $key=>$value ) {
			/* Allow for inline override of all parameters */
			if ( array_key_exists($value['on'], $arguments) && $value['on'] ) {
				$value['v'] = $arguments[$value['on']];
			}
			if ( $value['v'] != '' && $value['on'] != 'location' ) {
				$output .= 's' . $videoid . '.addVariable("' . $value['on'] . '","' . $value['v'] . '");' . "\n";
			}
		}
	}
	$output .= 's' . $videoid . '.addVariable("file","' . $arguments['filename'] . '");' . "\n";
	$output .= 's' . $videoid . '.write("video' . $videoid . '");' . "\n";
	$output .= '</script>' . "\n";

	$videoid++;
	if(is_feed()) {
		return $rss_output;
	} else {
		return $output;
	}
}

function FlashVideoAddPage() {
	add_options_page('Flash Video', 'Flash Video', '8', 'flash-video-player.php', 'FlashVideoOptions');
}

function FlashVideoOptions() {
	$message = '';	
	$g = array(0=>'Basic Settings', 1=>'Player Color', 2=>'Appearance Settings', 3=>'Controlbar Settings', 4=>'Playlist Appearance', 5=>'Playback Behavior', 6=>'External Communication', 'P'=>'Additional Flash Parameters');

	$options = get_option('FlashVideoSettings');
	if ($_POST) {
		for($i=0; $i<count($options);$i++) {
			foreach( (array) $options[$i] as $key=>$value) {
				// Handle Checkboxes that don't send a value in the POST
				if($value['t'] == 'cb' && !isset($_POST[$options[$i][$key]['on']])) {
					$options[$i][$key]['v'] = 'false';
				}
				if($value['t'] == 'cb' && isset($_POST[$options[$i][$key]['on']])) {
					$options[$i][$key]['v'] = 'true';
				}
				// Handle all other changed values
				if(isset($_POST[$options[$i][$key]['on']]) && $value['t'] != 'cb') {
					$options[$i][$key]['v'] = $_POST[$options[$i][$key]['on']];
				}
			}
		}
		update_option('FlashVideoSettings', $options);
		$message = '<div class="updated"><p><strong>Options saved.</strong></p></div>';	
	}

	echo '<div class="wrap">';
	echo '<h2>Flash Video Options</h2>';
	echo $message;
	echo '<form method="post" action="options-general.php?page=flash-video-player.php">';
	echo "<p>Welcome to the flash video player plugin options menu! Here you can set all (or none) of the available player variables to default values for your website. If you have a question what valid values for the variables are, please consult the <a href='http://mac-dev.net/blog/flash-video-player-plugin-customization/'>online documentation</a>. If your question isn't answered there or in the <a href='http://mac-dev.net/blog/frequently-asked-questions/'>F.A.Q.</a>, please ask in the <a href='http://www.mac-dev.net/blog/forum'>forum</a>.</p>";

	foreach( (array) $options as $key=>$value) {
		echo '<h3>' . $g[$key] . '</h3>';
		echo '<table class="form-table">';
		foreach( (array) $value as $setting) {
			echo '<tr><th scope="row">' . $setting['dn'] . '</th><td>';
			if($setting['t'] == 'tx') {
				echo '<input type="text" name="' . $setting['on'] . '" value="' . $setting['v'] . '" class="code" />';
			} elseif ($setting['t'] == 'cb') {
				echo '<input type="checkbox" class="check" name="' . $setting['on'] . '" ';
				if($setting['v'] == 'true') {
					echo 'checked="checked"';
				}
				echo ' />';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	echo '<p class="submit"><input type="submit" method="post" value="Update Options &raquo;"></p>';
	echo '</form>';
	echo '</div>';
}

function FlashVideo_head() {
	global $site_url;
	$path = $site_url . '/wp-content/plugins/flash-video-player/swfobject.js';
	echo '<script type="text/javascript" src="' . $path . '"></script>' . "\n";
}

add_action('wp_head', 'FlashVideo_head');

function FlashVideoLoadDefaults() {
	$f = array();

	/*
	  Array Legend:
	  on = Option Name
	  dn = Display Name
	  t = Type
	  v = Default Value
	*/
	
	//Basic Settings
	
	$f[0][0]['on'] = 'height';
	$f[0][0]['dn'] = 'Player Height';
	$f[0][0]['t'] = 'tx';
	$f[0][0]['v'] = '260';
	
	$f[0][1]['on'] = 'width';
	$f[0][1]['dn'] = 'Player Width';
	$f[0][1]['t'] = 'tx';
	$f[0][1]['v'] = '320';

	$f[0][2]['on'] = 'image';
	$f[0][2]['dn'] = 'Poster Image';
	$f[0][2]['t'] = 'tx';
	$f[0][2]['v'] = '';
	
	$f[0][3]['on'] = 'id';
	$f[0][3]['dn'] = 'ID';
	$f[0][3]['t'] = 'tx';
	$f[0][3]['v'] = '';
	
	$f[0][4]['on'] = 'searchbar';
	$f[0][4]['dn'] = 'Search Bar';
	$f[0][4]['t'] = 'cb';
	$f[0][4]['v'] = 'false';
	
	$f[0][5]['on'] = 'location';
	$f[0][5]['dn'] = 'Media Player Location';
	$f[0][5]['t'] = 'tx';
	$f[0][5]['v'] = get_option('siteurl') . '/wp-content/plugins/flash-video-player/mediaplayer.swf';

	// Player Color

	$f[1][0]['on'] = 'backcolor';
	$f[1][0]['dn'] = 'Background Color';
	$f[1][0]['t'] = 'tx';
	$f[1][0]['v'] = '';

	$f[1][1]['on'] = 'frontcolor';
	$f[1][1]['dn'] = 'Foreground Color';
	$f[1][1]['t'] = 'tx';
	$f[1][1]['v'] = '';

	$f[1][2]['on'] = 'lightcolor';
	$f[1][2]['dn'] = 'Light Color';
	$f[1][2]['t'] = 'tx';
	$f[1][2]['v'] = '';

	$f[1][3]['on'] = 'screencolor';
	$f[1][3]['dn'] = 'Screen Color';
	$f[1][3]['t'] = 'tx';
	$f[1][3]['v'] = '0x000000';

	// Appearance Settings

	$f[2][0]['on'] = 'logo';
	$f[2][0]['dn'] = 'Logo File';
	$f[2][0]['t'] = 'tx';
	$f[2][0]['v'] = $site_url;

	$f[2][1]['on'] = 'overstretch';
	$f[2][1]['dn'] = 'Stretch Movie';
	$f[2][1]['t'] = 'tx';
	$f[2][1]['v'] = 'true';

	$f[2][2]['on'] = 'showeq';
	$f[2][2]['dn'] = 'Show Equalizer';
	$f[2][2]['t'] = 'cb';
	$f[2][2]['v'] = 'false';
	
	$f[2][3]['on'] = 'showicons';
	$f[2][3]['dn'] = 'Show Load/Play Icons';
	$f[2][3]['t'] = 'cb';
	$f[2][3]['v'] = 'true';
	
	// Controlbar Settings
		
	$f[3][0]['on'] = 'shownavigation';
	$f[3][0]['dn'] = 'Show Controlbar';
	$f[3][0]['t'] = 'cb';
	$f[3][0]['v'] = 'true';

	$f[3][1]['on'] = 'showstop';
	$f[3][1]['dn'] = 'Show Stop Button';
	$f[3][1]['t'] = 'cb';
	$f[3][1]['v'] = 'false';
	
	$f[3][2]['on'] = 'showdigits';
	$f[3][2]['dn'] = 'Show Digits';
	$f[3][2]['t'] = 'cb';
	$f[3][2]['v'] = 'true';
	
	$f[3][3]['on'] = 'showdownload';
	$f[3][3]['dn'] = 'Show Download Button';
	$f[3][3]['t'] = 'cb';
	$f[3][3]['v'] = 'false';
	
	$f[3][4]['on'] = 'usefullscreen';
	$f[3][4]['dn'] = 'Use Full Screen';
	$f[3][4]['t'] = 'cb';
	$f[3][4]['v'] = 'true';
	
	// Playlist Appearance
		
	$f[4][0]['on'] = 'autoscroll';
	$f[4][0]['dn'] = 'Automatic Scroll';
	$f[4][0]['t'] = 'cb';
	$f[4][0]['v'] = 'false';

	$f[4][1]['on'] = 'displayheight';
	$f[4][1]['dn'] = 'Display Height';
	$f[4][1]['t'] = 'tx';
	$f[4][1]['v'] = '';
	
	$f[4][2]['on'] = 'displaywidth';
	$f[4][2]['dn'] = 'Display Width';
	$f[4][2]['t'] = 'tx';
	$f[4][2]['v'] = '';
	
	$f[4][3]['on'] = 'thumbsinplaylist';
	$f[4][3]['dn'] = 'Display Thumbnails in Playlist';
	$f[4][3]['t'] = 'cb';
	$f[4][3]['v'] = 'true';	

	// Playback Behavior
	
	$f[5][0]['on'] = 'audio';
	$f[5][0]['dn'] = 'Additional Audio Track';
	$f[5][0]['t'] = 'tx';
	$f[5][0]['v'] = '';
	
	$f[5][1]['on'] = 'autostart';
	$f[5][1]['dn'] = 'Automatically Start Playing';
	$f[5][1]['t'] = 'cb';
	$f[5][1]['v'] = 'false';
	
	$f[5][2]['on'] = 'bufferlength';
	$f[5][2]['dn'] = 'Buffer Length';
	$f[5][2]['t'] = 'tx';
	$f[5][2]['v'] = '3';
		
	$f[5][3]['on'] = 'captions';
	$f[5][3]['dn'] = 'Captions';
	$f[5][3]['t'] = 'tx';
	$f[5][3]['v'] = '';

	$f[5][4]['on'] = 'fallback';
	$f[5][4]['dn'] = 'Fallback FLV';
	$f[5][4]['t'] = 'tx';
	$f[5][4]['v'] = '';

	$f[5][5]['on'] = 'repeat';
	$f[5][5]['dn'] = 'Repeat Play';
	$f[5][5]['t'] = 'cb';
	$f[5][5]['v'] = 'false';

	$f[5][6]['on'] = 'rotatetime';
	$f[5][6]['dn'] = 'Rotate Time';
	$f[5][6]['t'] = 'tx';
	$f[5][6]['v'] = '5';

	$f[5][7]['on'] = 'shuffle';
	$f[5][7]['dn'] = 'Shuffle Play';
	$f[5][7]['t'] = 'cb';
	$f[5][7]['v'] = 'false';

	$f[5][8]['on'] = 'smoothing';
	$f[5][8]['dn'] = 'Video Smoothing';
	$f[5][8]['t'] = 'cb';
	$f[5][8]['v'] = 'true';

	$f[5][9]['on'] = 'volume';
	$f[5][9]['dn'] = 'Starting Volume';
	$f[5][9]['t'] = 'tx';
	$f[5][9]['v'] = '80';
	
	// External Communication

	$f[6][0]['on'] = 'callback';
	$f[6][0]['dn'] = 'Callback URL';
	$f[6][0]['t'] = 'tx';
	$f[6][0]['v'] = '';

	$f[6][1]['on'] = 'enablejs';
	$f[6][1]['dn'] = 'Enable JavaScript';
	$f[6][1]['t'] = 'cb';
	$f[6][1]['v'] = 'true';
	
	$f[6][2]['on'] = 'link';
	$f[6][2]['dn'] = 'Link to Download File';
	$f[6][2]['t'] = 'tx';
	$f[6][2]['v'] = '';

	$f[6][3]['on'] = 'linkfromdisplay';
	$f[6][3]['dn'] = 'Hyperlink Player';
	$f[6][3]['t'] = 'cb';
	$f[6][3]['v'] = 'false';

	$f[6][4]['on'] = 'linktarget';
	$f[6][4]['dn'] = 'Hyperlink Target';
	$f[6][4]['t'] = 'tx';
	$f[6][4]['v'] = '_self';

	$f[6][5]['on'] = 'recommendations';
	$f[6][5]['dn'] = 'Stream Script';
	$f[6][5]['t'] = 'tx';
	$f[6][5]['v'] = '';

	$f[6][6]['on'] = 'searchlink';
	$f[6][6]['dn'] = 'Search Script Page';
	$f[6][6]['t'] = 'tx';
	$f[6][6]['v'] = 'http://search.longtail.tv/?q=';

	$f[6][7]['on'] = 'streamscript';
	$f[6][7]['dn'] = 'Stream Script';
	$f[6][7]['t'] = 'tx';
	$f[6][7]['v'] = '';
		
	$f[6][8]['on'] = 'type';
	$f[6][8]['dn'] = 'File Type';
	$f[6][8]['t'] = 'tx';
	$f[6][8]['v'] = '';
	
	return $f;
}

function FlashVideo_activate() {
	update_option('FlashVideoSettings', FlashVideoLoadDefaults());
}

register_activation_hook(__FILE__,'FlashVideo_activate');

function FlashVideo_deactivate() {
	delete_option('FlashVideoSettings');
}

register_deactivation_hook(__FILE__,'FlashVideo_deactivate');

// CONTENT FILTER

add_filter('the_content', 'FlashVideo_Parse');


// OPTIONS MENU

add_action('admin_menu', 'FlashVideoAddPage');

?>