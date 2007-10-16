<?php
/*
Plugin Name: Flash Video
Version: 2.0 
Plugin URI: http://www.mac-dev.net
Description: Simplifies the process of adding video to a WordPress blog. Powered by Jeroen Wijering's FLV Player and SWFObject by Geoff Stearns.
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

1) Includes Jeroen Wijering's FLV Player (Creative Commons "BY-NC-SA" License) v3.12
   Website: http://www.jeroenwijering.com/?item=Flash_Video_Player
   License: http://creativecommons.org/licenses/by-nc-sa/2.0/
2) Includes Geoff Stearns' SWFObject Javascript Library (MIT License) v1.5
   Website: http://blog.deconcept.com/swfobject/
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
	
	$matches[1] = str_replace(array('&#8221;','&#8243;'), '', $matches[1]);
	preg_match_all('/(\w*)=(.*?) /i', $matches[1], $attributes);
	$arguments = array();

	foreach ( (array) $attributes[1] as $key => $value ) {
		$arguments[$value] = $attributes[2][$key];
	}

	if ( !array_key_exists('filename', $arguments) ) {
		return '<div style="background-color:#f99; padding:10px;">Error: Required parameter "filename" is missing!</div>';
		exit;
	}

	$options = get_option('FlashVideoSettings');

	/* Override inline parameters */
	if ( array_key_exists('height', $arguments) ) {
		$options[0][1]['v'] = $arguments['height'];
	}
	if ( array_key_exists('width', $arguments) ) {
		$options[0][2]['v'] = $arguments['width'];
	}
	if ( array_key_exists('image', $arguments) ) {
		$arguments['image'] = $site_url . '/' . $arguments['image'];
	}
	if ( array_key_exists('floatingcontrols', $arguments) ) {
		if ( $arguments['floatingcontrols'] == 'true' ) {
			$options[0][0]['v'] = $options[0][1]['v'];
		}
		if ( $arguments['floatingcontrols'] == 'false' ) {
			$options[0][1]['v'] += 20;
			$options[0][0]['v'] = '';
		}
	}
		
    $output .= "\n" . '<span id="s' . $videoid . '" class="flashvideo">' . "\n";
    $output .= '<a href="http://www.macromedia.com/go/getflashplayer">Get the Flash Player</a> to see this player.</span>' . "\n";
    $output .= '<script type="text/javascript">' . "\n";
	$output .= 'var s' . $videoid . ' = new SWFObject("' . $options[0][4]['v'] . '","s' . $videoid . '","' . $options[0][2]['v'] . '","' . $options[0][1]['v'] . '","7");' . "\n";
	//$output .= 's1.addParam("allowfullscreen","true");';
	for ( $i=0; $i<count($options);$i++ ) {
		foreach ( (array) $options[$i] as $key=>$value ) {
			/* Allow for inline override of all parameters */
			if ( array_key_exists($value['on'], $arguments) && $value['on'] != 'displayheight') {
				$value['v'] = $arguments[$value['on']];
			}
			if ( $value['on'] == 'displayheight' ) {
				if ( $value['v'] == 'true' ) {
					$value['v'] = $options[0][1]['v'];
				} else {
					$value['v'] += 20;
					$value['v'] = '';
				}
			}
			if ( $value['v'] != '' && $value['on'] != 'height' && $value['on'] != 'width' && $value['on'] != 'location' ) {
				$output .= 's' . $videoid . '.addVariable("' . $value['on'] . '","' . $value['v'] . '");' . "\n";
			}
		}
	}
	$output .= 's' . $videoid . '.addVariable("file","' . $site_url . '/' . $arguments['filename'] . '");' . "\n";
	$output .= 's' . $videoid . '.write("s' . $videoid . '");' . "\n";
	$output .= '</script>' . "\n";

	$videoid++;
	return $output;
}

function FlashVideoAddPage() {
	add_options_page('Flash Video', 'Flash Video', '8', 'flashvideo.php', 'FlashVideoOptions');
}

