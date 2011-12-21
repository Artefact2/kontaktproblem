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

require '../inc/main.php';
require '../lib/openid.php';

if(isset($_SESSION['account_id']) && $_SESSION['account_id'] > 0) {
  header('Location: ./kp/');
  die();
}

function kp_login($identity) {
  kp_init_connections();
  $conn = kp_kpconn();
  $fIdentity = mysql_real_escape_string($identity, $conn);

  list($hasAccount) = mysql_fetch_row(mysql_query('SELECT COUNT(*) FROM accounts WHERE openid_identifier="'.$fIdentity.'"', $conn));
  if($hasAccount == 0) {
    mysql_query('INSERT INTO accounts (openid_identifier) VALUES("'.$fIdentity.'")', $conn);
  }

  list($accountID) = mysql_fetch_row(mysql_query('SELECT id FROM accounts WHERE openid_identifier="'.$fIdentity.'"', $conn));
  $_SESSION['token'] = uniqid('kp_', true);
  $_SESSION['account_id'] = $accountID;
  header('Location: ./kp/');
  die();
}

$message = '';
try {
  $oid = new LightOpenID(kp_get_conf('openid_root'));
  if(!$oid->mode) {
    if(isset($_POST['openid_login']) && !empty($_POST['openid_identifier'])) {
      $oid->identity = $_POST['openid_identifier'];
      header('Location: '.$oid->authUrl());
      die();
    } else if(isset($_POST['google_login'])) {
      $oid->identity = 'https://www.google.com/accounts/o8/id';
      header('Location: '.$oid->authUrl());
      die();      
    }
  } else if($oid->mode == "cancel") {
    $message = '<p class="error">Authentication cancelled by user.</p>';
  } else {
    if($oid->validate()) {
      setcookie('kp_last_identifier', $oid->identity, time() + 3600 * 24 * 14);
      kp_login($oid->identity);
      header('Location: ./kp/');
      die();
    } else {
      $message = '<p class="error">Authentication was not successful.</p>';
    }
  }
} catch(ErrorException $e) {
  $message = '<p class="error">Authentication failed (<code>'.htmlspecialchars($e->getMessage()).'</code>).</p>';
}

kp_header('Welcome');
if(isset($_COOKIE['kp_last_identifier'])) {
  $value = 'value="'.htmlspecialchars($_COOKIE['kp_last_identifier']).'"';
} else {
  $value = 'placeholder="OpenID Identifier"';
}

echo "<div style=\"display: table; height: 100%; width: 100%;\">\n";
echo "<div style=\"display: table-cell; vertical-align: middle;\">\n";
echo "<form method=\"post\" action=\"\" style=\"width: 25em; display: block; margin: auto;\">\n";

echo "<p style=\"text-align: center;\"><strong>Welcome to Kontaktproblem.</strong><br />
Please authenticate yourself to continue:</p>$message
<ul>
  <li>
    <input class=\"openid\" type=\"text\" name=\"openid_identifier\" $value style=\"width: 14em;\" /> <input type=\"submit\" name=\"openid_login\" value=\"Login with OpenID\" />
  </li>
  <li>
    or <input type=\"submit\" name=\"google_login\" value=\"Login with your Google Account\" />
  </li>
</ul>
<p class=\"login_help\"><em>What if I don't have an OpenID? You can get an OpenID identifier at <a href=\"https://www.myopenid.com/\">myopenid.com</a>.</em></p>
<p class=\"login_help\"><em>Where do I create an account? You don't have to. An account will automatically be created when you log in for the first time.</em></p>";

echo "</form>\n</div>\n</div>\n";

kp_footer();