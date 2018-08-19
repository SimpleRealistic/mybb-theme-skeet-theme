<?php
/* Copyright (c) 2012 by Christian Fillion.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>. */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br /><a href=\"../../index.php\">Go back.</a>");
}

function invite_info()
{
	return array(
		"name"			=> "Invitation Plugin",
		"description"	=> "Invite user to your board.",
		"website"		=> "http://cfillion.no-ip.org/?invitation=v1.3.2",
		"author"		=> "cfillion",
		"authorsite"	=> "http://cfillion.no-ip.org/?invitation=v1.3.2",
		"version"		=> "1.3.2",
		"guid" 			=> "2418077c65561fe2bd0ac601bdb0c889",
		"compatibility" => "18*"
	);
}

function invite_install()
{
	global $db;

	invite_check_usereferrals();

	$db->query("CREATE TABLE `".TABLE_PREFIX."invitecodes` (
				`id` int(10) NOT NULL auto_increment PRIMARY KEY ,
				`code` varchar(10) NOT NULL,
				`used` int(10) NOT NULL DEFAULT '0',
				`usedby` varchar(100),
				`maxuses` int(10) NOT NULL DEFAULT '1',
				`email` varchar(300),
				`expire` bigint(30) NOT NULL DEFAULT '0',
				`primarygroup` int(10) NOT NULL,
				`othergroups` varchar(10),
				`createdby` int(10) NOT NULL DEFAULT '0')");

	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD invitecode varchar(12) DEFAULT NULL");
}

function invite_is_installed()
{
	global $db;

	if($db->table_exists('invitecodes') || $db->field_exists('invitecode', 'users'))
	{
		return true;
	}
	return false;
}

function invite_uninstall()
{
	global $db;

	$db->query("DROP TABLE ".TABLE_PREFIX."invitecodes");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP invitecode");
}

function invite_activate()
{
	global $db, $mybb;

	invite_check_usereferrals();

	$r = $db->query('SELECT gid, title FROM '.TABLE_PREFIX.'usergroups');
	while($data = $db->fetch_array($r))
	{
		$groupes .= $data['gid'].'=' . $data['gid'].'. '.$data['title']."\n";
	}
	$groupes = $db->escape_string($groupes);

	$invitation = array(
		"name" => "invitation",
		"title" => "Invitation System",
		"description" => "Manage invitation plugin (by cfillion)",
		"disporder" => "3",
		"isdefault" => "0",
	);
	$group['gid'] = $db->insert_query('settinggroups', $invitation);
	$gid = $db->insert_id();

	$new_setting1 = array(
		'name'			=> 'invitation_status',
		'title'			=> 'Enable plugin',
		'description'	=> 'Do you want to show the "Invitation" box on the register page ?',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '1',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting1);

	$new_setting2 = array(
		'name'			=> 'invitation_adminPermissions1',
		'title'			=> 'Who can administer invitations 1',
		'description'	=> 'Group authorized to administer invitations',
		'optionscode'	=> "select
nobody=[nobody]
".$groupes,
		'value'			=> '4',
		'disporder'		=> '2',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting2);

	$new_setting3 = array(
		'name'			=> 'invitation_adminPermissions2',
		'title'			=> 'Who can administer invitations 2',
		'description'	=> 'Second group authorized to administer invitations',
		'optionscode'	=> "select
						    nobody=[nobody]
						   ".$groupes,
		'value'			=> '',
		'disporder'		=> '3',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting3);

	$new_setting4 = array(
		'name'			=> 'invitation_adminPermissions3',
		'title'			=> 'Who can administer invitations 3',
		'description'	=> 'Third group authorized to administer invitations',
		'optionscode'	=> "select
						    nobody=[nobody]
						   ".$groupes,
		'value'			=> '',
		'disporder'		=> '4',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting4);

	$new_setting5 = array(
		'name'			=> 'invitation_userPermissions',
		'title'			=> 'Who can use Invitation Management',
		'description'	=> 'List of group IDs separated by commas. You can set the maximum number of invitations by adding "=X".<br />
Exemple: "2=5,6,18=2,20=10"',
		'optionscode'	=> 'text',
		'value'			=> '2=5',
		'disporder'		=> '5',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting5);

	$new_setting6 = array(
		'name'			=> 'invitation_allowedgroups',
		'title'			=> 'Usergroups that normal users can invite',
		'description'	=> 'List of group IDs separated by commas. Of course, normal users should not be allowed to invite new administrators.<br />
You can also set wich group can invite wich group by using this syntax: "2=X" (group X will be available only to members of group 2)',
		'optionscode'	=> 'text',
		'value'			=> '2',
		'disporder'		=> '6',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting6);

	$new_setting7 = array(
		'name'			=> 'invitation_required',
		'title'			=> 'Is the invitation code is required',
		'description'	=> '',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '7',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting7);

	$new_setting8 = array(
		'name'			=> 'invitation_showReferredCount',
		'title'			=> 'Show invited user count on invitation management page',
		'description'	=> '',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '8',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting8);

	$new_setting9 = array(
		'name'			=> 'invitation_showReferredCountProfile',
		'title'			=> 'Show referred/invited user count on user profile',
		'description'	=> 'This setting will take effect only if the "Use Referrals System" setting is disabled.',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '9',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting9);

	$new_setting10 = array(
		'name'			=> 'invitation_showCreatedBy',
		'title'			=> 'Show "created by" notice in Invitation Manager',
		'description'	=> '',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '10',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting10);

	$new_setting11 = array(
		'name'			=> 'invitation_headerText',
		'title'			=> 'Invitation manager : header text',
		'description'	=> 'HTML and {$phpVars} allowed here',
		'optionscode'	=> 'textarea',
		'value'			=> '',
		'disporder'		=> '11',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting11);

	$new_setting12 = array(
		'name'			=> 'invitation_footerText',
		'title'			=> 'Invitation manager : footer text',
		'description'	=> 'HTML and {$phpVars} allowed here',
		'optionscode'	=> 'textarea',
		'value'			=> '{$lang->invite_refer_link} {$mybb->settings[\\\'bburl\\\']}/member.php?action=register&amp;code=YOURCODE',
		'disporder'		=> '12',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting12);

	$new_setting13 = array(
		'name'			=> 'invitation_maxattempts',
		'title'			=> 'Max attempts for registration',
		'description'	=> 'How many a user can submit a wrong code without being locked. Let empty or 0 to disable.',
		'optionscode'	=> 'text',
		'value'			=> $mybb->settings['maxloginattempts'],
		'disporder'		=> '13',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting13);

	$new_setting14 = array(
		'name'			=> 'invitation_multideleteOnlyAdmins',
		'title'			=> 'Only administrators can use the Mass Delete tool',
		'description'	=> '',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '14',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting14);

	$new_setting15 = array(
		'name'			=> 'invitation_displayReferredUsers',
		'title'			=> 'Display list of referred users in profile',
		'description'	=> '',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '15',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting15);

	$new_setting16 = array(
		'name'			=> 'invitation_maxReferredUsers',
		'title'			=> 'How many referred users to display',
		'description'	=> 'Apply only if previous setting is enabled. Empty or 0 for infinite.',
		'optionscode'	=> 'text',
		'value'			=> '5',
		'disporder'		=> '16',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting16);

	$new_setting17 = array(
		'name'			=> 'invitation_displayInvitedBy',
		'title'			=> 'Display who invited this member in profile',
		'description'	=> '',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> '17',
		'gid'			=> intval($gid)
	);
	$db->insert_query('settings', $new_setting17);

	rebuild_settings();

	require MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('member_profile_customfields', '#'.preg_quote('{$customfields}').'#', "{\$customfields}\n<!--Invitation-->");
	find_replace_templatesets('member_register', '#'.preg_quote('{$referrer}').'#', "{\$referrer}\n<!--Invitation-->");
	find_replace_templatesets('usercp_nav_misc', '#'.preg_quote('<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e">').'#', "<tbody style=\"{\$collapsed['usercpmisc_e']}\" id=\"usercpmisc_e\">\n\t<!--Invitation-->");
	find_replace_templatesets('member_profile', '#'.preg_quote('{$referrals}').'#', "{\$referrals}\n{\$referrer}");
	find_replace_templatesets('member_profile_referrals', '#'.preg_quote('{$memprofile[\'referrals\']}').'#', '{$memprofile[\'referrals\']} {$referredList}');
}

