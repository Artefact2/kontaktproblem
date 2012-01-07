<?php
/*
 * Kontaktproblem
 * Copyright (C) 2011, 2012 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require __DIR__.'/../inc/main.php';

function kp_true_uri() {
  $root = kp_get_conf('rewrite_root');
  $uri = $_SERVER['REQUEST_URI'];

  if(strpos($uri, $root) !== 0) {
    kp_fatal("Your rewrite_root is incorrect. You must fix your configuration file.\nCurrent rewrite_root: $root\nRequested URI: $uri");
  }

  return substr($uri, strlen($root));
}

function kp_intro_message() {
  $root = kp_get_conf('rewrite_root');
  kp_header('');
  echo "<div style=\"position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: table;\">\n";
  echo "<div style=\"display: table-cell; vertical-align: middle;\">\n";
  echo "<p class=\"warning\" style=\"width: 25em; margin: auto;\">";
  echo "You have no monitored characters. Why not adding some API keys to your account?<br />Check the <a href=\"$root/Settings\"><strong>Account Settings</strong></a> page to do so.";
  echo "</p>\n</div>\n</div>\n";
  kp_footer();
  die();
}

if(!kp_logged_in()) {
  $root = kp_get_conf('rewrite_root');
  header('Location: '.$root.'/Login?from='.$_SERVER['REQUEST_URI']);
}

$uri = kp_true_uri();
$characters = kp_characters();

if(count($characters) == 0 && $uri == "/kp/") {
  kp_intro_message();
}

if($uri == "/kp/") {
  list($character, $view) = kp_default_view();

  if($character === null || $view === null || !kp_can_access_view($character, $view)) {
    if(kp_first_available_view($characters, $character, $view)) {
      /* $character and $view were passed by reference here. */
    } else {
      /* We have characters but no views! */
      kp_intro_message();
    }
  }

  header('Location: '.kp_get_conf('rewrite_root').'/kp/'.$character.'/'.$view);
  die();
}

list($empty, $kp, $char, $view) = explode('/', $uri, 4);
assert('$empty == ""');
assert('$kp == "kp"');

$char = urldecode($char);
$view = urldecode($view);

$views = kp_views();
$a_views = kp_accessible_views();
$imgroot = kp_get_conf('img_root');

if(!kp_can_access_view($char, $view)) {
  if(!isset($a_views[$char])) {
    header('Location: ../');
  }

  foreach($a_views[$char] as $a_view_name => $v_data) {
    header('Location: ./'.$a_view_name);
    die();
  }
}

foreach($a_views[$char] as $d) {
  list($char_id, ) = $d;
  break;
}

$imgroot = kp_get_conf('img_root');
$kproot = kp_get_conf('rewrite_root');

kp_header($char.' / '.$views[$view]['name']);

echo "<div id=\"side\">\n";

$name = htmlspecialchars($char);
echo "<h1 class=\"curchar\" style=\"background: black url('$imgroot/Character/".$char_id."_256.jpg') center center no-repeat;\">\n<span>$name</span>\n</h1>\n";

if(count($a_views) > 1) {
  echo "<ul>\n";
  foreach($a_views as $character_name => $c_a_views) {
    if($character_name == $char) continue;
    foreach($c_a_views as $d) {
      list($a_char_id, ) = $d;
      break;
    }
    $character_name_esc = htmlspecialchars($character_name);
    echo "<li><a href=\"../".urlencode($character_name)."/".$view."\"><img src=\"$imgroot/Character/".$a_char_id."_64.jpg\" alt=\"".$character_name_esc."\" title=\"".$character_name_esc."\" />".$character_name_esc."</a></li>\n";
  }
  echo "</ul>\n";
}

echo "<ul>\n";
foreach($a_views[$char] as $a_view_name => $a_view) {
  $v = $views[$a_view_name];
  if($view == $a_view_name) $class = ' class="current"';
  else $class = '';

  $a_view_name = htmlspecialchars($a_view_name);
  $v_name = htmlspecialchars($v['name']);

  if(isset($views[$a_view_name]['icon'])) {
    $icon = '<img src="'.$kproot.'/img/'.$views[$a_view_name]['icon'].'" alt="'.$v_name.'" title="'.$v_name.'" />';
  } else $icon = '';

  echo '<li><a'.$class.' href="./'.$a_view_name.'">'.$icon.$v_name.'</a></li>'."\n";
}
echo "</ul>\n<ul>\n";

echo '<li><a href="../../Settings"><img src="'.$kproot.'/img/33_128_3.png" alt="Account settings" title="Account settings" />Account settings</a></li>'."\n";
echo '<li><a href="'.kp_logout_link().'"><img src="'.$kproot.'/img/9_64_6.png" alt="Logout" title="Logout" />Logout</a></li>'."\n";
echo "</ul>\n</div>\n<div id=\"content\">\n";

$cache_duration = kp_show_view($char_id, $char, $view);

echo "</div>\n";

kp_footer('Cache expires in '.kpf_interval($cache_duration, 1).' - ');