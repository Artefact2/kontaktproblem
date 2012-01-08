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

const QUEUE_MAXLENGTH = 86400;
 
function kp_do_view($char_id, $char_name, &$out_expire_date) {
  $keys = kp_api_keys();
  $chars = kp_characters();
  $c_keys = $chars[$char_id]['api'];

  assert(kp_has_api_access(MASK_SkillInTraining, $c_keys, $id_sit));
  assert(kp_has_api_access(MASK_SkillQueue, $c_keys, $id_sq));

  $sit = kp_api('/char/SkillInTraining.xml.aspx', array('keyID' => $id_sit, 
							'vCode' => $keys[$id_sit], 
							'characterID' => $char_id));

  $sq = kp_api('/char/SkillQueue.xml.aspx', array('keyID' => $id_sit, 
						  'vCode' => $keys[$id_sit], 
						  'characterID' => $char_id));

  $root = kp_get_conf('rewrite_root');

  $now = time();
  if((int)$sit->result->skillInTraining) {
    $in_training_id = (int)$sit->result->trainingTypeID;
    $names = kp_typenames(array($in_training_id));
    $in_training = $names[$in_training_id];
    $level = kpf_level((int)$sit->result->trainingToLevel);

    $rate = (int)$sit->result->trainingDestinationSP - (int)$sit->result->trainingStartSP;
    $rate /= ($duration = strtotime((string)$sit->result->trainingEndTime) - ($start = strtotime((string)$sit->result->trainingStartTime)));
    $sp_per_hour = round($rate * 3600, 2);

    $left = strtotime((string)$sit->result->trainingEndTime) - $now;
    $left = ($left > 0) ? kpf_interval($left) : 'completion imminent';
    $sp_goal = kpf_sp((int)$sit->result->trainingDestinationSP);
    $sp_current = kpf_sp(min((int)$sit->result->trainingStartSP + ($now - $start) * $rate, (int)$sit->result->trainingDestinationSP));
    $progress = min(100, 100 * (($now - $start) / $duration));

    echo "<h1>Currently training: <img src=\"$root/img/50_64_12.png\" alt=\"\" /> <strong id=\"training\">$in_training $level</strong></h1>\n<ul>\n";
    echo "<li>Training rate: <strong>$sp_per_hour</strong> SP/hr</li>\n";
    echo "<li>Time left: <strong id=\"training_time_left\">$left</strong></li>\n";
    echo "<li>Completion: <strong><span id=\"current_sp\">$sp_current<span> / $sp_goal</strong> SP</li>\n";
    echo "<li>Progress: <strong id=\"percentage_done\">".round($progress, 2)."%</strong> done <br />\n<div class=\"progress_bar skill\"><div style=\"width: ".$progress."%;\">&nbsp;</div></div></li>";

    echo "</ul>\n";
  } else {
    echo "<h1>No skill in training</h1>\n";
  }

  $empty = @count($sq->result->rowset->row) == 0;
  $skill_ids = array();
  if(!$empty) {
    foreach($sq->result->rowset->row as $row) {
      $end = (string)$row['endTime'];
      $skill_ids[] = (int)$row['typeID'];

      if(!$row) {
	/* Training queue paused 
	 * NB: we're not breaking here, because
	 * the $skill_ids array is filled here as well.
	 */
	continue;
      } else {
	$max = strtotime($end);
      }
    }
  }

  if($empty || $max <= $now) {
    echo "<h1>Skill queue</h1>\n<p>Skill queue is empty.</p>\n";
  } else {
    $names = kp_typenames($skill_ids);
    $remaining = kpf_interval($max - $now, -1, true);
    echo "<h1>Skill queue (ends in $remaining)</h1>\n<ul id=\"skill_queue\">\n";

    $total = 0;
    $oddity = 0;
    foreach($sq->result->rowset->row as $row) {
      if((string)$row['endTime']) {
	$start = strtotime((string)$row['startTime']);
	$end = strtotime((string)$row['endTime']);

	if($end < $now) continue; /* Slightly outdated queue */

	if((int)$row['queuePosition'] == 0) {
	  $image = '50_64_12.png';
	} else $image = '50_64_13.png';

	$duration = '<em>'.kpf_interval($raw_duration = ($end - max($start, $now)), -1, true).'</em>';

	$fraction = 100 * ($raw_duration / QUEUE_MAXLENGTH);
	$r_total = round($total, 2);
	$r_fraction = round($fraction, 2);
	$bar = '<br /><div class="progress_bar queue"><div class="blank" style="width: '.$r_total.'%;">&nbsp;</div><div class="queue'.$oddity.'" style="width: '.min($r_fraction, 100 - $r_total).'%;">&nbsp;</div></div>';

	$total += $fraction;
	$oddity = ($oddity + 1) % 2;
      } else {
	$duration = $bar = '';
	$image = '50_64_13.png';
      }

      echo "<li>$duration<img src=\"$root/img/$image\" alt=\"\" /> <strong>".$names[(int)$row['typeID']]." ".kpf_level((int)$row['level'])."</strong>$bar</li>\n";
    }

    echo "</ul>\n";
  }

  $out_expire_date = min(strtotime($sq->cachedUntil), strtotime($sit->cachedUntil));
}