function invite_deactivate()
{
	global $db, $mybb;

	$db->delete_query("settinggroups", "name IN('invitation')");
	$db->delete_query("settings", "name LIKE '%invitation_%'");
	rebuild_settings();

	require MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('member_profile_customfields', '#\n<!--Invitation-->#', '', 0);
	find_replace_templatesets('member_register', '#\n<!--Invitation-->#', '', 0);
	find_replace_templatesets('usercp_nav_misc', '#\n\t<!--Invitation-->#', '', 0);
	find_replace_templatesets('member_profile', '#\n'.preg_quote('{$referrer}').'#', '', 0);
	find_replace_templatesets('member_profile_referrals', '#\s'.preg_quote('{$referredList}').'#', '', 0);
}

function invite_check_usereferrals()
{
	global $mybb;

	if($mybb->settings['usereferrals'] != 0)
	{
		$infos = invite_info();
		// We display a warning if the "Use Referrals System" setting is enabled
		flash_message($infos['name'].' requires that the "Use Referrals System" setting is disabled. This feature will be overridden after activation/installation.
<a href="index.php?module=config&amp;action=change&amp;search=usereferrals">Click here to configure setting.</a>', 'error');
		admin_redirect('index.php?module=config-plugins');
	}
}

$plugins->add_hook("pre_output_page", "show_invite");
function show_invite($page)
{
	global $mybb, $lang, $db;
	$lang->load('invite');

	if(!isset($_SESSION))
		session_start();

	if($mybb->settings['invitation_status'] == 1)
	{
		if(my_strpos($_SERVER['REQUEST_URI'], 'member.php') AND !isset($_POST['bday1']) AND (isset($_POST['agree']) OR isset($_POST['regsubmit'])))
		{
			if(isset($_SESSION['invitation_code']))
				$code = htmlspecialchars_uni($_SESSION['invitation_code']);
			else if(isset($_POST['invitationcode']))
				$code = htmlspecialchars_uni($_POST['invitationcode']);
			else
				$code = '';

			$desc = (!$mybb->settings['invitation_required']) ? $lang->reg_invitation_desc : $lang->reg_invitation_desc_required;
			$page = str_replace("<!--Invitation-->", "<br /><fieldset class=\"trow2\">
<legend><strong>{$lang->reg_invitation}</strong></legend>
<table cellspacing=\"0\" cellpadding=\"4\" style=\"width: 100%;\">
<tr>
<td><span class=\"smalltext\"><label for=\"invitation\">{$desc}</label></span></td>
</tr>
<tr>
<td>
<input type=\"text\" class=\"textbox\" name=\"invitationcode\" id=\"invitation\" value=\"{$code}\" style=\"width: 100%;\" />
</td>
</tr></table>
</fieldset>", $page);
			$_SESSION['invitation_code'] = null;
		}
		else if(THIS_SCRIPT == 'member.php' && $mybb->input['action'] == 'profile')
		{
			if(!invite_canManage() && !invite_isAdmin())
				return;

			$uid = intval($mybb->input['uid']);
			if($uid <= 0)
				return;

			$query = $db->simple_select('users', 'invitecode, usergroup', 'uid=\''.$uid.'\'');
			$user = $db->fetch_array($query);
			if($user['invitecode'] == null)
				return;

			$ic = $db->escape_string($user['invitecode']);
			$query = $db->simple_select('invitecodes', 'COUNT(*) as `count`, createdby', 'code=\''.$ic.'\'');
			$crep = $db->fetch_array($query);

			if($crep['createdby'] != $mybb->user['uid'] && !invite_isAdmin())
				return;

			$code = htmlspecialchars_uni($user['invitecode']);
			if($crep['count'] > 0)
				$code = "<a href=\"{$mybb->settings['bburl']}/misc.php?action=manageinvites/home&search_invite={$code}\">{$code}</a>";

			if($user['usergroup'] == 5) // If member is not activated yet
				$code .= " {$lang->profile_notactivated}";

			$page = str_replace("<!--Invitation-->", "<tr>
<td class=\"trow1\" width=\"40%\"><strong>{$lang->profile_invitation}:</strong></td>
<td class=\"trow1\" width=\"60%\">{$code}</td>
</tr>", $page);
		}
	}

	if(THIS_SCRIPT == 'usercp.php')
	{
		if((invite_canManage() || invite_isAdmin()) && !$mybb->usergroup['canmodcp'])
		{
			$page = str_replace("<!--Invitation-->", '<tr><td class="trow1 smalltext"><a href="misc.php?action=manageinvites" class="usercp_nav_item" style="background-attachment: scroll; background-clip: border-box; background-color: transparent; background-image: url(\''.$mybb->settings['bburl'].'/images/page_white_key.png\'); background-origin: padding-box; background-position: 0% 50%; background-repeat: no-repeat; background-size: auto;">'.$lang->manage_invitations.'</a></td></tr>', $page);
		}
	}

	$page = str_replace('<!--Invitation-->', '', $page);
	return $page;
}

$plugins->add_hook("datahandler_user_validate", "regcheck_invite");
function regcheck_invite($reg)
{
	global $db, $mybb, $lang;
	if(!my_strpos($_SERVER['REQUEST_URI'], 'member.php'))
		return $reg;

	$lang->load('invite');

	session_start();
	$_SESSION['invitation_code'] = null;
	if($mybb->settings['invitation_status'] == 1)
	{
		if((isset($_POST['invitationcode']) && !empty($_POST['invitationcode'])) || $mybb->settings['invitation_required'])
		{
			if((!isset($_POST['invitationcode']) || empty($_POST['invitationcode'])))
			{
				$reg->set_error($lang->reg_invitation_required);
				return;
			}

			$invitecode = $db->escape_string($_POST['invitationcode']);
			$query = $db->query('SELECT id, used, maxuses, expire, email FROM '.TABLE_PREFIX.'invitecodes WHERE code=\''.$invitecode.'\'');
			$data = $db->fetch_array($query);
			$emails = explode(',', $data['email']);
			if(intval($data['id']) > 0 && ($data['maxuses'] == 0 || $data['used'] < $data['maxuses']) && ($data['expire'] == 0 || $data['expire'] > TIME_NOW) && (empty($data['email']) || in_array($mybb->input['email'], $emails)))
			{
				$_SESSION['validated_code'] = $_POST['invitationcode'];
			}
			else
			{
				$msg2 = (!$mybb->settings['invitation_required']) ? $lang->reg_invalid_code2 : '';
				$reg->set_error($lang->reg_invalid_code.$msg2);
			}
		}
	}

	return $reg;
}

$plugins->add_hook("member_register_agreement", "invite_register_start");
$plugins->add_hook("member_register_coppa", "invite_register_start");
function invite_register_start()
{
	global $mybb;
	if($mybb->settings['invitation_status'] != 1 || empty($_GET['code']))
		return;
	
	if(!isset($_SESSION))
		session_start();

	$_SESSION['invitation_code'] = $_GET['code'];
}

$plugins->add_hook("member_do_register_end", "invite_registered");
function invite_registered()
{
	global $mybb, $db;

	if($mybb->settings['invitation_status'] != 1)
		return;

	$username = $db->escape_string($mybb->input['username']);

	session_start();
	$code = $db->escape_string($_SESSION['validated_code']);
	$_SESSION['validated_code'] = null;
	$selectq = $db->simple_select('invitecodes', 'createdby', 'code=\''.$code.'\'');
	$codedata = $db->fetch_array($selectq);

	// Save used code
	if(!$mybb->settings['usereferrals'])
		$db->update_query('users', array('invitecode' => $code, 'referrer' => $codedata['createdby']), 'username=\''.$username.'\'');
	else
		$db->update_query('users', array('invitecode' => $code), 'username=\''.$username.'\'');

	if($codedata['createdby'] > 0 && !$mybb->settings['usereferrals'])
	{
		$userq = $db->simple_select('users', 'referrals', 'uid=\''.$codedata['createdby'].'\'');
		$userdata = $db->fetch_array($userq);
		$db->update_query('users', array('referrals' => intval($userdata['referrals'])+1), 'uid='.$codedata['createdby'].'');
	}

	if($mybb->settings['regtype'] != "verify" && $mybb->settings['regtype'] != 'admin')
	{
		invite_activateuser();
	}
}

$plugins->add_hook("member_activate_accountactivated", "invite_activateuser");
$plugins->add_hook("admin_user_users_coppa_activate_commit", "invite_activateuser");
function invite_activateuser()
{
	global $mybb, $db;

	if($mybb->settings['invitation_status'] != 1)
		return;

	$where = isset($mybb->input['username']) ? 'username=\''.$db->escape_string($mybb->input['username']).'\'' : 'uid=\''.$db->escape_string($mybb->input['uid']).'\'';
	$query = $db->simple_select('users', 'invitecode, uid, email', $where);
	$user = $db->fetch_array($query);
	if($user['uid'] < 1 || empty($user['invitecode']))
		return;

	$invitecode = $db->escape_string($user['invitecode']);
	$query = $db->query('SELECT * FROM '.TABLE_PREFIX.'invitecodes WHERE code=\''.$invitecode.'\'');
	$data = $db->fetch_array($query);

	$emails = explode(',', $data['email']);
	if(intval($data['id']) > 0 && ($data['maxuses'] == 0 || $data['used'] < $data['maxuses']) && ($data['expire'] == 0 || $data['expire'] > TIME_NOW) && (empty($data['email']) || in_array($user['email'], $emails)))
	{
		// We change user's groups here
		$group = intval($data['primarygroup']);
		$othergroups = $db->escape_string($data['othergroups']);
		$db->update_query('users', array('usergroup' => $group, 'additionalgroups' => $othergroups), 'uid=\''.intval($user['uid']).'\'');

		$usedby = $data['usedby'];
		if(!in_array($user['uid'], explode(',', $usedby)))
		{
			if(!empty($usedby))
				$usedby .= ',';

			$usedby .= intval($user['uid']);
		}
		$usedby = $db->escape_string($usedby);

		$db->update_query('invitecodes', array('used' => $data['used']+1, 'usedby' => $usedby), 'id=\''.intval($data['id']).'\'');
	}
	else
	{
		// Next line seem to cause more bugs than benefits:
		//$db->update_query('users', array('invitecode' => null), 'uid=\''.intval($user['uid']).'\'');
	}
}

$plugins->add_hook('admin_config_menu', 'invite_admin_config_menu');
function invite_admin_config_menu($sub_menu)
{
	global $mybb, $lang;
	$lang->load('../invite');

	$sub_menu[] = array('id' => 'invite', 'title' => $lang->manage_invitations, 'link' => $mybb->settings['bburl'].'/misc.php?action=manageinvites');
	return $sub_menu;
}

$plugins->add_hook('modcp_start', 'invite_edit_modcpmenu');
function invite_edit_modcpmenu()
{
	global $modcp_nav, $mybb, $lang;
	$lang->load('invite');

	if(!invite_isAdmin())
		return;

	$newEntry = '<tr>
		<td class="tcat">
			<div class="float_right"><img src="images/collapse.gif" id="invitation_img" class="expander" alt="[-]" title="[-]" /></div>
			<div><span class="smalltext"><strong>'.$lang->invitations.'</strong></span></div>
		</td>
	</tr><tbody style="" id="invitation_e">
		<tr><td class="trow1 smalltext"><a href="misc.php?action=manageinvites" class="modcp_nav_item" style="background-attachment: scroll; background-clip: border-box; background-color: transparent; background-image: url(\''.$mybb->settings['bburl'].'/images/page_white_key.png\'); background-origin: padding-box; background-position: 0% 50%; background-repeat: no-repeat; background-size: auto;">'.$lang->manage_invitations.'</a></td></tr></tbody>';
	$modcp_nav = preg_replace('#</table>#', $newEntry.'</table>', $modcp_nav);
}

$plugins->add_hook('misc_start', 'invite_page');
function invite_page()
{
	global $mybb, $header, $headerinclude, $footer, $db, $lang, $theme, $templates;

	if(!preg_match('#^manageinvites#', $mybb->input['action']))
		return;

	$mybb->settings['redirects'] = "1"; // Enable friendly redirects as they are used to display confirmation/error messages

	if(!invite_canManage() && !invite_isAdmin())
	{
		error_no_permission();
	}

	$lang->load('invite');

	add_breadcrumb($lang->manage_invitations, "misc.php?action=manageinvites");
	if($mybb->input['action'] == 'manageinvites/add')
		add_breadcrumb($lang->add_invitation, "misc.php?action=manageinvites/add");
	else if($mybb->input['action'] == 'manageinvites/edit')
		add_breadcrumb($lang->edit_invitation, "misc.php?action=manageinvites/edit");
	else if($mybb->input['action'] == 'manageinvites/delete')
		add_breadcrumb($lang->delete, "misc.php?action=manageinvites/delete");
	else if($mybb->input['action'] == 'manageinvites/post/add')
		add_breadcrumb($lang->adding, "misc.php?action=manageinvites/post/add");
	else if($mybb->input['action'] == 'manageinvites/post/edit')
		add_breadcrumb($lang->editing, "misc.php?action=manageinvites/post/edit");
	else if($mybb->input['action'] == 'manageinvites/multidelete' || $mybb->input['action'] == 'manageinvites/post/multidelete')
		add_breadcrumb($lang->invitation_multidelete, "misc.php?action=manageinvites/multidelete");

	require_once MYBB_ROOT.$mybb->config['admin_dir'].'/inc/functions.php'; // Import check_template function

	$page = "
<html>
<head>
<title>{$lang->manage_invitations} - ".htmlspecialchars_uni($mybb->settings['bbname'])."</title>
$headerinclude
<style type=\"text/css\">
.nav_item {
	display: block;
	padding: 1px 0 1px 23px;
}

.nav_home {
	background: url('images/modcp/home.gif') no-repeat left center;
}

.nav_myinvites {
	background: url('images/user.png') no-repeat left center;
}

.nav_add {
	background: url('images/page_white_key.png') no-repeat left center;
}

.nav_delete {
	background: url('images/invalid.gif') no-repeat left center;
}

.trow1 {
	padding-right: 4px;
}

.trow1 input[type=text], .trow2 input[type=text] {
	width: 96%;
}
</style>
</head>
<body>
$header
	\n";

	$menu = '<tr>
              <td class="trow1 smalltext">
                <a href="misc.php?action=manageinvites/home" class="nav_item nav_home">'.$lang->manage_invitations.'</a>
              </td>
            </tr>';

	if(invite_isAdmin())
	{
		$menu .= '
            <tr>
              <td class="trow1 smalltext">
                <a href="misc.php?action=manageinvites/home&amp;myinvites=1" class="nav_item nav_myinvites">'.$lang->invitations_myinvites.'</a>
              </td>
            </tr>';
	}

	if($mybb->input['action'] == 'manageinvites' || $mybb->input['action'] == 'manageinvites/home' || $mybb->input['action'] == 'manageinvites/multidelete')
	{
		if(isset($_GET['myinvites']) && $_GET['myinvites'] == 1)
			add_breadcrumb($lang->invitations_myinvites, "misc.php?action=manageinvites&myinvites=1");
		if(isset($_GET['search_invite']) && !empty($_GET['search_invite']))
			add_breadcrumb($lang->searchresult_invitation, "misc.php?action=manageinvites&search_invite=".urlencode($_GET['search_invite']));
		if($mybb->input['action'] == 'manageinvites/multidelete' && !invite_canMassDelete())
			error_no_permission();

		$emptyMessage = '<tr>
              <td class="trow1" colspan="8">
                <span class="smalltext">
                  <strong>'.$lang->invitations_emptylist.'</strong>
                </span>
              </td>
            </tr>';

		$tableHead = '<td class="tcat" width="10%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_code.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="5%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_used.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="5%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_maxuses.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="10%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_members.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="25%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_primarygroup.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="25%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_othergroups.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="15%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_expire.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="40px" style="min-width: 40px;">
                <span class="smalltext">
                  <strong>&nbsp;</strong>
                </span>
              </td>';

		$orderBy = 'ORDER BY ';
		switch($_GET['sortby'])
		{
			case 'creator':
				$orderBy .= 'u.username';
				break;
			case 'email':
				$orderBy .= 'c.email';
				break;
			case 'group':
				$orderBy .= 'g.title';
				break;
			case 'used':
				$orderBy .= 'c.used';
				break;
			case 'maxuses':
				$orderBy .= 'c.maxuses';
				break;
			case 'expiration':
				$orderBy .= 'c.expire';
				break;
		}
		if(isset($_GET['desc']) && $orderBy != 'ORDER BY ')
		{
			$orderBy .= ' DESC';
		}
		if($orderBy != 'ORDER BY ')
			$orderBy .= ', ';
		$orderBy .= 'c.id';
		if(!isset($_GET['asc']))
			$orderBy .= ' DESC';

		$sort = "\n                <span class=\"smalltext\" style=\"float: right;\">{$lang->sort} ";
		$sort .= invite_sortlink('id', $lang->sort_id);
		if($_GET['myinvites'] != 1 && invite_isAdmin() && $mybb->settings['invitation_showCreatedBy'])
			$sort .= invite_sortlink('creator', $lang->sort_creator);
		$sort .= invite_sortlink('email', $lang->sort_email);
		$sort .= invite_sortlink('group', $lang->sort_group);
		$sort .= invite_sortlink('used', $lang->sort_used);
		$sort .= invite_sortlink('maxuses', $lang->sort_maxuses);
		$sort .= invite_sortlink('expiration', $lang->sort_expiration);

		$sort_href = '?action='.htmlspecialchars_uni($mybb->input['action']).'&amp;myinvites='.intval($_GET['myinvites']).'&amp;search_invite='.htmlspecialchars_uni($_GET['search_invite']).'&amp;sortby='.htmlspecialchars_uni($_GET['sortby']);
		if($_GET['sortby'] == 'id')
		{
			if(!isset($_GET['asc']))
				$sort .= '&nbsp;<a href="'.$sort_href.'&amp;asc">'.$lang->asc.'</a>';
			else
				$sort .= '&nbsp;<a href="'.$sort_href.'">'.$lang->desc.'</a>';
		}
		else if(isset($_GET['desc']))
			$sort .= '&nbsp;<a href="'.$sort_href.'">'.$lang->asc.'</a>';
		else
			$sort .= '&nbsp;<a href="'.$sort_href.'&amp;desc">'.$lang->desc.'</a>';
		$sort .= $sort_creator.'</span>';


		$iListCount = 1;
		$iNdListCount = 1;
		$search = $db->escape_string($_GET['search_invite']);
		$query = $db->query('SELECT c.*, g.title AS primary_group, u.username AS createdby_user
		                     FROM '.TABLE_PREFIX.'invitecodes c
		                     LEFT JOIN '.TABLE_PREFIX.'usergroups g ON c.primarygroup = g.gid
		                     LEFT JOIN '.TABLE_PREFIX.'users u ON c.createdby = u.uid
		                     WHERE c.code LIKE \'%'.$search.'%\'
		                           OR c.email LIKE \'%'.$search.'%\'
		                           OR u.username LIKE \'%'.$search.'%\'
		                           OR g.title LIKE \'%'.$search.'%\'
		                           OR c.usedby LIKE \'%'.$search.'%\'
		                     '.$orderBy);

		while($data = $db->fetch_array($query))
		{
			if($data['createdby'] != $mybb->user['uid'] && (!invite_isAdmin() || $_GET['myinvites'] == 1))
				continue;

			$canBeUsed = (($data['maxuses'] == 0 || $data['used'] < $data['maxuses']) && ($data['expire'] == 0 || $data['expire'] > TIME_NOW));
			$controls = '<a href="misc.php?action=manageinvites/edit&amp;id='.intval($data['id']).'&amp;my_post_key='.$mybb->post_code.'">
			                 <img src="'.$mybb->settings['bburl'].'/images/pencil.png" alt="" title="'.$lang->edit_invitation.'" /></a>&nbsp;'.
			            '<a href="misc.php?action=manageinvites/delete&amp;id='.intval($data['id']).'&amp;my_post_key='.$mybb->post_code.'" onclick="if(!confirm(\''.addslashes($lang->confirm_action).'\')) return false;">
			                 <img src="'.$mybb->settings['bburl'].'/images/invalid.gif" alt="" title="'.$lang->delete_invitation.'" /></a>';

			$otherGroups = '';
			foreach(explode(',', $data['othergroups']) as $gid)
			{
				$ogQuery = $db->query('SELECT title FROM '.TABLE_PREFIX.'usergroups WHERE gid='.intval($gid));
				$ogData = $db->fetch_array($ogQuery);
				if(!empty($otherGroups))
					$otherGroups .= ', ';
				$otherGroups .= htmlspecialchars_uni($ogData['title']);
			}

			if(empty($otherGroups))
				$otherGroups = $lang->na;

			$usedBy = '';
			foreach(explode(',', $data['usedby']) as $uid)
			{
				if(!empty($uid))
				{
					$ubQuery = $db->query('SELECT username FROM '.TABLE_PREFIX.'users WHERE uid='.intval($uid));
					$ubData = $db->fetch_array($ubQuery);

					if(!empty($ubData['username']))
					{
						if(!empty($usedBy))
							$usedBy .= ', ';

						$usedBy .= '<a href="'.get_profile_link(intval($uid)).'">'.htmlspecialchars_uni($ubData['username']).'</a>';
					}
				}
			}

			if((empty($usedBy) || $canBeUsed) && !empty($data['email']))
			{
				foreach(explode(',', $data['email']) as $email)
				{
					if(!empty($usedBy))
						$usedBy .= ', ';

					$usedBy .= '<a href="mailto:'.htmlspecialchars_uni($email).'" style="color: #000000;">'.htmlspecialchars_uni($email).'</a>';
				}
			}

			$exp = ($data['expire'] == 0) ? $lang->never : my_date($mybb->settings['dateformat'], $data['expire']).', '.my_date($mybb->settings['timeformat'], $data['expire']);

			if(empty($usedBy))
				$usedBy = '--';

			$maxUses = (intval($data['maxuses']) == 0) ? '--' : intval($data['maxuses']);
			$createdby = (!empty($data['createdby_user']) && $data['createdby_user'] != $mybb->user['username'] && $mybb->settings['invitation_showCreatedBy']) ? '<br /><span class="smalltext">'.$lang->invitation_createdby.' <a href="'.get_profile_link($data['createdby']).'">'.htmlspecialchars_uni($data['createdby_user']).'</a></span>' : '';

			$multidelete = ($mybb->input['action'] == 'manageinvites/multidelete') ? ' <a href="?action=manageinvites/edit&amp;id='.htmlspecialchars_uni($data['id']).'&amp;my_post_key='.$mybb->post_code.'">' : '';
			$multideleteEnd = ($mybb->input['action'] == 'manageinvites/multidelete') ? '</a>' : '';
			if($mybb->input['action'] == 'manageinvites/multidelete')
				$controls = '<input type="checkbox" name="multidelete['.intval($data['id']).']" />';

			if($canBeUsed)
			{
				$class = ($iListCount%2) ? 'trow1' : 'trow2';
				$iList .= '<tr>
              <td class="'.$class.'">
                  '.$multidelete.'<strong>'.htmlspecialchars_uni($data['code']).'</strong>'.$multideleteEnd.$createdby.'
              </td>
              <td class="'.$class.'" align="center">
                '.intval($data['used']).'
              </td>
              <td class="'.$class.'" align="center">
                '.$maxUses.'
              </td>
              <td class="'.$class.'" align="center">
                '.$usedBy.'
              </td>
              <td class="'.$class.'" align="center">
                '.htmlspecialchars_uni($data['primary_group']).'
              </td>
              <td class="'.$class.'" align="center">
                '.$otherGroups.'
              </td>
              <td class="'.$class.'" align="center">
                '.$exp.'
              </td>
              <td class="'.$class.'" align="center">
                '.$controls.'
              </td>
            </tr>';
				$iListCount++;
			}
			else
			{
				$class = ($iNdListCount%2) ? 'trow1' : 'trow2';
				$iNdList .= '<tr>
              <td class="'.$class.'">
                  '.$multidelete.'<strong>'.htmlspecialchars_uni($data['code']).'</strong>'.$multideleteEnd.$createdby.'
              </td>
              <td class="'.$class.'" align="center">
                '.intval($data['used']).'
              </td>
              <td class="'.$class.'" align="center">
                '.intval($data['maxuses']).'
              </td>
              <td class="'.$class.'" align="center">
                '.$usedBy.'
              </td>
              <td class="'.$class.'" align="center">
                '.htmlspecialchars_uni($data['primary_group']).'
              </td>
              <td class="'.$class.'" align="center">
                '.$otherGroups.'
              </td>
              <td class="'.$class.'" align="center">
                '.$exp.'
              </td>
              <td class="'.$class.'" align="center">
                '.$controls.'
              </td>
            </tr>';
				$iNdListCount++;
			}
		}

		if(check_template($mybb->settings['invitation_headerText']))
			$headerText = 'Invalid PHP in header text';
		else
		{
			eval("\$headerText = \"".$mybb->settings['invitation_headerText']."\";");
		}

		if(!empty($headerText))
		{
			$page .= '<p style="text-align: center; margin: 0px;">'.$headerText.'</p>';
		}
		if($mybb->settings['invitation_showReferredCount'])
		{
			$page .= '<span style="float: left; margin-left: 5px;"><br />'.$lang->invite_referredcount." <strong>{$mybb->user['referrals']}</strong></span>";
		}
		$page .= '
<div style="float: right; text-align: right;">
	<form method="get" action="misc.php" style="margin-top: 3px;">
		<input type="hidden" name="action" value="'.htmlspecialchars_uni($mybb->input['action']).'" />
		<input type="hidden" name="myinvites" value="' . intval($_GET['myinvites']) . '" />
		<label for="search_invite"><strong>'.$lang->search_invitation.':</strong></label>
		<input type="text" id="search_invite" name="search_invite" value="'.htmlspecialchars_uni($_GET['search_invite']).'" />
		<input type="submit" value="Go" />
	</form>
	'.$sort.'
</div>';
		$page .= '
<table width="100%" border="0" align="center">
  <tbody>
    <tr>
      <td width="180" valign="top">
        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
          <tbody>
            <tr>
              <td class="thead">
                <strong>'.$lang->menu.'</strong>
              </td>
            </tr>
            '.$menu.'
            <tr>
              <td class="tcat">
                <div class="float_right">
                  <img src="images/collapse.gif" id="actions_img" class="expander" alt="[-]" title="[-]" style="cursor: pointer" />
                </div>
                <div>
                  <span class="smalltext">
                    <strong>'.$lang->manage_invitations.'</strong>
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody style="" id="actions_e">
            <tr>
              <td class="trow1 smalltext">
                <a href="misc.php?action=manageinvites/add" class="nav_item nav_add">'.$lang->add_invitation.'</a>
              </td>
            </tr>';
            if($mybb->input['action'] != 'manageinvites/multidelete' && invite_canMassDelete())
            {
				$page .= '
            <tr>
              <td class="trow1 smalltext">
                <a href="misc.php?action=manageinvites/multidelete" class="nav_item nav_delete">'.$lang->invitation_multidelete.'</a>
              </td>
            </tr>';
            }
            else if(invite_canMassDelete())
            {
				$page .= '
            <tr>
              <td class="trow1 smalltext">
                <a href="misc.php?action=manageinvites/home" class="nav_item nav_delete">'.$lang->cancel_multidelete.'</a>
              </td>
            </tr>';
            }
		$page .= '
          </tbody>
        </table>
      </td>
      <td valign="top">';
		if($mybb->input['action'] == 'manageinvites/multidelete')
			$page .= '<form method="post" action="misc.php?action=manageinvites/post/multidelete">';
		$page .= '
        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
          <tbody>
            <tr>
              <td class="thead" align="center" colspan="8">
                <strong>'.$lang->available_invitations.'</strong>
              </td>
            </tr>
            <tr>
              '.$tableHead.'
            </tr>';
		if(!empty($iList))
			$page .= $iList;
		else
			$page .= $emptyMessage;
            $page .= '
          </tbody>
        </table>
        <br />
        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
          <tbody>
            <tr>
              <td class="thead" align="center" colspan="8">
                <strong>'.$lang->unavailable_invitations.'</strong>
              </td>
            </tr>
            <tr>
              '.$tableHead.'
            </tr>';
		if(!empty($iNdList))
			$page .= $iNdList;
		else
			$page .= $emptyMessage;
            $page .= '
          </tbody>
        </table>
        ';
        if($mybb->input['action'] == 'manageinvites/multidelete')
			$page .= '<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="submit" value="'.$lang->multidelete_button.'" style="margin-top: 3px;" /></form>';
		$page .= '
      </td>
    </tr>
  </tbody>
</table>';
		if(check_template($mybb->settings['invitation_footerText']))
			$footerText = 'Invalid PHP in footer text';
		else
		{
			eval("\$footerText = \"".$mybb->settings['invitation_footerText']."\";");
		}
		if(!empty($mybb->settings['invitation_footerText']))
		{
			$page .= '<p style="text-align: center; margin-bottom: 0px;">'.$footerText.'</p>';
		}
	}
	else if($mybb->input['action'] == 'manageinvites/add')
	{
		if(!invite_canCreateNew())
			redirect('misc.php?action=manageinvites/home', $lang->invitation_maxcodes);

		$query = $db->query('SELECT code FROM '.TABLE_PREFIX.'invitecodes');
		$codes = array();
		while($item = $db->fetch_array($query))
		{
			$codes[] = $item['code'];
		}

		while(in_array($code, $codes) || !isset($code))
		{
			$code = random_str(8);
		}

		$query = $db->query('SELECT gid, title FROM '.TABLE_PREFIX.'usergroups');
		while($g = $db->fetch_array($query))
		{
			if(invite_isAdmin() || invite_isGroupAllowed($g['gid']))
			{
				$grouplist .= '<option value="'.htmlspecialchars_uni($g['gid']).'">'.htmlspecialchars_uni($g['title'])."</option>\n";
			}
		}

		$page .= '
<table width="100%" border="0" align="center">
  <tbody>
    <tr>
      <td width="180" valign="top">
        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
          <tbody>
            <tr>
              <td class="thead">
                <strong>'.$lang->menu.'</strong>
              </td>
            </tr>
            '.$menu.'
          </tbody>
        </table>
      </td>
      <td valign="top">
        <form action="misc.php?action=manageinvites/post/add" method="post" style="text-align: center;">
        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
          <tbody>
            <tr>
              <td class="thead" align="center" colspan="6">
                <strong>'.$lang->add_invitation.'</strong>
              </td>
            </tr>
            <tr>
              <td class="tcat" width="10%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_code.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="20%">
                <span class="smalltext">
                  <strong>'.$lang->email_edit.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="10%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_maxuses.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="18%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_primarygroup.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="20%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_othergroups_edit.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="22%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_expire_edit.'</strong>
                </span>
              </td>
            </tr>
            <tr>
              <td class="trow1">
                <input type="text" name="code" value="'.$code.'" maxlength="10" /><br /><br /><br /><br /><br />
              </td>
              <td class="trow1">
                <input type="text" name="email" value="" /><br />
                <input type="checkbox" name="nomail" id="nomail" /> <label for="nomail">'.$lang->invitation_sendnomail.'</label><br />
                <input type="checkbox" name="refme" id="refme" checked="checked" /> <label for="refme">'.$lang->invitation_mail_refme.'</label><br /><br /><br />
              </td>
              <td class="trow1">
                <input type="text" name="maxuses" value="1" /><br /><br /><br /><br /><br />
              </td>
              <td class="trow1">
                <select name="group" style="width: 100%;">
                  <option value=""> </option>
                  '.$grouplist.'
                </select>
                <br /><br /><br /><br /><br />
              </td>
              <td class="trow1">
                <select multiple="multiple" name="othergroups[]" style="height: 100px; width: 100%;">
                  <option value=""> </option>
                  '.$grouplist.'
                </select>
              </td>
              <td class="trow1">
                <input type="text" name="exp" value="" /><br />
                <span class="smalltext">Ex. "4 days", "2 months", "5 weeks"</span>
                <br /><br /><br /><br />
              </td>
            </tr>
          </tbody>
        </table>
        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
        <br />
        <input type="submit" name="addcode" value="'.$lang->add_and_close.'" />
        <input type="submit" name="addcode-continue" value="'.$lang->add_and_return.'" />
        </form>
      </td>
    </tr>
  </tbody>
</table>';
	}
	else if($mybb->input['action'] == 'manageinvites/edit')
	{
		verify_post_check($mybb->input['my_post_key']);

		$id = intval($_GET['id']);
		$query = $db->query('SELECT * FROM '.TABLE_PREFIX.'invitecodes WHERE id='.$id);
		$data = $db->fetch_array($query);
		if(count($data) <= 0 || ($data['createdby'] != $mybb->user['uid'] && !invite_isAdmin()))
			redirect('misc.php?action=manageinvites/home', $lang->selected_isnull);

		$code = htmlspecialchars_uni($data['code']);
		foreach(explode(',', $data['email']) as $m)
		{
			if(!empty($email))
				$email .= ', ';
			$email .= htmlspecialchars_uni($m);
		}
		$maxuses = intval($data['maxuses']);
		$group = intval($data['primarygroup']);
		$otherGroups = explode(',', $data['othergroups']);
		if(!is_array($otherGroups)) $otherGroups = array();

		$query = $db->query('SELECT gid, title FROM '.TABLE_PREFIX.'usergroups');
		while($g = $db->fetch_array($query))
		{
			if(invite_isAdmin() || invite_isGroupAllowed($g['gid']))
			{
				$pSelected = ($g['gid'] == $group) ? 'selected="selected"' : '';
				$grouplist .= '<option value="'.htmlspecialchars_uni($g['gid']).'" '.$pSelected.'>'.htmlspecialchars_uni($g['title'])."</option>\n";

				$oSelected = (in_array($g['gid'], $otherGroups)) ? 'selected="selected"' : '';
				$groupslist .= '<option value="'.htmlspecialchars_uni($g['gid']).'" '.$oSelected.'>'.htmlspecialchars_uni($g['title'])."</option>\n";
			}
		}

		$exp = (!empty($data['expire']) > 0) ? $data['expire'] : '';

		$page .= '
<table width="100%" border="0" align="center">
  <tbody>
    <tr>
      <td width="180" valign="top">
        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
          <tbody>
            <tr>
              <td class="thead">
                <strong>'.$lang->menu.'</strong>
              </td>
            </tr>
            '.$menu.'
          </tbody>
          <tr>
              <td class="tcat">
                <div class="float_right">
                  <img src="images/collapse.gif" id="actions_img" class="expander" alt="[-]" title="[-]" style="cursor: pointer" />
                </div>
                <div>
                  <span class="smalltext">
                    <strong>'.$lang->manage_invitations.'</strong>
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody style="" id="actions_e">
            <tr>
              <td class="trow1 smalltext">
                <a href="misc.php?action=manageinvites/delete&amp;id='.$id.'" onclick="if(!confirm(\''.addslashes($lang->confirm_action).'\')) return false;" class="nav_item nav_delete">'.$lang->delete_this_invitation.'</a>
              </td>
            </tr>
        </table>
      </td>
      <td valign="top">
        <form action="misc.php?action=manageinvites/post/edit" method="post" style="text-align: center;">
        <table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
          <tbody>
            <tr>
              <td class="thead" align="center" colspan="6">
                <strong>'.$lang->edit_invitation.'</strong>
              </td>
            </tr>
            <tr>
              <td class="tcat" width="10%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_code.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="20%">
                <span class="smalltext">
                  <strong>'.$lang->email_edit.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="10%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_maxuses.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="18%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_primarygroup.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="20%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_othergroups_edit.'</strong>
                </span>
              </td>
              <td class="tcat" align="center" width="22%">
                <span class="smalltext">
                  <strong>'.$lang->invitation_expire_edit.'</strong>
                </span>
              </td>
            </tr>
            <tr>
              <td class="trow1">
                <input type="text" name="code" value="'.$code.'" maxlength="10" /><br /><br /><br /><br /><br />
              </td>
              <td class="trow1">
                <input type="text" name="email" value="'.$email.'" /><br />
                <input type="checkbox" name="nomail" id="nomail" /> <label for="nomail">'.$lang->invitation_sendnomail.'</label><br />
                <input type="checkbox" name="refme" id="refme" checked="checked" /> <label for="refme">'.$lang->invitation_mail_refme.'</label><br /><br /><br />
              </td>
              <td class="trow1">
                <input type="text" name="maxuses" value="'.$maxuses.'" /><br /><br /><br /><br /><br />
              </td>
              <td class="trow1">
                <select name="group" style="width: 100%;">
                  <option value=""> </option>
                  '.$grouplist.'
                </select>
                <br /><br /><br /><br /><br />
              </td>
              <td class="trow1">
                <select multiple="multiple" name="othergroups[]" style="height: 100px; width: 100%;">
                  <option value=""> </option>
                  '.$groupslist.'
                </select>
              </td>
              <td class="trow1">
                <input type="text" name="exp" value="'.$exp.'" /><br />
                <span class="smalltext">Ex. "4 days", "2 months", "5 weeks",<br /> or Unix timestamp</span>
                <br /><br /><br />
              </td>
            </tr>
          </tbody>
        </table>
        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
        <input type="hidden" name="id" value="'.$id.'" />
        <br />
        <input type="submit" value="'.$lang->edit_and_close.'" />
        </form>
      </td>
    </tr>
  </tbody>
</table>';
	}
	else if($mybb->input['action'] == 'manageinvites/post/add')
	{
		verify_post_check($mybb->input['my_post_key']);
		if(!isset($_POST['code']) || !isset($_POST['email']) || !isset($_POST['maxuses']) || !isset($_POST['group']) || !isset($_POST['exp']))
			redirect('misc.php?action=manageinvites/add', $lang->invalid_form);

		if(!invite_canCreateNew())
			redirect('misc.php?action=manageinvites/home', $lang->invitation_maxcodes);

		$query = $db->query('SELECT code FROM '.TABLE_PREFIX.'invitecodes');
		$codes = array();
		while($item = $db->fetch_array($query))
		{
			$codes[] = $item['code'];
		}

		if(in_array($_POST['code'], $codes))
			redirect('misc.php?action=manageinvites/add', $lang->code_already_used);

		$code = $db->escape_string($_POST['code']);
		$email = $db->escape_string($_POST['email']);
		$email = preg_replace('#\s*,\s*#', ',', $email);
		$limit = intval($_POST['maxuses']);
		$group = intval($_POST['group']);

		if(!is_array($_POST['othergroups']))
			$_POST['othergroups'] = array();

		foreach($_POST['othergroups'] as $og)
		{
			$ogid = intval($og);
			if($ogid > 0 && (invite_isAdmin() || invite_isGroupAllowed($og)))
			{
				if(!empty($otherGroups))
					$otherGroups .= ',';

				$otherGroups .= $ogid;
			}
		}

		if(!preg_match('#^\s*([0-9]+)\s*(minutes?|hours?|days?|weeks?|months?|years?)\s*$#isU', $_POST['exp']))
			$exp = 0;
		else
			$exp = $db->escape_string(strtotime("+{$_POST['exp']}") - 1);

		if(empty($code) || $group <= 0 || (empty($exp) && $exp !== 0))
			redirect('misc.php?action=manageinvites/add', $lang->fill_all_fields);
		else if(!preg_match('#^[a-zA-Z0-9_-]+$#', $code))
			redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->invalid_code);
		else if(!empty($email))
		{
			foreach(explode(',', $email) as $m)
			{
				if(!preg_match('#^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$#', $m))
				{
					redirect('misc.php?action=manageinvites/add', $lang->invalid_email);
					break;
				}
				else if($m == $mybb->user['email'])
				{
					redirect('misc.php?action=manageinvites/add', $lang->invited_yourself);
					break;
				}
			}
		}
		if(my_strlen($code) < 8)
			redirect('misc.php?action=manageinvites/add', $lang->code_too_short);
		else if(my_strlen($code) > 10)
			redirect('misc.php?action=manageinvites/add', $lang->code_too_long);
		else if(!invite_isAdmin() && !invite_isGroupAllowed($group))
			redirect('misc.php?action=manageinvites/add', $lang->invitation_forbiddengroup);

		$array = array('code' => $code, 'email' => $email, 'maxuses' => $limit, 'primarygroup' => $group, 'othergroups' => $otherGroups, 'expire' => $exp, 'createdby' => $mybb->user['uid']);
		$redirect = (!isset($_POST['addcode-continue'])) ? 'misc.php?action=manageinvites/home' : 'misc.php?action=manageinvites/add';
		if($db->insert_query('invitecodes', $array))
		{
			if(!empty($email) && !isset($_POST['nomail']))
			{
				//$headers = 'From: '.$mybb->settings['adminemail'];
				$query = $db->query('SELECT title FROM '.TABLE_PREFIX.'usergroups WHERE gid = \'' . $group . '\'');
				$g = $db->fetch_array($query);

				$body = null;
				$subject = $mybb->settings['bbname'];

				foreach(explode(',', $email) as $addr)
				{
					$body = invite_buildEmailBody($code, $g['title'], $exp, $email, !isset($_POST['refme']));
					if(empty($mailed))
						$mailed = '<br />';

					if(my_mail($addr, $subject, $body))
						$mailed .= '<br />'.$lang->invitation_mailed.' '.htmlspecialchars_uni($addr);
					else
						$mailed .= '<br />'.$lang->invitation_mail_failed.' '.htmlspecialchars_uni($addr);
				}
			}
			redirect($redirect, $lang->invitation_created.$mailed);
		}
	}
	else if($mybb->input['action'] == 'manageinvites/post/edit')
	{
		verify_post_check($mybb->input['my_post_key']);
		if(!isset($_POST['id']) || !isset($_POST['code']) || !isset($_POST['email']) || !isset($_POST['maxuses']) || !isset($_POST['group']) || !isset($_POST['exp']))
			redirect('misc.php?action=manageinvites/home', $lang->invalid_form);

		$id = intval($_POST['id']);

		$selectq = $db->simple_select('invitecodes', 'id, createdby', "id=$id");
		$data = $db->fetch_array($selectq);
		if($data['id'] != $id || ($data['createdby'] != $mybb->user['uid'] && !invite_isAdmin()))
			redirect('misc.php?action=manageinvites/home', $lang->selected_isnull);

		$query = $db->query('SELECT code FROM '.TABLE_PREFIX.'invitecodes WHERE id!='.$id);
		$codes = array();
		while($item = $db->fetch_array($query))
		{
			$codes[] = $item['code'];
		}

		if(in_array($_POST['code'], $codes))
			redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->code_already_used);

		$code = $db->escape_string($_POST['code']);
		$email = $db->escape_string($_POST['email']);
		$email = preg_replace('#\s*,\s*#', ',', $email);
		$limit = intval($_POST['maxuses']);
		$group = intval($_POST['group']);

		if(!is_array($_POST['othergroups']))
			$_POST['othergroups'] = array();

		foreach($_POST['othergroups'] as $og)
		{
			$ogid = intval($og);
			if($ogid > 0 && (invite_isAdmin() || invite_isGroupAllowed($og)))
			{
				if(!empty($otherGroups))
					$otherGroups .= ',';

				$otherGroups .= $ogid;
			}
		}

		if(!preg_match('#^\s*([0-9]+)\s*(minutes?|hours?|days?|weeks?|months?|years?)\s*$#isU', $_POST['exp']))
		{
			if(intval($_POST['exp']) > TIME_NOW / 2)
				$exp = intval($_POST['exp']);
			else
				$exp = 0;
		}
		else
			$exp = $db->escape_string(strtotime("+{$_POST['exp']}") - 1);

		if(empty($code) || $group <= 0 || (empty($exp) && $exp !== 0))
			redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->fill_all_fields);
		else if(!preg_match('#^[a-zA-Z0-9_-]+$#', $code))
			redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->invalid_code);
		else if(!empty($email))
		{
			foreach(explode(',', $email) as $m)
			{
				if(!preg_match('#^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$#', $m))
				{
					redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->invalid_email);
					break;
				}
				else if($m == $mybb->user['email'])
				{
					redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->invited_yourself);
					break;
				}
			}
		}
		if(my_strlen($code) < 8)
			redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->code_too_short);
		else if(my_strlen($code) > 10)
			redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->code_too_long);
		else if(!invite_isAdmin() && !invite_isGroupAllowed($group))
			redirect('misc.php?action=manageinvites/edit&id='.$id.'&my_post_key='.$mybb->post_code, $lang->invitation_forbiddengroup);

		$array = array('code' => $code, 'email' => $email, 'maxuses' => $limit, 'primarygroup' => $group, 'othergroups' => $otherGroups, 'expire' => $exp);
		if($db->update_query('invitecodes', $array, 'id='.$id))
		{
			if(!empty($email) && !isset($_POST['nomail']))
			{
				$query = $db->query('SELECT title FROM '.TABLE_PREFIX.'usergroups WHERE gid = \'' . $group . '\'');
				$g = $db->fetch_array($query);

				$body = null;
				$subject = $mybb->settings['bbname'];

				foreach(explode(',', $email) as $addr)
				{
					$body = invite_buildEmailBody($code, $g['title'], $exp, $email, !isset($_POST['refme']));

					if(empty($mailed))
						$mailed = '<br />';

					if(my_mail($addr, $subject, $body))
						$mailed .= '<br />'.$lang->invitation_mailed.' '.htmlspecialchars_uni($addr);
					else
						$mailed .= '<br />'.$lang->invitation_mail_failed.' <b>'.htmlspecialchars_uni($addr).'</b>';
				}
			}
			redirect('misc.php?action=manageinvites/home', $lang->invitation_edited.$mailed);
		}
	}
	else if($mybb->input['action'] == 'manageinvites/delete')
	{
		$id = intval($_GET['id']);
		if($id <= 0)
			redirect('misc.php?action=manageinvites/home', $lang->selected_isnull);

		$selectq = $db->simple_select('invitecodes', 'createdby', "id=$id");
		$data = $db->fetch_array($selectq);
		if($data['createdby'] != $mybb->user['uid'] && !invite_isAdmin())
			redirect('misc.php?action=manageinvites/home', $lang->selected_isnull);

		if($db->delete_query("invitecodes", "id=$id"))
			redirect('misc.php?action=manageinvites/home', $lang->invitation_deleted);
	}
	else if($mybb->input['action'] == 'manageinvites/post/multidelete')
	{
		verify_post_check($mybb->input['my_post_key']);
		if(!invite_canMassDelete())
			error_no_permission();

		if(!is_array($mybb->input['multidelete']))
			error($lang->no_invitation_selected);

		$mybb->input['multidelete'] = array_unique(array_map('intval', array_keys($mybb->input['multidelete'])));
		if(count($mybb->input['multidelete']) < 1)
			error($lang->no_invitation_selected);

		eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
		$page .= '<form action="misc.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>'.$lang->invitation_multidelete.'</strong></td>
</tr>
<tr>
<td class="trow1" colspan="2" align="center">'.$lang->confirm_action.' '.sprintf($lang->confirm_multidelete, count($mybb->input['multidelete'])).'</td>
</tr>
'.$loginbox.'
</table>
<br />
<div align="center"><input type="submit" class="button" name="submit" value="'.$lang->delete_invitations.'" /></div>
<input type="hidden" name="action" value="manageinvites/post/do_multidelete" />
<input type="hidden" name="invites" value="'.implode(',', $mybb->input['multidelete']).'" />
</form>';
	}
	else if($mybb->input['action'] == 'manageinvites/post/do_multidelete')
	{
		verify_post_check($mybb->input['my_post_key']);
		if(!invite_canMassDelete())
			error_no_permission();

		$count = 0;
		$query = $db->simple_select('invitecodes', 'code,createdby', "id IN(".$db->escape_string($mybb->input['invites']).")");
		while($data = $db->fetch_array($query))
		{
			if($data['createdby'] != $mybb->user['uid'] && !invite_isAdmin())
				error_no_permission();
			$count++;
		}
		if($count == 0)
			error($lang->selected_isnull);

		$invites = explode(',', $mybb->input['invites']);
		if(count($invites) < 1)
			error($lang->no_invitation_selected);

		foreach($invites as $id)
		{
			$db->delete_query("invitecodes", "id=$id");
		}

		redirect('misc.php?action=manageinvites/multidelete', $lang->multidelete_deleted);
	}
	else
	{
		$page = '';
		return;
	}

	$page .= ob_get_contents();
	$page .= "\n$footer\n</body>\n</html>\n";

	ob_clean();
	output_page($page);
}

