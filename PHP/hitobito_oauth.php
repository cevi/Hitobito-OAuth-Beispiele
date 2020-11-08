<?php

// hitobito oauth urls
const hitobito_oauth_authorization_endpoint = '';
const hitobito_oauth_token_endpoint = '';
const hitobito_oauth_profile_endpoint = '';

// oauth client id and secret
const hitobito_oauth_uid = '';
const hitobito_oauth_secret = '';

// profile scope, has to match with settings in hitobito
// one or more out of [email | name | with_roles]
const hitobito_oauth_scope = 'email name with_roles';

// own redirect url, has to match with settings in hitobito
const redirect_uri = '';

// start session to store oauth stuff
session_start();

// start the login process by sending the user to authorization page
if(isset($_GET['action']) && ($_GET['action'] == 'login')) {
    // remove potential stuff form previous login
    session_unset();
    // generate a random hash and store in the session for security
    $_SESSION['state'] = hash('sha256', microtime(TRUE).rand().$_SERVER['REMOTE_ADDR']);
    $params = array(
        'response_type' => 'code',
        'client_id' => hitobito_oauth_uid,
        'redirect_uri' => redirect_uri,
        'scope' => hitobito_oauth_scope,
        'state' => $_SESSION['state']
    );
    // redirect the user to hitobito authorization page
    header('Location: ' . hitobito_oauth_authorization_endpoint . '?' . http_build_query($params));
    die();
}

// log out
if(isset($_GET['action']) && ($_GET['action'] == 'logout')) {
    session_unset();
    header('Location: ' . $_SERVER['PHP_SELF']);
    die();
}

// when hitobito redirects the user back here, there will be a "code" and "state" parameter in the query string
if(isset($_GET['code'])) {
    // verify the state matches our stored state
    if(!isset($_GET['state']) || $_SESSION['state'] != $_GET['state']) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        die();
    }
    // exchange the auth code for a token
    $token = apiRequest(hitobito_oauth_token_endpoint, array(
        'grant_type' => 'authorization_code',
        'client_id' => hitobito_oauth_uid,
        'client_secret' => hitobito_oauth_secret,
        'redirect_uri' => redirect_uri,
        'state' => $_SESSION['state'],
        'code' => $_GET['code']
    ));
    $_SESSION['access_token'] = $token->access_token;
    $_SESSION['token'] = $token; // store full token response for demo purpose
    $_SESSION['code'] = $_GET['code']; // store code for demo purpose
    header('Location: ' . $_SERVER['PHP_SELF']);
}

if(isset($_SESSION['access_token'])) {
    echo '<h3>Logged In</h3>';
    echo '<p><a href="?action=logout">Log Out</a></p>';
    echo '<pre>';
    echo '<p>Code:</p>';
    print_r($_SESSION['code']);
    echo '<p>Token:</p>';
    print_r($_SESSION['token']);
    echo '<p>Profile (Scope email):</p>';
    print_r(apiRequest(hitobito_oauth_profile_endpoint, FALSE, array('X-Scope: email')));
    echo '<p>Profile (Scope name):</p>';
    print_r(apiRequest(hitobito_oauth_profile_endpoint, FALSE, array('X-Scope: name')));
    echo '<p>Profile (Scope with_roles):</p>';
    print_r(apiRequest(hitobito_oauth_profile_endpoint, FALSE, array('X-Scope: with_roles')));
    echo '</pre>';
} else {
    echo '<h3>Not logged in</h3>';
    echo '<p><a href="?action=login">Log In</a></p>';
}

function apiRequest($url, $post=FALSE, $headers=array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if($post)
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $headers[] = 'Accept: application/json';
    if(isset($_SESSION['access_token']))
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    return json_decode($response);
}
