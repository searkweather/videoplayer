<?php

/*
Plugin Name: Flash Video Player
Version: 3.1
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

1) Includes Jeroen Wijering's FLV Media Player (Creative Commons "BY-NC-SA" License) v4.3
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
	preg_match_all('/([.\w]*)=(.*?) /i', $matches[1], $attributes);
	$arguments = array();

	foreach ( (array) $attributes[1] as $key => $value ) {
		// Strip out legacy quotes
		$arguments[$value] = str_replace('"', '', $attributes[2][$key]);
	}

	if ( !array_key_exists('filename', $arguments) && !array_key_exists('file', $arguments) ) {
		return '<div style="background-color:#ff9;padding:10px;"><p>Error: Required parameter "file" is missing!</p></div>';
		exit;
	}
	
	//Deprecate filename in favor of file. 
	if(array_key_exists('filename', $arguments)) {
		$arguments['file'] = $arguments['filename'];
	}

	$options = get_option('FlashVideoSettings');

	/* Override inline parameters */
	
	if ( array_key_exists('width', $arguments) ) {
		$options[2][5]['v'] = $arguments['width'];
	}
	if ( array_key_exists('height', $arguments) ) {
		$options[2][1]['v'] = $arguments['height'];
	}
	if ( array_key_exists('image', $arguments) ) {
		// Respect remote images
		if(strpos($arguments['image'], 'http://') === false) {
			$arguments['image'] = $site_url . '/' . $arguments['image'];
		}
		// If an image is found, embed it in the RSS feed.
		$rss_output .= '<img src="' . $arguments['image'] . '" />';
	} else {
		if ($options[0][3]['v'] == '') {
			// Place the default image, since there isn't one set.
			$rss_output .= '<img src="' . $site_url . '/' . 'wp-content/plugins/flash-video-player/default_video_player.gif" />';
		} else {
			$rss_output .= '<img src="' . $options[0][3]['v'] . '" />';
		}
	}
	if(strpos($arguments['file'], 'http://') !== false || isset($arguments['streamer']) || strpos($arguments['file'], 'https://') !== false) {
		// This is a remote file, so leave it alone but clean it up a little
		$arguments['file'] = str_replace('&#038;','&',$arguments['file']);
	} else {
		$arguments['file'] = $site_url . '/' . $arguments['file'];
	}
	$output .= "\n" . '<span id="video' . $videoid . '" class="flashvideo">' . "\n";
   	$output .= '<a href="http://www.macromedia.com/go/getflashplayer">Get the Flash Player</a> to see this player.</span>' . "\n";
    	$output .= '<script type="text/javascript">' . "\n";
	$output .= 'var s' . $videoid . ' = new SWFObject("' . $site_url . '/wp-content/plugins/flash-video-player/mediaplayer/player.swf' . '","n' . $videoid . '","' . $options[2][5]['v'] . '","' . $options[2][1]['v'] . '","7");' . "\n";
	$output .= 's' . $videoid . '.addParam("allowfullscreen","true");' . "\n";
	$output .= 's' . $videoid . '.addParam("allowscriptaccess","always");' . "\n";
	$output .= 's' . $videoid . '.addParam("wmode","opaque");' . "\n";
	$output .= 's' . $videoid . '.addVariable("id","n' . $videoid . '");' . "\n";
	for ( $i=0; $i<count($options);$i++ ) {
		foreach ( (array) $options[$i] as $key=>$value ) {
			/* Allow for inline override of all parameters */
			if ( array_key_exists($value['on'], $arguments) && $value['on'] ) {
				$value['v'] = $arguments[$value['on']];
			}
			if ( $value['v'] != '' ) {
				// Check to see if we're processing a "skin". If so, make the filename absolute using the 
				// fully qualified path. This will ensure the player displays correctly on category pages as well.
				if($value['on'] == 'skin') {
					if($value['v'] != 'undefined') {
						$output .= 's' . $videoid . '.addVariable("' . $value['on'] . '","' . $site_url . '/wp-content/plugins/flash-video-player/skins/' . $value['v'] . '/' . trim($value['v']) . '.swf");' . "\n";
					}
				} else {
					$output .= 's' . $videoid . '.addVariable("' . $value['on'] . '","' . trim($value['v']) . '");' . "\n";
				}		
			}
		}
	}
	$output .= 's' . $videoid . '.addVariable("file","' . $arguments['file'] . '");' . "\n";
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
	global $site_url;
	$message = '';	
	$g = array(0=>'File Properties', 1=>'Colors', 2=>'Layout', 3=>'Behavior', 4=>'External');

	$options = get_option('FlashVideoSettings');
	// Process form submission
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

	$skins_dir = str_replace('wp-admin', 'wp-content', dirname($_SERVER['SCRIPT_FILENAME'])) . '/plugins/flash-video-player/skins/';
	$skins = array();
	
	//echo "<pre>";
	//echo print_r($options);
	//echo "</pre>";
	
	// Pull the directories listed in the skins folder to generate the dropdown list with valid skin files
	chdir($skins_dir);
	if ($handle = opendir($skins_dir)) {
	    while (false !== ($file = readdir($handle))) {
	        if ($file != "." && $file != "..") {
				if(is_dir($file)) {
					$skins[] = $file;
				}
	        }
	    }
	    closedir($handle);
	}
	// Add the default value onto the beginning of the skins array
	array_unshift($skins, 'undefined');
	$options[2][4]['op'] = $skins;

	foreach( (array) $options as $key=>$value) {
		echo '<h3>' . $g[$key] . '</h3>' . "\n";
		echo '<table class="form-table">' . "\n";
		foreach( (array) $value as $setting) {
			echo '<tr><th scope="row">' . $setting['dn'] . '</th><td>' . "\n";
			switch ($setting['t']) {
				case 'tx':
					echo '<input type="text" name="' . $setting['on'] . '" value="' . $setting['v'] . '" />';
					break;
				case 'dd':
					echo '<select name="' . $setting['on'] . '">';
					foreach( (array) $setting['op'] as $v) {
						$selected = '';
						if($v == $setting['v']) {
							$selected = ' selected';
						}
						echo '<option value="' . $v . '"' . $selected . '>' . ucfirst($v) . '</option>';
					}
					echo '</select>';
					break;
				case 'cb':
					echo '<input type="checkbox" class="check" name="' . $setting['on'] . '" ';
					if($setting['v'] == 'true') {
						echo 'checked="checked"';
					}
					echo ' />';
					break;
				}
				echo '</td></tr>' . "\n";
			}
			echo '</table>' . "\n";
		}
	echo '<p class="submit"><input class="button-primary" type="submit" method="post" value="Update Options"></p>';
	echo '</form>';
	echo '</div>';
}