function invite_buildEmailBody($code, $groupname, $exp, $email, $noref = false)
{
	global $mybb, $lang;

	$expire = ($exp == 0) ? $lang->never : my_date($mybb->settings['dateformat'], $exp).', '.my_date($mybb->settings['timeformat'], $exp);

	$mail1 = (!$noref) ? str_replace('{username}', $mybb->user['username'], $lang->invitation_mail_1) : $lang->invitation_mail_1_noref;

	$body = $mail1.' « '.$mybb->settings['bbname']." » ({$mybb->settings['bburl']})\n{$lang->invitation_mail_2}\n\n\t{$mybb->settings['bburl']}/member.php?action=register&code=$code\n\t{$lang->invitation_mail_group} {$groupname}\n\t{$lang->invitation_mail_expire} {$expire}\n\t{$lang->invitation_mail_email} {$email}\n\n{$lang->invitation_mail_3}\n\n{$lang->invitation_mail_4} {$mybb->settings['bbname']}.\n\n------------------------------------------\n{$lang->invitation_mail_5} {$mybb->settings['returnemail']}";

	return $body;
}

function invite_isAdmin()
{
	global $mybb;
	if(!$mybb->user['uid'])
		return false;

	foreach(invite_getAllUsergroups() as $group)
	{
		if(in_array($group, array($mybb->settings['invitation_adminPermissions1'], $mybb->settings['invitation_adminPermissions2'], $mybb->settings['invitation_adminPermissions3'])))
			return true;
	}
	return false;
}

