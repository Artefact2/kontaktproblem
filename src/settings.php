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

if(!kp_logged_in()) {
  header('Location: ./');
  die();
}

function kp_fmt_feature($k) {
  if($k == 0) {
    return array('no access', 'No');
  } else if($k == 1) {
    return array('partial access', 'Yes (Partial)');
  } else {
    assert('$k == 2');
    return array('full access', 'Yes (Full)');
  }
}

kp_init_connections();

if(isset($_POST['refresh_cache'])) {
  kp_invalidate_api_keys();
}

if(isset($_POST['add_new_key']) | isset($_POST['retry_key'])) {
  if(isset($_POST['add_new_key'])) {
    $key_id = intval($_POST['new_key_id']);
    $vcode = $_POST['new_vcode'];
  } else {
    list($key_id) = array_keys($_POST['retry_key']);
    $key_id = intval($key_id);
    list($vcode) = mysql_fetch_row(mysql_query('SELECT v_code FROM api_keys WHERE key_id='.$key_id, kp_kpconn()));
  }
  
  $xml = kp_api('/account/APIKeyInfo.xml.aspx', array('keyID' => $key_id, 'vCode' => $vcode));
  if($xml === null) {
    $message = "<p class=\"error\">Did not receive a valid XML file from the API server.</p>";
  } else if(isset($xml->error)) {
    $message = "<p class=\"error\">Got an error from the API server: <br /><code>".((string)$xml->error)."</code></p>";
  } else if((string)$xml->result->key['type'] != "Character" && (string)$xml->result->key['type'] != 'Account') {
    $message = "<p class=\"error\">Sorry, only Character and Account keys are supported (got <code>".((string)$xml->result->key['type'])."</code>).</p>";
  } else {
    $conn = kp_kpconn();
    list($alreadyHasKey) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM api_keys WHERE account_id='.kp_account_id().' AND key_id='.$key_id, $conn));
    $vcode = mysql_real_escape_string($vcode, $conn);
    if($alreadyHasKey) {
      mysql_query('UPDATE api_keys SET valid=1 AND v_code="'.$vcode.'" WHERE account_id='.kp_account_id().' AND key_id='.$key_id, $conn);
      $message = "<p class=\"success\">API key successfully updated.</p>";
    } else {
      mysql_query('INSERT INTO api_keys (account_id, key_id, v_code, valid) VALUES('.kp_account_id().', '.$key_id.', "'.$vcode.'", 1)', $conn);
      $message = "<p class=\"success\">Successfully added new API key.</p>";
    }
    
    kp_invalidate_api_keys();
  }
}

if(isset($_POST['delete_key'])) {
  list($key_id) = array_keys($_POST['delete_key']);
  mysql_query('DELETE FROM api_keys WHERE key_id='.intval($key_id).' AND account_id='.kp_account_id(), kp_kpconn());
  $message = '<p class="success">API key successfully deleted.</p>';
}

if(isset($_POST['api_root'])) {
  $default = kp_get_conf('default_api_root');
  $_SESSION['api_root'] = $api = $_POST['api_root'];
  if($api == $default) $_SESSION['api_root'] = $fApi = '';
  else $fApi = mysql_real_escape_string($api, kp_kpconn());

  mysql_query('UPDATE accounts SET api_root="'.$fApi.'" WHERE id='.kp_account_id(), kp_kpconn());
  $message = '<p class="success">API root updated.</p>';
}

if(isset($_POST['make_default'])) {
  list($view_c) = array_keys($_POST['make_default']);
  $view_c = intval($view_c);
  if(isset($_POST['char'][$view_c]) && isset($_POST['view'][$view_c])) {
    $char = mysql_real_escape_string($_SESSION['default_character'] = $_POST['char'][$view_c], kp_kpconn());
    $view = mysql_real_escape_string($_SESSION['default_view'] = $_POST['view'][$view_c], kp_kpconn());

    mysql_query('UPDATE accounts SET default_character="'.$char.'", default_view="'.$view.'" WHERE id='.kp_account_id(), kp_kpconn());
  }
}

$chars = kp_characters();
$views = kp_views();
$a_views = kp_accessible_views();

kp_header('Account Settings');
echo "<div style=\"width: 100%; height: 100%; display: table;\">\n";
echo "<div style=\"height: 100%; display: table-cell; vertical-align: middle; text-align: center;\">\n";
echo "<div style=\"width: 35em; margin: auto;\">\n";

$mask = 0;
foreach($views as $view_name => $view_data) {
  foreach(kp_to_mask($view_data['requires']) as $r) {
    $mask |= $r;
  }
  foreach(kp_to_mask($view_data['optional']) as $r) {
    $mask |= $r;
  }
}

echo "<h2>API Keys</h2>";
if(isset($message)) echo $message;
echo "<p class=\"api_help\">You can create a new API key here: <a href=\"https://support.eveonline.com/api/Key/CreatePredefined/$mask\"><code>https://support.eveonline.com/api/Key/CreatePredefined/$mask</code></a>.<br />You can uncheck any method you want.</p>";