function FlashVideo_head() {
	global $site_url;
	echo '<script type="text/javascript" src="' . $site_url . '/wp-content/plugins/flash-video-player/swfobject.js"></script>' . "\n";
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
	
	//File Properties
	
	$f[0][0]['on'] = 'author';
	$f[0][0]['dn'] = 'Author';
	$f[0][0]['t'] = 'tx';
	$f[0][0]['v'] = '';

	$f[0][1]['on'] = 'date';
	$f[0][1]['dn'] = 'Publish Date';
	$f[0][1]['t'] = 'tx';
	$f[0][1]['v'] = '';

	$f[0][2]['on'] = 'description';
	$f[0][2]['dn'] = 'Description';
	$f[0][2]['t'] = 'tx';
	$f[0][2]['v'] = '';

	$f[0][3]['on'] = 'image';
	$f[0][3]['dn'] = 'Preview Image';
	$f[0][3]['t'] = 'tx';
	$f[0][3]['v'] = '';
	
	$f[0][4]['on'] = 'link';
	$f[0][4]['dn'] = 'Link URL';
	$f[0][4]['t'] = 'tx';
	$f[0][4]['v'] = '';

	$f[0][5]['on'] = 'captions';
	$f[0][5]['dn'] = 'Captions';
	$f[0][5]['t'] = 'tx';
	$f[0][5]['v'] = '';
	
	$f[0][6]['on'] = 'type';
	$f[0][6]['dn'] = 'Type';
	$f[0][6]['t'] = 'tx';
	$f[0][6]['v'] = '';
	
	$f[0][7]['on'] = 'streamer';
	$f[0][7]['dn'] = 'Streamer';
	$f[0][7]['t'] = 'tx';
	$f[0][7]['v'] = '';
	//Colors
	
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
	$f[1][3]['v'] = '';
	
	//Layout
	
	$f[2][0]['on'] = 'controlbar';
	$f[2][0]['dn'] = 'Controlbar';
	$f[2][0]['t'] = 'dd';
	$f[2][0]['v'] = 'bottom';
	$f[2][0]['op'] = array('none', 'bottom', 'over');

	$f[2][1]['on'] = 'height';
	$f[2][1]['dn'] = 'Player Height';
	$f[2][1]['t'] = 'tx';
	$f[2][1]['v'] = '280';

	$f[2][2]['on'] = 'playlist';
	$f[2][2]['dn'] = 'Playlist';
	$f[2][2]['t'] = 'dd';
	$f[2][2]['v'] = 'none';
	$f[2][2]['op'] = array('none','bottom', 'over', 'right');
	
	$f[2][3]['on'] = 'playlistsize';
	$f[2][3]['dn'] = 'Playlist Size';
	$f[2][3]['t'] = 'tx';
	$f[2][3]['v'] = '';
	
	$f[2][4]['on'] = 'skin';
	$f[2][4]['dn'] = 'Skin';
	$f[2][4]['t'] = 'dd';
	$f[2][4]['v'] = 'undefined';
	$f[2][4]['op'] = array('undefined', 'bright', 'overlay', 'simple', 'stylish', 'swift', 'thin');
	
	$f[2][5]['on'] = 'width';
	$f[2][5]['dn'] = 'Player Width';
	$f[2][5]['t'] = 'tx';
	$f[2][5]['v'] = '400';

	//Behavior

	$f[3][0]['on'] = 'autostart';
	$f[3][0]['dn'] = 'Auto Start';
	$f[3][0]['t'] = 'cb';
	$f[3][0]['v'] = 'false';

	$f[3][1]['on'] = 'bufferlength';
	$f[3][1]['dn'] = 'Buffer Length';
	$f[3][1]['t'] = 'tx';
	$f[3][1]['v'] = '1';
	
	$f[3][2]['on'] = 'displayclick';
	$f[3][2]['dn'] = 'Display Click';
	$f[3][2]['t'] = 'dd';
	$f[3][2]['v'] = 'play';
	$f[3][2]['op'] = array('play', 'link', 'fullscreen', 'none', 'mute', 'next');
	
	$f[3][3]['on'] = 'icons';
	$f[3][3]['dn'] = 'Play Icon';
	$f[3][3]['t'] = 'cb';
	$f[3][3]['v'] = 'true';
	
	$f[3][4]['on'] = 'linktarget';
	$f[3][4]['dn'] = 'Link Target';
	$f[3][4]['t'] = 'tx';
	$f[3][4]['v'] = '_blank';
	
	$f[3][5]['on'] = 'logo';
	$f[3][5]['dn'] = 'Logo';
	$f[3][5]['t'] = 'tx';
	$f[3][5]['v'] = '';
	
	$f[3][6]['on'] = 'mute';
	$f[3][6]['dn'] = 'Mute Sounds';
	$f[3][6]['t'] = 'cb';
	$f[3][6]['v'] = 'false';
	
	$f[3][7]['on'] = 'quality';
	$f[3][7]['dn'] = 'High Quality';
	$f[3][7]['t'] = 'cb';
	$f[3][7]['v'] = 'true';
	
	$f[3][8]['on'] = 'repeat';
	$f[3][8]['dn'] = 'Repeat';
	$f[3][8]['t'] = 'dd';
	$f[3][8]['v'] = 'none';
	$f[3][8]['op'] = array('none', 'list', 'always', 'single');

	$f[3][9]['on'] = 'resizing';
	$f[3][9]['dn'] = 'Resizing';
	$f[3][9]['t'] = 'cb';
	$f[3][9]['v'] = 'true';
	
	$f[3][10]['on'] = 'shuffle';
	$f[3][10]['dn'] = 'Shuffle';
	$f[3][10]['t'] = 'cb';
	$f[3][10]['v'] = 'false';
	
	$f[3][11]['on'] = 'stretching';
	$f[3][11]['dn'] = 'Stretching';
	$f[3][11]['t'] = 'dd';
	$f[3][11]['v'] = 'uniform';
	$f[3][11]['op'] = array('none', 'exactfit', 'uniform', 'fill');
	
	$f[3][12]['on'] = 'volume';
	$f[3][12]['dn'] = 'Startup Volume';
	$f[3][12]['t'] = 'dd';
	$f[3][12]['v'] = '90';
	$f[3][12]['op'] = array('0', '10', '20', '30', '40', '50', '60', '70', '80', '90', '100');
	
	//External
		
	$f[4][0]['on'] = 'abouttext';
	$f[4][0]['dn'] = 'About Text';
	$f[4][0]['t'] = 'tx';
	$f[4][0]['v'] = '';

	$f[4][1]['on'] = 'aboutlink';
	$f[4][1]['dn'] = 'About Link';
	$f[4][1]['t'] = 'tx';
	$f[4][1]['v'] = 'http://www.longtailvideo.com/players/';
	
	$f[4][2]['on'] = 'plugins';
	$f[4][2]['dn'] = 'Plugins';
	$f[4][2]['t'] = 'tx';
	$f[4][2]['v'] = '';

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