function invite_canManage()
{
	global $mybb;
	if(!$mybb->user['uid'])
		return false;

	foreach(invite_getAllUsergroups() as $group)
	{
		$settings_gids = explode(',', $mybb->settings['invitation_userPermissions']);
		foreach($settings_gids as $item)
		{
			$array = explode('=', $item);
			if($array[0] == $group)
				return true;
		}
	}

	return false;
}

function invite_getAllUsergroups()
{
	global $mybb;
	$groups = array($mybb->user['usergroup']);
	foreach(explode(',', $mybb->user['additionalgroups']) as $gid)
	{
		$groups[] = $gid;
	}
	return array_unique(array_filter($groups));
}

function invite_getMaxInvites($gid)
{
	global $mybb;

	if(invite_isAdmin())
		return 0;

	$settings_gids = explode(',', $mybb->settings['invitation_userPermissions']);
	foreach($settings_gids as $item)
	{
		$array = explode('=', $item);
		if($array[0] == $gid)
		{
			if(count($array) > 1)
				return intval($array[1]);

			return 0;
		}
	}
}

function invite_canCreateNew()
{
	global $mybb, $db;

	if(invite_isAdmin())
		return true;

	$query = $db->simple_select('invitecodes', 'COUNT(code) AS `count`', 'createdby='.$db->escape_string($mybb->user['uid']));
	$data = $db->fetch_array($query);

	return (intval(invite_getMaxInvites($mybb->user['usergroup'])) == 0 || $data['count'] < invite_getMaxInvites($mybb->user['usergroup']));
}

