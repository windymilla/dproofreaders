<?PHP
$relPath="./../pinc/";
include_once($relPath.'v_site.inc');
include_once($relPath.'username.inc');
include_once($relPath.'connect.inc');
$db_Connection=new dbConnect();
include_once($relPath.'user.inc');
include_once($relPath.'metarefresh.inc');
include_once($relPath.'theme.inc');

function abort_login( $error )
{
    global $reset_password_url;
    global $site_manager_email_addr;

    theme(_("Login Failed"), "header");

    echo "<br>\n";
    echo "<b>$error</b>\n";
?>
<p>Please attempt again to log in above. If problems continue to persists, review the following possible fixes:
<ol>
<li>Type your username in the exact same way as when you registered.</li>
<li><A HREF=\"$reset_password_url\">Reset</A> your password.</li>
<li>Enable Javascript.</li>
<li>Accept cookies (at least from us at www.pgdp.net)</li>
<li>Allow popup windows (at least from us at www.pgdp.net)</li>
<li>caching set to off (or: refresh page every visit)</li>
<li>Ensure your PC clock is set to the correct date & time</li>
</ol>
<p>If all of this fails, contact the <a href=\"mailto:$site_manager_email_addr\">site manager</a>.
</body></html>
<?
    exit();
}

extract($_POST);

$err = check_username($userNM);
if ($err != '')
{
    abort_login($err);
}

if ($userPW == '')
{
    abort_login(_("You did not supply a password."));
}

// $userNM = str_replace("\'", "''", $userNM);
$userC=new db_udb();

$uC=$userC->checkLogin($userNM,$userPW);
if (!$uC)
{
    abort_login(_("Username or password is incorrect."));
}

$uP=$userC->getUserPrefs($userNM);
if (!$uP)
{
    abort_login(_("Username or password is incorrect."));
}

// The login is successful!

// Log into phpBB2
if (is_dir($forums_dir)) {
	$result = mysql_query("SELECT user_id FROM phpbb_users WHERE username = '$userNM'");
	$user_id = mysql_result($result, 0, "user_id");
	define('IN_PHPBB', true);
	$phpbb_root_path = $forums_dir."/";
	include($phpbb_root_path.'extension.inc');
	include($phpbb_root_path.'common.php');
	include($phpbb_root_path.'config.php');
	session_begin($user_id, $user_ip, PAGE_INDEX, false, 1);
}

// send them to the correct page
if (!empty($destination))
{
    // They were heading to $destination (via a bookmark, say)
    // when we sidetracked them into the login pages.
    // Make sure they get to where they were going.
    $url = $destination;
}
else
{
    // isn't this the same as the manager field in users?
    //        $result = mysql_query("SELECT value FROM usersettings WHERE username = '$username' AND setting = 'manager'");
    // needs to be included in user.inc, if not....

    if ($userC->manager=='yes')
    {
        $url = "../tools/project_manager/projectmgr.php";
    }
    else
    {
        $url = "../tools/proofers/proof_per.php";
    }
}
metarefresh(1,$url,_("Sign In"),"");

?>
