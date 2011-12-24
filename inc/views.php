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
			     'optional' => array('AccountBalance')
			     ),
	       'SQ' => array(
			     'name' => 'Skill queue',
			     'requires' => array('SkillInTraining', 'SkillQueue'),
			     'optional' => array()
			     )
	       );
}

function kp_can_access_view($character_name, $view) {
  $views = kp_views();
  if(!isset($views[$view])) return false;

  $chars = kp_characters();
  $c_id = null;
  foreach($chars as $char_id => $char_data) {
    if($char_data['name'] = $character_name) {
      $c_id = $char_id;
      break;
    }
  }
  if($c_id === null) return false;

  return kp_can_access_view_raw($chars, $c_id, $views, $view);
}

function kp_can_access_view_raw($chars, $character_id, $views, $view_name) {
  return kp_check_api_access(kp_to_mask($views[$view_name]['requires']), 
			     kp_to_mask($views[$view_name]['optional']), 
			     $chars[$character_id]['api']) 
    > 0;
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
  $views = kp_views();
  foreach($characters as $character_id => $character_data) {
    foreach($views as $view_name => $view_data) {
      if(kp_can_access_view_raw($characters, $character_id, $views, $view_name)) {
	$out_char = $character_data['name'];
	$out_view = $view_name;
	return true;
      }
    }
  }
  
  return false;
}