$plugins->add_hook('member_profile_start', 'invite_profile');
function invite_profile()
{
	global $mybb, $referredList, $referrer, $db, $lang;
	$lang->load('invite');
	$referredList = '';
	$referrer = '';

	if($mybb->settings['invitation_status'] != 1)
		return;

	if($mybb->settings['invitation_showReferredCountProfile'])
	{
		$mybb->settings['usereferrals'] = 1;
	}

	if($mybb->input['uid'])
	{
		$uid = intval($mybb->input['uid']);
	}
	else
	{
		$uid = $mybb->user['uid'];
	}

	if(!$mybb->settings['usereferrals'])
		return;

	$query = $db->simple_select('users', 'referrals,referrer', 'uid='.intval($uid), array('limit' => '1'));
	$memprofile = $db->fetch_array($query);

	if($mybb->settings['invitation_displayReferredUsers'] && $memprofile['referrals'] > 0)
	{
		$count = 0;
		$query = $db->simple_select('users', 'uid,username,usergroup,displaygroup', 'referrer='.intval($uid), array('order_by' => 'uid DESC'));
		while($data = $db->fetch_array($query))
		{
			if(empty($referredList))
				$referredList = '(';
			else
				$referredList .= ', ';

			if($mybb->settings['invitation_maxReferredUsers'] && $count > $mybb->settings['invitation_maxReferredUsers'])
			{
				$referredList .= '...';
				break;
			}

			$referredList .= build_profile_link(htmlspecialchars_uni($data['username']), $data['uid']);
		}
		if(!empty($referredList))
			$referredList .= ')';
	}

	if($mybb->settings['invitation_displayInvitedBy'] && $memprofile['referrer'] > 0)
	{
		$query = $db->simple_select('users', 'username', 'uid='.intval($memprofile['referrer']).' LIMIT 1');
		$data = $db->fetch_array($query);
		$referrer = '<tr>
<td class="trow1"><strong>'.$lang->invited_by.'</strong></td>
<td class="trow1">'.build_profile_link(htmlspecialchars_uni($data['username']), $memprofile['referrer']).'</td>
</tr>';
	}
}

