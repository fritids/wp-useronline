<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: http://www.lesterchan.net/portfolio/programming.php
Description: Adds A Useronline Feature To WordPress
Version: 2.04
Author: GaMerZ
Author URI: http://www.lesterchan.net
*/


/*  Copyright 2005  Lester Chan  (email : gamerz84@hotmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


### UserOnline Table Name
$wpdb->useronline = $table_prefix . 'useronline';


### Function: WP-UserOnline Menu
add_action('admin_menu', 'useronline_menu');
function useronline_menu() {
	if (function_exists('add_submenu_page')) {
		add_submenu_page('index.php',  __('WP-UserOnline'),  __('WP-UserOnline'), 1, 'useronline/useronline.php', 'display_useronline');
	}
	if (function_exists('add_options_page')) {
		add_options_page(__('Useronline'), __('Useronline'), 'manage_options', 'useronline/useronline-options.php') ;
	}
}


### Function: Get IP Address
if(!function_exists('get_ipaddress')) {
	function get_ipaddress() {
		if (empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip_address = $_SERVER["REMOTE_ADDR"];
		} else {
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		if(strpos($ip_address, ',') !== false) {
			$ip_address = explode(',', $ip_address);
			$ip_address = $ip_address[0];
		}
		return $ip_address;
	}
}


### Function: Process UserOnline
add_action('admin_head', 'useronline');
add_action('wp_head', 'useronline');
function useronline() {
	global $wpdb, $useronline, $user_identity;
	// Useronline Settings
	$timeoutseconds = get_settings('useronline_timeout');
	$timestamp = current_time('timestamp');
	$timeout = ($timestamp-$timeoutseconds);
	$ip = get_ipaddress();
	$url = addslashes(urlencode($_SERVER['REQUEST_URI']));
	$useragent = $_SERVER['HTTP_USER_AGENT'];

	// Check For Members
	if(!empty($_COOKIE['comment_author_'.COOKIEHASH]))  {
		$memberonline = addslashes(trim($_COOKIE['comment_author_'.COOKIEHASH]));
		$where = "WHERE username='$memberonline'";
	// Check For Admins
	} elseif(!empty($user_identity)) {
		$memberonline = addslashes($user_identity);
		$where = "WHERE username='$memberonline'";
	// Check For Guests
	} else { 
		$memberonline = 'Guest';
		$where = "WHERE ip='$ip'";
	}
	// Check For Bot
	$bots = get_settings('useronline_bots');
	foreach ($bots as $name => $lookfor) { 
		if (stristr($useragent, $lookfor) !== false) { 
			$memberonline = addslashes($name);
			$where = "WHERE ip='$ip'";
			break;
		} 
	}
	$useragent = addslashes($useragent);

	// Check For Page Title
	$make_page = wp_title('&raquo;', false);
	if(empty($make_page)) {
		$make_page = get_bloginfo('name');
	} elseif(is_single()) {
		$make_page = get_bloginfo('name').' &raquo; Blog Archive '.$make_page;
	} else {
		$make_page = get_bloginfo('name').$make_page;
	}
	$make_page = addslashes($make_page);
	
	// Delete Users
	$delete_users = $wpdb->query("DELETE FROM $wpdb->useronline $where OR (timestamp < $timeout)");
	
	// Insert Users
	$insert_user = $wpdb->query("INSERT INTO $wpdb->useronline VALUES ('$timestamp', '$memberonline', '$useragent', '$ip', '$make_page', '$url')");

	// Count Users Online
	$useronline = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline"));
	
	// Get Most User Online
	$most_useronline = intval(get_settings('useronline_most_users'));

	// Check Whether Current Users Online Is More Than Most Users Online
	if($useronline > $most_useronline) {
		update_option('useronline_most_users', $useronline);
		update_option('useronline_most_timestamp', current_time('timestamp'));
	}
}


### Function: Display UserOnline
if(!function_exists('get_useronline')) {
	function get_useronline($user = 'User', $users = 'Users', $display = true) {
		global $useronline;
		// Display User Online
		if($display) {
			if($useronline > 1) {
				echo '<b>'.number_format($useronline)."</b> $users ".__('Online');
			} else {
				echo "<b>$useronline</b> $user ".__('Online');
			}
		} else {
			return $useronline;
		}
	}
}

### Function: Display Max UserOnline
if(!function_exists('get_most_useronline')) {
	function get_most_useronline($display = true) {
		$most_useronline_users = intval(get_settings('useronline_most_users'));
		if($display) {
			echo number_format($most_useronline_users);
		} else {
			return $most_useronline_users;
		}
	}
}


### Function: Display Max UserOnline Date
if(!function_exists('get_most_useronline_date')) {
	function get_most_useronline_date($date_format = 'jS F Y, H:i', $display =true) {
		$most_useronline_timestamp = get_settings('useronline_most_timestamp');
		$most_useronline_date = gmdate($date_format, $most_useronline_timestamp);
		if($display) {
			echo $most_useronline_date;
		} else {
			return$most_useronline_date;
		}
	}
}


### Function: Display Users Browsing The Site
function get_users_browsing_site() {
	global $wpdb;

	// Get Users Browsing Site
	$page_url = addslashes(urlencode($_SERVER['REQUEST_URI']));
	$users_browse = $wpdb->get_results("SELECT username FROM $wpdb->useronline");

	// Variables
	$members = array();
	$total_users = 0;
	$total_members = 0;
	$total_guests = 0;
	$total_bots = 0;
	$nicetext_members = '';
	$nicetext_guests = '';
	$nicetext_bots = '';

	// If There Is Users Browsing, Then We Execute
	if($users_browse) {
		// Reassign Bots Name
		$bots = get_settings('useronline_bots');
		$bots_name = array();
		foreach($bots as $botname => $botlookfor) {
			$bots_name[] = $botname;
		}
		// Get Users Information
		foreach($users_browse as $user_browse) {
			if($user_browse->username == 'Guest') {
				$total_guests++;
			} elseif(in_array($user_browse->username, $bots_name)) {
				$total_bots++;
			} else {
				$members[] = stripslashes($user_browse->username);
				$total_members++;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);

		// Nice Text For Guests
		if($total_guests == 1) { 
			$nicetext_guests = $total_guests.' '.__('Guest');
		} else {
			$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
		}
		// Nice Text For Bots
		if($total_bots == 1) {
			$nicetext_bots = $total_bots.' '.__('Bot'); 
		} else {
			$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
		}

		// Print Member Name
		if($members) {
			$temp_member = '';
			foreach($members as $member) {
				$temp_member .= '<a href="'.get_settings('home').'/wp-stats.php?author='.urlencode($member).'">'.$member.'</a>, ';
			}
			if(!function_exists('get_totalposts')) {
				$temp_member = strip_tags($temp_member);
			}
		}
		// Print Guests
		if($total_guests > 0) {
			$temp_member .= $nicetext_guests.', ';
		}
		// Print Bots
		if($total_bots > 0) {
			$temp_member .= $nicetext_bots.', ';
		}
		// Print User Count
		$temp_member = substr($temp_member, 0, -2);
		echo __('Users: ').'<b>'.$temp_member.'</b><br />';
	} else {
		// This Should Not Happen
		_e('No User Is Browsing This Page');
	}
}


### Function: Display Users Browsing The Page
function get_users_browsing_page() {
	global $wpdb;

	// Get Users Browsing Page
	$page_url = addslashes(urlencode($_SERVER['REQUEST_URI']));
	$users_browse = $wpdb->get_results("SELECT username FROM $wpdb->useronline WHERE url = '$page_url'");

	// Variables
	$members = array();
	$total_users = 0;
	$total_members = 0;
	$total_guests = 0;
	$total_bots = 0;
	$nicetext_members = '';
	$nicetext_guests = '';
	$nicetext_bots = '';

	// If There Is Users Browsing, Then We Execute
	if($users_browse) {
		// Reassign Bots Name
		$bots = get_settings('useronline_bots');
		$bots_name = array();
		foreach($bots as $botname => $botlookfor) {
			$bots_name[] = $botname;
		}
		// Get Users Information
		foreach($users_browse as $user_browse) {
			if($user_browse->username == 'Guest') {
				$total_guests++;
			} elseif(in_array($user_browse->username, $bots_name)) {
				$total_bots++;
			} else {
				$members[] = stripslashes($user_browse->username);
				$total_members++;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);

		// Nice Text For Members
		if($total_members == 1) {
			$nicetext_members = $total_members.' '.__('Member');
		} else {
			$nicetext_members = number_format($total_members).' '.__('Members');
		}
		// Nice Text For Guests
		if($total_guests == 1) { 
			$nicetext_guests = $total_guests.' '.__('Guest');
		} else {
			$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
		}
		// Nice Text For Bots
		if($total_bots == 1) {
			$nicetext_bots = $total_bots.' '.__('Bot'); 
		} else {
			$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
		}
		
		// Print User Count
		echo __('Users Browsing This Page: ').'<b>'.number_format($total_users).'</b> ('.$nicetext_members.', '.$nicetext_guests.' '.__('and').' '.$nicetext_bots.')<br />';

		// Print Member Name
		if($members) {
			$temp_member = '';
			foreach($members as $member) {
				$temp_member .= '<a href="'.get_settings('home').'/wp-stats.php?author='.urlencode($member).'">'.$member.'</a>, ';
			}
			if(!function_exists('get_totalposts')) {
				$temp_member = strip_tags($temp_member);
			}
			echo __('Members').': '.substr($temp_member, 0, -2);
		}
	} else {
		// This Should Not Happen
		_e('No User Is Browsing This Page');
	}
}


### Function: Check IP
function check_ip($ip) {
	if(is_user_logged_in() && ($ip != 'unknown')) {
		return "(<a href=\"http://ws.arin.net/cgi-bin/whois.pl?queryinput=$ip\" target=\"_blank\" title=\"".gethostbyaddr($ip)."\">$ip</a>)";
	}
}


### Function: Output User's Country Flag/Name
function ip2nation_country($ip, $display_countryname = 0) {
	if(function_exists('wp_ozh_ip2nation')) {
		$country_code = wp_ozh_getCountryCode(0, $ip);
		$country_name = wp_ozh_getCountryName(0, $ip);
		if($country_name != 'Private') {
			$temp = '<img src="http://frenchfragfactory.net/images/flag_'.$country_code.'.gif" alt="'.$country_name.'" />';
			if($display_countryname) {
				$temp .= $country_name;
			}
			return $temp.' ';
		} else {
			return;
		}
	}
	return;
}


### Function: Display UserOnline For Admin
function display_useronline() {
	global $wpdb;
	// Reassign Bots Name
	$bots = get_settings('useronline_bots');
	$bots_name = array();
	foreach($bots as $botname => $botlookfor) {
		$bots_name[] = $botname;
	}

	// Get The Users Online
	$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline");

	// Variables Variables Variables
	$members = array();
	$guests = array();
	$bots = array();
	$total_users = 0;
	$total_members = 0;
	$total_guests = 0;
	$total_bots = 0;
	$nicetext_users = '';
	$nicetext_members = '';
	$nicetext_guests = '';
	$nicetext_bots = '';

	// Process Those User Who Is Online
	if($usersonline) {
		foreach($usersonline as $useronline) {
			if($useronline->username == 'Guest') {
				$guests[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
				$total_guests++;
			} elseif(in_array($useronline->username, $bots_name)) {
				$bots[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
				$total_bots++;
			} else {
				$members[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
				$total_members++;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);
	}

	//  Nice Text For Users
	if($total_users == 1) {
		$nicetext_users = $total_users.' '.__('User');
	} else {
		$nicetext_users = number_format($total_users).' '.__('Users');
	}

	//  Nice Text For Members
	if($total_members == 1) {
		$nicetext_members = $total_members.' '.__('Member');
	} else {
		$nicetext_members = number_format($total_members).' '.__('Members');
	}


	//  Nice Text For Guests
	if($total_guests == 1) { 
		$nicetext_guests = $total_guests.' '.__('Guest');
	} else {
		$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
	}

	//  Nice Text For Bots
	if($total_bots == 1) {
		$nicetext_bots = $total_bots.' '.__('Bot'); 
	} else {
		$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
	}
?>
	<div class="wrap">
		<h2>UserOnline Stats</h2>
		<p><?php if ($total_users == 1) { _e('There is '); } else { _e('There are a total of '); } ?><b><?php echo $nicetext_users; ?></b> online now: <b><?php echo $nicetext_members; ?></b>, <b><?php echo $nicetext_guests; ?></b> and <b><?php echo $nicetext_bots; ?></b>.</p>
		<p>Most users ever online were <b><?php get_most_useronline(); ?></b>, on <b><?php get_most_useronline_date(); ?></b></p>
	</div>
		<?php
			// Print Out Members
			if($total_members > 0) {
				echo 	'<div class="wrap"><h2>'.$nicetext_members.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($members) {
				$wp_stats = false;
				if(function_exists('get_totalposts')) {
					$wp_stats = true;
				}
				foreach($members as $member) {
					if($wp_stats) {
						echo '<p><b>#'.$no.' - <a href="'.get_settings('home').'/wp-stats.php?author='.$member['username'].'">'.$member['username'].'</a></b> '.ip2nation_country($member['ip']).check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
					} else {
						echo '<p><b>#'.$no.' - '.$member['username'].'</b> '.check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
					}
					$no++;
				}
			}
			// Print Out Guest
			if($total_guests > 0) {
				echo 	'<div class="wrap"><h2>'.$nicetext_guests.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($guests) {
				foreach($guests as $guest) {
					echo '<p><b>#'.$no.' - '.$guest['username'].'</b> '.ip2nation_country($guest['ip']).check_ip($guest['ip']).' on '.gmdate('d.m.Y @ H:i', $guest['timestamp']).'<br />'.$guest['location'].' [<a href="'.$guest['url'].'">url</a>]</p>'."\n";
					$no++;
				}
				echo '</div>';
			}
			// Print Out Bots
			if($total_bots > 0) {
				echo 	'<div class="wrap"><h2>'.$nicetext_bots.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($bots) {
				foreach($bots as $bot) {
					echo '<p><b>#'.$no.' - '.$bot['username'].'</b> '.check_ip($bot['ip']).' on '.gmdate('d.m.Y @ H:i', $bot['timestamp']).'<br />'.$bot['location'].' [<a href="'.$bot['url'].'">url</a>]</p>'."\n";
					$no++;
				}
				echo '</div>';
			}
			if($total_users == 0) {
				echo 	'<div class="wrap"><h2>'.__('No One Is Online Now').'</h2></div>'."\n";
			}
		echo '</div>';
}


### Function: Create UserOnline Table
add_action('activate_useronline/useronline.php', 'create_useronline_table');
function create_useronline_table() {
	global $wpdb;
	$bots = array('Google Bot' => 'googlebot', 'Google Bot' => 'google', 'MSN' => 'msnbot', 'Alex' => 'ia_archiver', 'Lycos' => 'lycos', 'Ask Jeeves' => 'jeeves', 'Altavista' => 'scooter', 'AllTheWeb' => 'fast-webcrawler', 'Inktomi' => 'slurp@inktomi', 'Turnitin.com' => 'turnitinbot', 'Technorati' => 'technorati', 'Yahoo' => 'yahoo', 'Findexa' => 'findexa', 'NextLinks' => 'findlinks', 'Gais' => 'gaisbo', 'WiseNut' => 'zyborg', 'WhoisSource' => 'surveybot', 'Bloglines' => 'bloglines', 'BlogSearch' => 'blogsearch', 'PubSub' => 'pubsub', 'Syndic8' => 'syndic8', 'RadioUserland' => 'userland', 'Gigabot' => 'gigabot', 'Become.com' => 'become.com');
	include_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	// Drop UserOnline Table
	$wpdb->query("DROP TABLE IF EXISTS $wpdb->useronline");
	// Create UserOnline Table
	$create_table = "CREATE TABLE $wpdb->useronline (".
							" timestamp int(15) NOT NULL default '0',".
							" username varchar(50) NOT NULL default '',".
							" useragent varchar(255) NOT NULL default '',".
							" ip varchar(40) NOT NULL default '',".						 
							" location varchar(255) NOT NULL default '',".
							" url varchar(255) NOT NULL default '',".
							" UNIQUE KEY useronline_id (timestamp,username,ip,useragent))";
	maybe_create_table($wpdb->useronline, $create_table);
	// Add In Options
	add_option('useronline_most_users', 1, 'Most Users Ever Online Count');
	add_option('useronline_most_timestamp', current_time('timestamp'), 'Most Users Ever Online Date');
	add_option('useronline_timeout', 300, 'Timeout In Seconds');
	add_option('useronline_bots', $bots, 'Bots Name/Useragent');
}
?>