function FlashVideoOptions() {
	$message = '';	
	$g = array(0=>'Basic', 1=>'Player Color', 2=>'Appearance', 3=>'Playback', 4=>'Interaction');

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
	echo '<form method="post" action="options-general.php?page=flashvideo.php">';
	echo '<p class="submit"><input type="submit" method="post" value="Update Options &raquo;"></p>';

	echo "<p>Welcome to the flash video player plugin options menu! Here you can set all (or none) of the available player variables to default values for your website. If you have a question what valid values for the variables are, please consult the <a href='http://mac-dev.net/blog/flash-video-player-plugin-customization/'>online documentation</a>. If your question isn't answered there or in the <a href='http://mac-dev.net/blog/frequently-asked-questions/'>F.A.Q.</a>, please ask in the <a href='http://www.mac-dev.net/blog/forum'>forum</a>.</p>";

	foreach( (array) $options as $key=>$value) {
		echo '<fieldset class="options">';
		echo '<legend>' . $g[$key] . '</legend>';
		echo '<table class="optiontable">';
		foreach( (array) $value as $setting) {
			echo '<tr><th scope="row">' . $setting['dn'] . '</th><td>';
			if($setting['t'] == 'tx') {
				echo '<input type="text" name="' . $setting['on'] . '" value="' . $setting['v'] . '" />';
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
		echo '</fieldset>';
	}

	echo '<p class="submit"><input type="submit" method="post" value="Update Options &raquo;"></p>';
	echo '</form>';
	echo '</div>';
}

function FlashVideo_head() {
	$path = $site_url . '/wp-content/plugins/flashvideo/swfobject.js';
	echo '<script type="text/javascript" src="' . $path . '"></script>' . "\n";
}

add_action('wp_head', 'FlashVideo_head');

function FlashVideoLoadDefaults() {
	global $site_url;
	$f = array();

	/*
	  Array Legend:
	  gn = Group Name
	  id = Unique Identifier
	  on = Option Name
	  dn = Display Name
	  t = Type
	  d = Default
	  g = Groups
	*/
	
	//Basic Settings
	
	$f[0][0]['on'] = 'displayheight';
	$f[0][0]['dn'] = 'Floating Controls';
	$f[0][0]['t'] = 'cb';
	$f[0][0]['v'] = '';

	$f[0][1]['on'] = 'width';
	$f[0][1]['dn'] = 'Player Width';
	$f[0][1]['t'] = 'tx';
	$f[0][1]['v'] = '320';
	
	$f[0][2]['on'] = 'height';
	$f[0][2]['dn'] = 'Player Height';
	$f[0][2]['t'] = 'tx';
	$f[0][2]['v'] = '240';

	$f[0][3]['on'] = 'image';
	$f[0][3]['dn'] = 'Poster Image';
	$f[0][3]['t'] = 'tx';
	$f[0][3]['v'] = '';
	
	$f[0][4]['on'] = 'location';
	$f[0][4]['dn'] = 'SWF Location';
	$f[0][4]['t'] = 'tx';
	$f[0][4]['v'] = $site_url . '/wp-content/plugins/flashvideo/flvplayer.swf';

	// Player Color

	$f[1][5]['on'] = 'backcolor';
	$f[1][5]['dn'] = 'Background Color';
	$f[1][5]['t'] = 'tx';
	$f[1][5]['v'] = '';

	$f[1][6]['on'] = 'frontcolor';
	$f[1][6]['dn'] = 'Foreground Color';
	$f[1][6]['t'] = 'tx';
	$f[1][6]['v'] = '';

	$f[1][7]['on'] = 'lightcolor';
	$f[1][7]['dn'] = 'Light Color';
	$f[1][7]['t'] = 'tx';
	$f[1][7]['v'] = '';

	// Appearance Settings

	$f[2][8]['on'] = 'autoscroll';
	$f[2][8]['dn'] = 'Automatic Scroll';
	$f[2][8]['t'] = 'cb';
	$f[2][8]['v'] = 'true';

	$f[2][9]['on'] = 'displaywidth';
	$f[2][9]['dn'] = 'Display Width';
	$f[2][9]['t'] = 'tx';
	$f[2][9]['v'] = '';

	$f[2][10]['on'] = 'largecontrols';
	$f[2][10]['dn'] = 'Large Controls';
	$f[2][10]['t'] = 'cb';
	$f[2][10]['v'] = 'false';

	$f[2][11]['on'] = 'logo';
	$f[2][11]['dn'] = 'Logo File';
	$f[2][11]['t'] = 'tx';
	$f[2][11]['v'] = $site_url;

	$f[2][12]['on'] = 'overstretch';
	$f[2][12]['dn'] = 'Stretch Movie';
	$f[2][12]['t'] = 'tx';
	$f[2][12]['v'] = 'true';

	$f[2][13]['on'] = 'showdigits';
	$f[2][13]['dn'] = 'Show Counter';
	$f[2][13]['t'] = 'cb';
	$f[2][13]['v'] = 'true';

	$f[2][14]['on'] = 'showdownload';
	$f[2][14]['dn'] = 'Show Download Button';
	$f[2][14]['t'] = 'cb';
	$f[2][14]['v'] = 'false';

	$f[2][15]['on'] = 'showeq';
	$f[2][15]['dn'] = 'Show Equalizer';
	$f[2][15]['t'] = 'cb';
	$f[2][15]['v'] = 'false';

	$f[2][16]['on'] = 'showicons';
	$f[2][16]['dn'] = 'Show Load/Play Icons';
	$f[2][16]['t'] = 'cb';
	$f[2][16]['v'] = 'true';

	$f[2][17]['on'] = 'showvolume';
	$f[2][17]['dn'] = 'Show Volume';
	$f[2][17]['t'] = 'cb';
	$f[2][17]['v'] = 'true';

	$f[2][18]['on'] = 'thumbsinplaylist';
	$f[2][18]['dn'] = 'Show Thumbnails in Playlist';
	$f[2][18]['t'] = 'cb';
	$f[2][18]['v'] = 'false';

	// Playback Settings

	$f[3][19]['on'] = 'autostart';
	$f[3][19]['dn'] = 'Autostart';
	$f[3][19]['t'] = 'tx';
	$f[3][19]['v'] = 'false';

	$f[3][20]['on'] = 'bufferlength';
	$f[3][20]['dn'] = 'Buffer Length';
	$f[3][20]['t'] = 'tx';
	$f[3][20]['v'] = '3';

	$f[3][21]['on'] = 'repeat';
	$f[3][21]['dn'] = 'Repeat Play';
	$f[3][21]['t'] = 'tx';
	$f[3][21]['v'] = 'false';

	$f[3][22]['on'] = 'rotatetime';
	$f[3][22]['dn'] = 'Rotate Time';
	$f[3][22]['t'] = 'tx';
	$f[3][22]['v'] = '5';

	$f[3][23]['on'] = 'shuffle';
	$f[3][23]['dn'] = 'Shuffle Playback';
	$f[3][23]['t'] = 'tx';
	$f[3][23]['v'] = '';

	$f[3][24]['on'] = 'smoothing';
	$f[3][24]['dn'] = 'Smooth Playback';
	$f[3][24]['t'] = 'cb';
	$f[3][24]['v'] = 'true';

	$f[3][25]['on'] = 'volume';
	$f[3][25]['dn'] = 'Starting Volume';
	$f[3][25]['t'] = 'tx';
	$f[3][25]['v'] = '80';

	// Interaction Settings

	$f[4][26]['on'] = 'audio';
	$f[4][26]['dn'] = 'Audio Track';
	$f[4][26]['t'] = 'tx';
	$f[4][26]['v'] = '';

	$f[4][27]['on'] = 'callback';
	$f[4][27]['dn'] = 'Callback URL';
	$f[4][27]['t'] = 'tx';
	$f[4][27]['v'] = '';

	$f[4][28]['on'] = 'captions';
	$f[4][28]['dn'] = 'Captions URL';
	$f[4][28]['t'] = 'tx';
	$f[4][28]['v'] = '';

	$f[4][29]['on'] = 'enablejs';
	$f[4][29]['dn'] = 'Enable JavaScript';
	$f[4][29]['t'] = 'cb';
	$f[4][29]['v'] = 'true';

	$f[4][30]['on'] = 'fsbuttonlink';
	$f[4][30]['dn'] = 'Alternate Full Screen URL';
	$f[4][30]['t'] = 'tx';
	$f[4][30]['v'] = '';

	$f[4][31]['on'] = 'id';
	$f[4][31]['dn'] = 'ID';
	$f[4][31]['t'] = 'tx';
	$f[4][31]['v'] = '';

	$f[4][32]['on'] = 'link';
	$f[4][32]['dn'] = 'Download Link';
	$f[4][32]['t'] = 'tx';
	$f[4][32]['v'] = '';

	$f[4][33]['on'] = 'linkfromdisplay';
	$f[4][33]['dn'] = 'Hyperlink Player';
	$f[4][33]['t'] = 'cb';
	$f[4][33]['v'] = 'false';

	$f[4][34]['on'] = 'linktarget';
	$f[4][34]['dn'] = 'Hyperlink URL';
	$f[4][34]['t'] = 'tx';
	$f[4][34]['v'] = '';

	$f[4][35]['on'] = 'streamscript';
	$f[4][35]['dn'] = 'Stream Script';
	$f[4][35]['t'] = 'tx';
	$f[4][35]['v'] = '';

	$f[4][36]['on'] = 't';
	$f[4][36]['dn'] = 'File Type';
	$f[4][36]['t'] = 'tx';
	$f[4][36]['v'] = 'autodetect';

	$f[4][37]['on'] = 'useaudio';
	$f[4][37]['dn'] = 'Use Extra Audio';
	$f[4][37]['t'] = 'cb';
	$f[4][37]['v'] = 'false';

	$f[4][38]['on'] = 'usecaptions';
	$f[4][38]['dn'] = 'Use Captions';
	$f[4][38]['t'] = 'cb';
	$f[4][38]['v'] = 'false';

	$f[4][39]['on'] = 'usefullscreen';
	$f[4][39]['dn'] = 'Use Flash 9 Fullscreen';
	$f[4][39]['t'] = 'cb';
	$f[4][39]['v'] = 'true';

	$f[4][40]['on'] = 'usekeys';
	$f[4][40]['dn'] = 'Use Keyboard Shortcuts';
	$f[4][40]['t'] = 'cb';
	$f[4][40]['v'] = 'false';
	
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