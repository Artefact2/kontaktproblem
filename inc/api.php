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

const MASK_AccountBalance                =        1;
const MASK_AssetList                     =        2;
const MASK_CalendarEventAttendees        =        4;
const MASK_CharacterSheet                =        8;
const MASK_ContactList                   =       16;
const MASK_ContactNotifications          =       32;
const MASK_FacWarStats                   =       64;
const MASK_IndustryJobs                  =      128;
const MASK_KillLog                       =      256;
const MASK_MailBodies                    =      512;
const MASK_MailingLists                  =     1024;
const MASK_MailMessages                  =     2048;
const MASK_MarketOrders                  =     4096;
const MASK_Medals                        =     8192;
const MASK_Notifications                 =    16384;
const MASK_NotificationTexts             =    32768;
const MASK_Research                      =    65536;
const MASK_SkillInTraining               =   131072;
const MASK_SkillQueue                    =   262144;
const MASK_Standings                     =   524288;
const MASK_UpcomingCalendarEvents        =  1048576;
const MASK_WalletJournal                 =  2097152;
const MASK_WalletTransactions            =  4194304;
const MASK_CharacterInfoPublic           =  8388608;
const MASK_CharacterInfo                 = 16777216;
const MASK_AccountStatus                 = 33554432;
const MASK_Contracts                     = 67108864;

function kp_api_keys() {
  if(isset($_SESSION['api_keys']) && is_array($_SESSION['api_keys'])) {
    return $_SESSION['api_keys'];
  }
  
  kp_init_connections();
  $id = kp_account_id();
  $req = mysql_query('SELECT key_id, v_code FROM api_keys WHERE valid=1 AND account_id='.$id, kp_kpconn());
  
  $_SESSION['api_keys'] = array();
  while(list($key_id, $v_code) = mysql_fetch_row($req)) {
    $_SESSION['api_keys'][$key_id] = $v_code;
  }

  return $_SESSION['api_keys'];
}

function kp_invalidate_api_keys() {
  unset($_SESSION['api_keys']);
  unset($_SESSION['characters']);
}

function kp_characters() {
  if(isset($_SESSION['characters']) && is_array($_SESSION['characters'])) {
    return $_SESSION['characters'];
  }

  $_SESSION['characters'] = array();
  foreach(kp_api_keys() as $key_id => $v_code) {
    $xml = kp_api('/account/APIKeyInfo.xml.aspx', array('keyID' => $key_id, 'vCode' => $v_code));
    if($xml === null) continue;
    $mask = (int)$xml->result->key['accessMask'];

    foreach($xml->result->key->rowset->row as $row) {
      $_SESSION['characters'][(int)$row['characterID']]['name'] = (string)$row['characterName'];
      $_SESSION['characters'][(int)$row['characterID']]['api'][$key_id] = $mask;
    }
  }

  return $_SESSION['characters'];
}

function kp_api($name, $params) {
  if(kp_logged_in() && isset($_SESSION['api_root']) && !empty($_SESSION['api_root'])) {
    $apiRoot = $_SESSION['api_root'];
  } else $apiRoot = kp_get_conf('default_api_root');

  static $cacheDir = null;
  if($cacheDir === null) $cacheDir = ROOT.'/cache';
  
  if(!is_writable($cacheDir)) {
    $user = trim(`id -un`);
    kp_fatal("Cache directory $cacheDir is not writable by user $user.");
  }

  $hash = kp_account_id().'_'.hash('sha256', serialize($name).serialize($params));
  if(file_exists($cacheDir.'/'.$hash)) {
    $xml = new SimpleXMLElement(file_get_contents($cacheDir.'/'.$hash));
    $cachedUntil = strtotime((string)$xml->cachedUntil);
    if($cachedUntil >= time()) {
      return $xml;
    }
  }

  $c = curl_init($apiRoot.$name);
  curl_setopt($c, CURLOPT_POST, true);
  curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
  $xml = curl_exec($c);
  curl_close($c);
  
  if(isset($params['keyID']) && isset($params['vCode']) && 
     ($xml === false || isset($xml->error))) {
    $key_id = intval($params['keyID']);
    mysql_query('UPDATE api_keys SET valid=0 WHERE key_id='.$key_id, kp_kpconn());
    kp_invalidate_api_keys();
  }

  if($xml === false) return null;

  file_put_contents($cacheDir.'/'.$hash, $xml);
  return new SimpleXMLElement($xml);
}

function kp_has_api_access($xmlMask, $keys, &$out_key_id) {
  foreach($keys as $key_id => $mask) {
    if($mask & $xmlMask) {
      $out_key_id = $key_id;
      return true;
    }
  }

  return false;
}

function kp_to_mask($array) {
  return array_map(function($call) {
      return constant('MASK_'.$call);
    }, $array);
}

function kp_check_api_access($required, $optional, $keys) {
  foreach($required as $mask) {
    if(!kp_has_api_access($mask, $keys, $out)) {
      return 0;
    }
  }

  foreach($optional as $mask) {
    if(!kp_has_api_access($mask, $keys, $out)) {
      return 1;
    }
  }
  
  return 2;
}