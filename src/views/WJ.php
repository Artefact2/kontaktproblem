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

/* The rowCount parameter is disregarded by CCP so we have to use the
 * default value */
const WALK_ENTRIES = 50;
const TABLE_ROWCOUNT = 100;
 
function kp_do_view($char_id, $char_name, &$out_expire_date) {
  $out_expire_date = 0;

  $keys = kp_api_keys();
  $chars = kp_characters();
  $c_keys = $chars[$char_id]['api'];

  assert(kp_has_api_access(MASK_WalletJournal, $c_keys, $key_id));
  $params = array(
		  'keyID' => $key_id,
		  'vCode' => $keys[$key_id],
		  'characterID' => $char_id,
		  'rowCount' => WALK_ENTRIES
		  );

  $entries = array();
  do {
    $xml = kp_api('/char/WalletJournal.xml.aspx', $params);
    $out_expire_date = max($out_expire_date, strtotime((string)$xml->cachedUntil));

    $num_entries = 0;
    foreach($xml->result->rowset->row as $row) {
      $num_entries++;
      $ref_id = (int)$row['refID'];

      if(!isset($params['fromID']) || $ref_id < $params['fromID']) {
	$params['fromID'] = $ref_id;
      }

      $entries[$ref_id] = $row;
    }
  } while($num_entries == WALK_ENTRIES);

  krsort($entries);
  reset($entries);

  if(kp_has_api_access(MASK_AccountBalance, $c_keys, $balance_api)) {
    $bxml = kp_api('/char/AccountBalance.xml.aspx', array(
							  'keyID' => $balance_api,
							  'vCode' => $keys[$balance_api],
							  'characterID' => $char_id
							  ));
    $balance = (string)$bxml->result->rowset->row['balance'];
    $out_expire_date = min($out_expire_date, strtotime((string)$bxml->cachedUntil));
  } else {
    list(, $first) = each($entries);
    $balance = (string)$first['balance'];
  }

  $f_balance = kpf_isk($balance);
  
  $ref_types = array();
  $xml_ref_types = kp_api('/eve/RefTypes.xml.aspx', array());
  foreach($xml_ref_types->result->rowset->row as $row) {
    $ref_types[(int)$row['refTypeID']] = (string)$row['refTypeName'];
  }

  echo "<h1>Current balance : <span id=\"balance\"><noscript>".$f_balance."</noscript></span> ISK</h1>\n";
  echo "<script type=\"text/javascript\">\n";
  echo "$('span#balance').text('0.00');";
  echo "function animate_balance(balance, i) { $('span#balance').text(balance * i / 60).formatCurrency({symbol: '', decimalSymbol: '".kp_get_conf('default_decimal_mark')."', digitGroupSymbol: '".kp_get_conf('default_thousands_sep')."'}); if(i < 60) setTimeout(animate_balance, 1000 / 60, balance, i+1) };\n";
  echo "animate_balance(".$balance.", 0);\n";
  echo "</script>\n";

  $data = kp_generate_graph_data($entries);
  krsort($data);
  echo "<noscript><h2>Wealth graph (non-JavaScript version)</h2>\n<table class=\"wealth_graph\"><thead><tr><th>&#9660; Day</th><th>Minimum</th><th>Average</th><th>Maximum</th></tr></thead>\n<tfoot></tfoot>\n<tbody>\n";
  foreach($data as $day => $day_values) {
    echo '<tr><td>'.date('Y-m-d', $day).'</td><td>'.kpf_isk($day_values['min']).'</td><td>'.kpf_isk($day_values['avg']).'</td><td>'.kpf_isk($day_values['max']).'</td></tr>'."\n";
  }
  echo "</tbody>\n</table>\n</noscript>\n<div id=\"js_w_graph\"></div>\n";
  echo "<script type=\"text/javascript\">\n";

  echo "$('div#js_w_graph').append('<h2>Wealth graph (min/avg/max)</h2><div id=\"w_graph_ph\"></div>');";
  echo "$.plot($('div#w_graph_ph'), ".kp_serialize_datapoints(array_slice($data, 0, 30, true)).", { 
    series: { stack: true, 
              bars: {
                      show: true, 
                      barWidth: 16 * 3600000, 
                      align: \"center\", 
                      fillColor: { colors: [ { opacity: 1.0 }, { opacity: 1.0 } ] } 
                    } 
            }, 
    colors: ['rgba(134, 194, 248, 0.8)', 'rgba(134, 194, 248, 0.5)', 'rgba(134, 194, 248, 0.25)'],
    xaxis: {
             mode: \"time\",
             timeformat: \"%y-%0m-%0d\",
             minTickSize: [3, \"day\"]
    },
    yaxis: {
             tickFormatter: function(val, axis) {
                 if(val >= 1000000000) return (val / 1000000000).toFixed(1) + \"B\";
                 else if(val >= 1000000) return (val / 1000000).toFixed(1) + \"M\";
                 else if(val >= 1000) return (val / 1000).toFixed(0) + \"K\";
                 else return val;
             }
    }
  });";

  echo" </script>\n";
  echo "<h2>Wallet journal (last ".TABLE_ROWCOUNT." entries)</h2>\n";

  echo "<table id=\"wj\">\n<thead>\n<tr><th>&#9660; Date</th><th>Type</th><th>Amount</th><th>Balance</th><th>From / To</th></tr>\n</thead><tfoot></tfoot>\n<tbody>\n";
  $i = 0;
  foreach($entries as $row) {
    if(++$i > TABLE_ROWCOUNT) break;
    $date = substr((string)$row['date'], 0, -3); /* Hide the seconds, it's always :00 anyway */
    $type = $ref_types[(int)$row['refTypeID']];
    $amount = kpf_isk((string)$row['amount']);
    $balance = kpf_isk((string)$row['balance']);
    if(substr($amount, 0, 1) == '-') {
      $amount = '<span class="balance_neg">'.$amount;
    } else $amount = '<span class="balance_pos">'.$amount;
    $from = (string)$row['ownerName1'];
    $to = (string)$row['ownerName2'];
    if($from == $char_name) $fromto = $to;
    else if($to == $char_name) $fromto = $from;
    else $fromto = $from.$to;
    if($fromto == '') $fromto = '<span class="na">N/A</span>';
    else $fromto = htmlspecialchars($fromto);

    echo "<tr><td>$date</td><td>$type</td><td>$amount ISK</span></td><td>$balance ISK</td><td>$fromto</td></tr>\n";
  }
  echo "</tbody>\n</table>\n";
}

