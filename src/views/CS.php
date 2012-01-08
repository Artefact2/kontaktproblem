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
 
function kp_do_view($char_id, $char_name, &$out_expire_date) {
  $keys = kp_api_keys();
  $chars = kp_characters();
  $c_keys = $chars[$char_id]['api'];

  assert(kp_has_api_access(MASK_CharacterSheet, $c_keys, $id_cs));
  $has_balance = kp_has_api_access(MASK_AccountBalance, $c_keys, $id_balance);

  $cs = kp_api('/char/CharacterSheet.xml.aspx', array('keyID' => $id_cs, 
						      'vCode' => $keys[$id_cs], 
						      'characterID' => $char_id));

  if($has_balance)
  $balance = kp_api('/char/AccountBalance.xml.aspx', array('keyID' => $id_balance, 
							   'vCode' => $keys[$id_balance], 
							   'characterID' => $char_id));
  
  $root = kp_get_conf('rewrite_root');
  $img_root = kp_get_conf('img_root');

  echo "<h1>".htmlspecialchars($char_name)."</h1>\n<ul>\n";

  echo "<li>Date of birth: <strong>".((string)$cs->result->DoB)."</strong> (".kpf_long_interval(time() - strtotime((string)$cs->result->DoB))." ago)</li>\n";

  $corp_id = (int)$cs->result->corporationID;
  if($corp_id == 0) {
    $corporation = '<em>No corporation</em>';
  } else {
    $corp_name = htmlspecialchars((string)$cs->result->corporationName);
    $corporation = '<img src="'.$img_root.'/Corporation/'.$corp_id.'_128.png" alt="" /> Member of <strong><a href="https://gate.eveonline.com/Corporation/'.$corp_name.'">'.$corp_name.'</a></strong>';
  }
  echo "<li id=\"cs_corp\">$corporation</li>\n";

  $alliance_id = (int)$cs->result->allianceID;
  if($alliance_id == 0) {
    $alliance = '<em>No alliance</em>';
  } else {
    $alliance_name = htmlspecialchars((string)$cs->result->allianceName);
    $alliance = '<img src="'.$img_root.'/Corporation/'.$alliance_id.'_128.png" alt="" /> Member of <strong><a href="https://gate.eveonline.com/Alliance/'.$alliance_name.'">'.$alliance_name.'</a></strong>';
  }
  echo "<li id=\"cs_alliance\">$alliance</li>\n";

  $f_balance = (string)$cs->result->balance;
  if($has_balance) $f_balance = (string)$balance->result->rowset->row['balance'];
  $f_balance = '<strong>'.kpf_isk($f_balance).' ISK</strong>';
  echo "<li>Balance: $f_balance</li>\n";

  echo "</ul>\n";

  echo "<h2>Attributes</h2>\n";

  $attribs = array(
		   'intelligence' => array('Intelligence', 'icon22_03.png', 10222),
		   'perception' => array('Perception', 'icon22_05.png', 10217),
		   'charisma' => array('Charisma', 'icon22_01.png', 10226),
		   'willpower' => array('Willpower', 'icon22_02.png', 10213),
		   'memory' => array('Memory', 'icon22_04.png', 10209),
		   );

  echo "<table id=\"attribs\">\n<thead>\n<tr><th>Attribute</th><th>Base points</th><th>Implants</th><th>Remappable</th><th>Total</th></tr></thead>\n<tfoot></tfoot>\n<tbody>\n";

  foreach($attribs as $attrib => $data) {
    list($name, $icon, $type_id) = $data;
    $implant = @(int)$cs->result->attributeEnhancers->{$attrib.'Bonus'}->augmentatorValue;
    $total = (int)$cs->result->attributes->$attrib;

    $remappable = $total - 17;
    $pb = '<div class="spb remap">'.str_repeat('<strong>&nbsp;</strong>', $remappable).str_repeat('<span>&nbsp;</span>', 10 - $remappable).'</div>';

    echo "<tr><td><img src=\"$root/img/$icon\" alt=\"\" /> $name</td><td>17</td><td><img src=\"$img_root/Type/".$type_id."_64.png\" alt=\"\" /> <strong>+$implant</strong></td><td>$pb</td><td><strong>".($total + $implant)."</strong></td></tr>\n";
  }

  echo "</tbody>\n</table>\n";

  $type_ids = array();
  $skillpoints = 0;

  foreach($cs->result->rowset[0]->row as $row) {
    $type_ids[(int)$row['typeID']] = array((int)$row['skillpoints'], (int)$row['level']);
    $skillpoints += (int)$row['skillpoints'];
  }

  if((int)$cs->result->cloneSkillPoints < $skillpoints) {
    echo "<h2 class=\"clone_warning\">Warning! Your clone is outdated (".((string)$cs->result->cloneName).").</h2>\n";
    echo "<script type=\"text/javascript\">$('h2.clone_warning').each(function() {
  var elem = $(this);
  setInterval(function() {
    if(elem.css('visibility') == 'hidden') {
      elem.css('visibility', 'visible');
    } else {
      elem.css('visibility', 'hidden');
    }
  }, 1000);
});</script>\n";
  }

  echo "<h2>Skills (".kpf_sp($skillpoints)." SP in total)</h2>\n";

  $previous_group = null;
  kp_init_connections();
  $req = mysql_query('SELECT invTypes.typeID, invTypes.typeName, invGroups.groupName, dgmTypeAttributes.valueFloat
                      FROM invTypes 
                      LEFT JOIN invGroups ON invTypes.groupID = invGroups.groupID
                      LEFT JOIN dgmTypeAttributes ON (invTypes.typeID = dgmTypeAttributes.typeID AND dgmTypeAttributes.attributeID = 275)
                      WHERE invTypes.typeID IN ('.implode(',', array_keys($type_ids)).')
                      ORDER BY invGroups.groupName ASC, invTypes.typeName ASC', kp_eveconn());

  while($row = mysql_fetch_row($req)) {
    list($type_id, $type_name, $group_name, $rank) = $row;
    if($group_name !== $previous_group) {
      if($previous_group !== null) echo "</ul>\n";
      $previous_group = $group_name;
      echo "<h3>".htmlspecialchars($group_name)."</h3>\n<ul class=\"skills\">\n";
    }

    list($sp, $level) = $type_ids[$type_id];
    $img = $level == 5 ? '50_64_14.png' : '50_64_13.png';

    $sp_total = kpf_sp(kp_get_skill_end($sp, $rank));
    $sp = kpf_sp($sp);
    $pb = '<div class="spb skill">'.str_repeat('<strong>&nbsp;</strong>', $level).str_repeat('<span>&nbsp;</span>', 5 - $level).'</div>';

    echo "<li><em>Level $level $pb</em><img src=\"$root/img/$img\" alt=\"\" /> <strong>".htmlspecialchars($type_name)."</strong> (".$rank."x)<br /> Skill Points: <strong>$sp / $sp_total</strong></li>\n";
  }
  
  $out_expire_date = strtotime($cs->cachedUntil);
  if($has_balance && ($t = strtotime($balance->cachedUntil)) < $out_expire_date) {
      $out_expire_date = $t;
  }
}