$apiRoot = isset($_SESSION['api_root']) ? $_SESSION['api_root'] : '';
if(empty($apiRoot)) $apiRoot = kp_get_conf('default_api_root');
echo "<form method=\"post\" action=\"\">\n<p><input type=\"text\" name=\"api_root\" value=\"$apiRoot\" /> <input type=\"submit\" value=\"Change API root\" /><br /><em class=\"api_help\">Change this if you want to use an API proxy.<br />If you don't know what this is, just don't touch it.</em></p>\n</form>\n<hr />\n";

echo "<form method=\"post\" action=\"\">
<table class=\"apikeys\">
<thead>
<tr>
<th>Key ID</th>
<th>Verification Code (vCode)</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tfoot />
<tbody>
";

$keys = mysql_query('SELECT key_id, v_code, valid FROM api_keys WHERE account_id='.kp_account_id().' ORDER BY key_id ASC', kp_kpconn());
while($row = mysql_fetch_row($keys)) {
  list($key_id, $v_code, $valid) = $row;
  $v_code = htmlspecialchars(substr($v_code, 0, 20).'...'.substr($v_code, -10));
  echo "<tr>\n<td><code>$key_id</code></td>\n";
  echo "<td><code>$v_code</code></td>\n";
  $action = '';
  if($valid) {
    echo "<td class=\"key_valid\">OK</td>\n";
    $action = '<br /><input type="submit" name="retry_key['.$key_id.']" value="Refetch" />';
  } else {
    echo "<td class=\"key_invalid\">Invalid</td>\n";
    $action = '<br /><input type="submit" name="retry_key['.$key_id.']" value="Retry" />';
  }
  echo "<td><input type=\"submit\" name=\"delete_key[".$key_id."]\" value=\"Delete\" />$action</td>\n</tr>\n";
}

echo "<tr>
<td><input type=\"text\" name=\"new_key_id\" size=\"7\" /></td>
<td><input type=\"text\" name=\"new_vcode\" size=\"40\" /></td>
<td>N/A</td>
<td><input type=\"submit\" name=\"add_new_key\" value=\"Add new key\" /></td>
</tr>
</tbody>
</table>
</form>";

if(count($chars) > 0) {
  list($default_char, $default_view) = kp_default_view();

  echo "<hr />\n";
  echo "<h2>Feature table (<form class=\"inline\" method=\"post\" action=\"\"><input type=\"submit\" name=\"refresh_cache\" value=\"Refresh\" /></form>)</h2>\n";
  echo "<form method=\"post\" action=\"\">\n<table class=\"feature_table\">\n<thead>\n<tr>\n<th></th>\n";
  
  foreach($views as $view_name => $view_data) {
    echo "<th>\n<strong>";
    echo htmlspecialchars($view_data['name']);
    echo "</strong><br />\n<code>";

    $prereqs = array();
    foreach($view_data['requires'] as $req) {
      $prereqs[] = "$req (Required)";
    }
    foreach($view_data['optional'] as $opt) {
      $prereqs[] = "$opt (Optional)";
    }
    echo implode('<br />', $prereqs);
    echo "</code>\n</th>\n";
  }

  echo "</tr>\n</thead>\n<tfoot />\n<tbody>\n";

  $img_root = kp_get_conf('img_root');
  $view_c = 1;
  foreach($chars as $char_id => $char_data) {
    $name = htmlspecialchars($char_data['name']);
    
    echo "<tr>\n";
    echo "<th><img src=\"${img_root}/Character/${char_id}_64.jpg\" alt=\"$name\" title=\"$name\" /></th>\n";

    foreach($views as $view_name => $view_data) {
      if(isset($a_views[$char_data['name']][$view_name])) {
	list(, $access) = $a_views[$char_data['name']][$view_name];
      } else $access = 0;
      
      list($class, $label) = kp_fmt_feature($access);
      if($char_data['name'] == $default_char && $view_name == $default_view) {
	$def = '<strong>Default view</strong>';
      } else if($access >= 1) {
	$def = '<input type="submit" name="make_default['.$view_c.']" value="Make default" /><input type="hidden" name="char['.$view_c.']" value="'.$name.'" /><input type="hidden" name="view['.$view_c.']" value="'.htmlspecialchars($view_name).'" />';
      } else {
	$def = '';
      }

      echo "<td class=\"$class\">$label<br />$def</td>\n";
      ++$view_c;
    }

    echo "</tr>\n";
  }
  
  echo "</table>\n</form>\n";
}

echo "<hr />\n<p class=\"api_help\"><em>Once you are done, you can <strong><a href=\"./\">return to the main page</a></strong>.</em></p>";

echo "</div>\n</div>\n</div>\n";
kp_footer();