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

define('VERSION', '0.1');
define('ROOT', dirname(realpath(__DIR__)));

require __DIR__.'/api.php';
require __DIR__.'/views.php';

function kp_header($title = '') {
  $root = kp_get_conf('rewrite_root');
  if($title == '') $title = 'Kontaktproblem';
  else $title = htmlspecialchars($title).' / Kontaktproblem';

  echo "<!DOCTYPE html>\n";
  echo "<html>\n<head>\n";
  echo "<title>$title</title>\n";
  echo "<link href=\"$root/kp.css\" rel=\"stylesheet\" type=\"text/css\" /> \n";
  echo "</head>\n<body>\n"; 
}

function kp_footer($text = '') {
  echo "<p class=\"footer\"><span class=\"footer_text\">".$text."</span>Kontaktproblem ".VERSION." (Artefact2/Jerera) / All images are &copy; CCP / <a href=\"https://github.com/Artefact2/kontaktproblem\">Browse source</a> (<a href=\"http://www.gnu.org/licenses/agpl.html\">AGPLv3</a>)</p>";
  echo "</body>\n</html>";
}

function kp_fatal($message) {
  header('HTTP/1.1 500 Internal Server Error');
  header('Content-Type: text/plain');
  echo $message;
  die();
}

function kp_logout_link() {
  $root = kp_get_conf('rewrite_root');
  return $root.'/Logout?token='.kp_session_token();
}

function kp_read_conf() {
  global $__kp_conf;
  $inifile = ROOT.'/config.ini';
  if(!file_exists($inifile)) {
    kp_fatal("Configuration file $inifile not found.");
  }
  $__kp_conf = parse_ini_file($inifile);
  if($__kp_conf === false) {
    kp_fatal("Unable to parse the configuration file.");
  }
}

function kp_get_conf($key) {
  global $__kp_conf;
  if(isset($__kp_conf[$key])) return $__kp_conf[$key];
  else return false;
}

function kp_init_connections() {
  global $__kp_eveconn;
  global $__kp_kpconn;
  static $done = false;
  
  if($done) return;

  $__kp_eveconn = mysql_connect(kp_get_conf('dump_host'), kp_get_conf('dump_user'), kp_get_conf('dump_password'), true);
  if($__kp_eveconn === false) {
    kp_fatal("Could not connect to the dump database:\n".mysql_error());
  }

  $__kp_kpconn = mysql_connect(kp_get_conf('kp_host'), kp_get_conf('kp_user'), kp_get_conf('kp_password'), true);
  if($__kp_kpconn === false) {
    kp_fatal("Could not connect to the kontaktproblem database:\n".mysql_error());
  }

  if(mysql_select_db(kp_get_conf('dump_database'), $__kp_eveconn) === false) {
    kp_fatal("Could not select the dump database:\n".mysql_error());
  }
  
  if(mysql_select_db(kp_get_conf('kp_database'), $__kp_kpconn) === false) {
    kp_fatal("Could not select the kp database:\n".mysql_error());
  }
  
  $done = true;
}

function kp_eveconn() {
  global $__kp_eveconn;
  return $__kp_eveconn;
}

function kp_kpconn() {
  global $__kp_kpconn;
  return $__kp_kpconn;
}

function kp_logged_in() {
  return isset($_SESSION['account_id']) && $_SESSION['account_id'] > 0;
}

function kp_account_id() {
  return isset($_SESSION['account_id']) ? $_SESSION['account_id'] : 0;
}

function kp_session_token() {
  if(isset($_SESSION['token'])) return $_SESSION['token'];
  else {
    $token = uniqid('kp_', true);
    return $_SESSION['token'] = $token;
  }
}

session_start();
kp_read_conf();