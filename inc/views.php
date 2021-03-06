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

function kp_views() {
  return array(
	       'CS' => array(
			     'name' => 'Character sheet',
			     'requires' => array('CharacterSheet'),
			     'optional' => array('AccountBalance'),
			     'icon' => '25_64_15.png'
			     ),
	       'SQ' => array(
			     'name' => 'Skill queue',
			     'requires' => array('SkillInTraining', 'SkillQueue'),
			     'optional' => array(),
			     'icon' => '36_64_15.png'
			     ),
	       'WJ' => array(
			     'name' => 'Wallet journal',
			     'requires' => array('WalletJournal'),
			     'optional' => array('AccountBalance'),
			     'icon' => '32_128_3.png'
			     )
	       );
}

function kp_accessible_views() {
  if(isset($_SESSION['accessible_views']) && is_array($_SESSION['accessible_views'])) {
    return $_SESSION['accessible_views'];
  }

  $_SESSION['accessible_views'] = array();

  $views = kp_views();
  $chars = kp_characters();

  foreach($chars as $character_id => $character_data) {
    foreach($views as $view_name => $view_data) {
      $access = kp_check_api_access(kp_to_mask($view_data['requires']), 
				    kp_to_mask($view_data['optional']), 
				    $character_data['api']);
      if($access == 0) continue;

      $_SESSION['accessible_views'][$character_data['name']][$view_name] = array($character_id, $access);
    }
  }

  return $_SESSION['accessible_views'];
}

function kp_can_access_view($character_name, $view) {
  $a_views = kp_accessible_views();
  if(isset($a_views[$character_name][$view])) {
    list(, $access) = $a_views[$character_name][$view];
    return $access >= 1;
  }

  return false;
}

function kp_default_view() {
  if(isset($_SESSION['default_character']) && !empty($_SESSION['default_character'])) {
    $char = $_SESSION['default_character'];
  } else $char = null;

  if(isset($_SESSION['default_view']) && !empty($_SESSION['default_view'])) {
    $view = $_SESSION['default_view'];
  } else $view = null;

  return array($char, $view);
}

function kp_first_available_view($characters, &$out_char, &$out_view) {
  $a_views = kp_accessible_views();
  foreach($a_views as $character_name => $views) {
    foreach($views as $view => $stuff) {
      list(, $access) = $stuff;
      if($access >= 1) {
	$out_char = $character_name;
	$out_view = $view;
	return true;
      }
    }
  }
  
  return false;
}

function kp_show_view($char_id, $character, $view) {
  $cache_dir = __DIR__.'/../cache';
  $cache_path = $cache_dir.'/VIEW_'.$view.'_'.kp_account_id().'_'.$char_id;
  
  $has_cache = kp_get_conf('cache');
  if(file_exists($cache_path) && $has_cache) {
    $t = filemtime($cache_path);
    $now = time();
    if($t >= $now) {
      echo file_get_contents($cache_path);
      return ($t - $now) + 1;
    }
  }

  $r = __DIR__.'/../src/views/'.$view.'.php';
  if(file_exists($r)) {
    require $r;
    ob_start();
    kp_do_view($char_id, $character, $expires);
    $data = ob_get_clean();
    $now = time();
    if($expires > $now) {
      file_put_contents($cache_path, $data);
      touch($cache_path, $expires);
    }
  } else {
    echo "<code>File $r could not be included.</code>";
    return false;
  }

  echo $data;
  return $expires - $now;
}