function kp_generate_graph_data($entries) {
  $data = array();
  $datapoints = array();

  foreach($entries as $row) {
    $raw_date = (string)$row['date'];
    $date = explode(' ', $raw_date);
    $date = strtotime(array_shift($date));
    $balance = floatval((string)$row['balance']);

    if(!isset($data[$date]['min']) || $data[$date]['min'] > $balance) {
      $data[$date]['min'] = $balance;
    }
    if(!isset($data[$date]['max']) || $data[$date]['max'] < $balance) {
      $data[$date]['max'] = $balance;
    }

    $datapoints[] = array(strtotime($raw_date), $date, $balance);
  }

  $c = count($datapoints);
  $datapoints = array_reverse($datapoints);
  for($i = 1; $i < $c; ++$i) {
    list($p_exact_date, $p_day, $balance) = $datapoints[$i - 1];
    list($exact_date, $day, $l_balance) = $datapoints[$i];
   
    if($p_day == $day) {
      $length = $exact_date - $p_exact_date;

      /* FIXME do proper initialization */
      @$data[$day]['avg_total'] += $length;
      @$data[$day]['avg_sum'] += $length * $balance;
    } else {
      $next = kp_next_day($p_day);
      $length1 = $next - $p_exact_date;
      $length2 = $exact_date - $day;

      @$data[$p_day]['avg_total'] += $length1;
      @$data[$day]['avg_total'] += $length2;
      @$data[$p_day]['avg_sum'] += $length1 * $balance;
      @$data[$day]['avg_sum'] += $length2 * $balance;

      while($next != $day) {
	$data[$next] = array(
			     'min' => $balance,
			     'max' => $balance,
			     'avg_total' => 1,
			     'avg_sum' => $balance
			     );
	$next = kp_next_day($next);
      }
    }
  }

  $today = kp_today();
  while($day != $today) {
    $day = kp_next_day($day);
    $data[$day] = array(
			'min' => $l_balance,
			'max' => $l_balance,
			'avg_total' => 1,
			'avg_sum' => $l_balance
			);
  }
  

  foreach($data as &$day) {
    if($day['avg_total'] == 0) {
      $day['avg'] = 0;
    } else {
      $day['avg'] = $day['avg_sum'] / $day['avg_total'];
    }

    unset($day['avg_sum']);
    unset($day['avg_total']);
  }
  
  return $data;
}

function kp_next_day($day) {
  return strtotime(date('Y-m-d', $day + 36 * 3600));
}

function kp_today() {
  return strtotime(date('Y-m-d', time()));
}

function kp_serialize_datapoints($data) {
  $min = array();
  $max = array();
  $avg = array();

  foreach($data as $day => $day_data) {
    $m = $day_data['min'];
    $M = $day_data['max'];
    $a = $day_data['avg'];

    $min[] = '['.$day.'000, '.$m.']';
    $avg[] = '['.$day.'000, '.($a - $m).']';
    $max[] = '['.$day.'000, '.($M - $a).']';
  }

  return '[['.implode(',', $min).'], ['.implode(',', $avg).'], ['.implode(',', $max).']]';
}