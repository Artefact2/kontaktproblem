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

function kp_typenames($typeIDs) {
  if(count($typeIDs) == 0) return array();

  $in = implode(', ', array_unique($typeIDs));
  $names = array();

  kp_init_connections();
  $r = mysql_query('SELECT typeID, typeName FROM invTypes WHERE typeID IN ('.$in.')', kp_eveconn());
  while($row = mysql_fetch_row($r)) {
    list($type_id, $type_name) = $row;
    $names[$type_id] = $type_name;
  }

  return $names;
}

function kpf_level($level) {
  if($level == 1) return 'I';
  else if($level == 2) return 'II';
  else if($level == 3) return 'III';
  else if($level == 4) return 'IV';
  else if($level == 5) return 'V';
  else return $level;
}

function kp_get_skill_start($sp, $rank) {
  static $values = array(0, 250, 1415, 8000, 45255, 256000);
  
  $last = false;
  foreach($values as $v) {
    $threshold = $rank * $v;
    if($threshold > $sp) return $last;
    else $last = $threshold;
  }

  return $last;
}

function kp_get_skill_end($sp, $rank) {
  static $values = array(250, 1415, 8000, 45255, 256000);
  
  $last = false;
  foreach($values as $v) {
    $threshold = $rank * $v;
    if($threshold > $sp) return $threshold;
    else $last = $threshold;
  }

  return $last;
}