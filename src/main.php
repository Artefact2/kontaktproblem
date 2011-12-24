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
  echo "<div style=\"width: 100%; height: 100%; display: table;\">\n";
  echo "<div style=\"height: 100%; display: table-cell; vertical-align: middle;\">\n";
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