function invite_sortlink($name, $label)
{
	global $mybb;

	$sort_href = '?action='.htmlspecialchars_uni($mybb->input['action']).'&amp;myinvites='.intval($_GET['myinvites']).'&amp;search_invite='.htmlspecialchars_uni($_GET['search_invite']).'&amp;sortby=';
	$desc = (isset($_GET['desc'])) ? '&amp;desc' : '';
	if(empty($_GET['sortby']))
		$_GET['sortby'] = 'id';

	if($_GET['sortby'] == $name)
		return '<strong>'.$label."</strong> \n";
	else
		return '<a href="'.$sort_href.$name.$desc.'">'.$label."</a> \n";
}

function invite_canMassDelete()
{
	global $mybb;
	return (!$mybb->settings['invitation_multideleteOnlyAdmins'] || invite_isAdmin());
}

function invite_isGroupAllowed($gid)
{
	global $mybb;
	if(in_array($gid, explode(',', $mybb->settings['invitation_allowedgroups'])))
		return true;

	foreach(explode(',', $mybb->settings['invitation_allowedgroups']) as $item)
	{
		if(my_strpos($item, '=') == 1)
		{
			$item = explode('=', $item);
			if($item[1] == $gid)
			{
				foreach(invite_getAllUsergroups() as $group)
				{
					if($group == $item[0])
						return true;
				}
			}
		}
	}

	return false;
}

$plugins->add_hook('build_friendly_wol_location_end', 'invite_wol');
function invite_wol($wol)
{
	global $lang;
	$lang->load('invite');

	if(my_strpos($wol['user_activity']['location'], 'misc.php?action=manageinvites'))
	{
		if(invite_isAdmin() || invite_canManage())
			$wol['location_name'] = '<a href="misc.php?action=manageinvites">'.$lang->manage_invitations.'</a>';
		else
			$wol['location_name'] = $lang->manage_invitations;
	}
	return $wol;
}
