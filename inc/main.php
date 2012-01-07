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
require __DIR__.'/db.php';

function kp_header($title = '') {
  $root = kp_get_conf('rewrite_root');
  if($title == '') $title = 'Kontaktproblem';
  else $title = htmlspecialchars($title).' / Kontaktproblem';

  echo "<!DOCTYPE html>\n";
  echo "<html>\n<head>\n";
  echo "<title>$title</title>\n";
  echo "<link href=\"$root/kp.css\" rel=\"stylesheet\" type=\"text/css\" /> \n";
  echo "<script src=\"//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js\" type=\"text/javascript\"></script>\n";
  echo "<script type=\"text/javascript\" src=\"$root/js/jquery.flot.min.js\"></script>";
  echo "<script type=\"text/javascript\" src=\"$root/js/jquery.flot.stack.min.js\"></script>";
  echo "<script type=\"text/javascript\" src=\"$root/js/jquery.formatCurrency.min.js\"></script>";
  echo "</head>\n<body>\n"; 
  echo "<div id=\"wrapper\">\n";
}

function kp_footer($text = '') {
  echo "<div id=\"push\"></div>\n</div>\n<div id=\"footer\">\n<p><span class=\"footer_text\">".$text."</span>Kontaktproblem ".VERSION." (Artefact2/Jerera) / All images are &copy; CCP / <a href=\"https://github.com/Artefact2/kontaktproblem\">Browse source</a> (<a href=\"http://www.gnu.org/licenses/agpl.html\">AGPLv3</a>)</p>\n</div>\n";
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

function kp_printr() {
  echo "<pre>";
  foreach(func_get_args() as $arg) {
    print_r($arg);
    echo "\n";
  }
  echo "</pre>";
}

function kpf_isk($amount) {
  static $t_sep = null;
  static $d_mark = null;
  if($t_sep === null) {
    $t_sep = kp_get_conf('default_thousands_sep');
    $d_mark = kp_get_conf('default_decimal_mark');
  }

  return number_format(floatval($amount), 2, $d_mark, $t_sep);
}

function kpf_interval($timestamp, $precision = -1, $abbrev = false) {
  if($abbrev) {
    $day = $days = 'd';
    $hour = $hours = 'h';
    $minute = $minutes = 'm';
    $second = $seconds = 's';
    $separator = $separator_last = ' ';
  } else {
    $day = ' day'; $days = ' days';
    $hour = ' hour'; $hours = ' hours';
    $minute = ' minute'; $minutes = ' minutes';
    $second = ' second'; $seconds = ' seconds';
    $separator = ', ';
    $separator_last = ' and ';
  }

  $s = $timestamp % 60;
  $timestamp = ($timestamp - $s) / 60;
  
  $m = $timestamp % 60;
  $timestamp = ($timestamp - $m) / 60;
  
  $h = $timestamp % 24;
  $timestamp = ($timestamp - $h) / 24;
  
  $d = $timestamp;
  
  $fmt = array();
  if($d == 1) {
    $fmt[] = $d.$day;
  } else if($d > 1) {
    $fmt[] = $d.$days;
  }
  if($h == 1) {
    $fmt[] = $h.$hour;
  } else if($h > 1) {
    $fmt[] = $h.$hours;
  }
  if($m == 1) {
    $fmt[] = $m.$minute;
  } else if($m > 1) {
    $fmt[] = $m.$minutes;
  }
  if($s == 1) {
    $fmt[] = $s.$second;
  } else if($s > 1) {
    $fmt[] = $s.$seconds;
  }
  
  $fmt = array_slice($fmt, 0, ($precision < 0) ? 4 : $precision);
  
  $c = count($fmt);
  if($c == 0) return '0'.$second;
  else if($c == 1) return $fmt[0];
  else {
    $last = array_pop($fmt);
    $llast = array_pop($fmt);
    $fmt[] = $llast.$separator_last.$last;
    return implode($separator, $fmt);
  }
}

function kpf_sp($sp) {
  static $t_sep = null;
  if($t_sep === null) {
    $t_sep = kp_get_conf('default_thousands_sep');
  }

  return number_format(floatval($sp), 0, '', $t_sep);
}

session_start();
kp_read_conf();