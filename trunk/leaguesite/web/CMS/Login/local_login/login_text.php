<?php
	if (!isset($account_needs_to_be_converted) || !($account_needs_to_be_converted))
	{
		if ((isset($_SESSION['user_logged_in'])) &&	($_SESSION['user_logged_in']))
		{
			$tmpl->done('already logged in');
		}
	}
	
	$db_imported = new db_import;
	$account_old_website = htmlent($db_imported->old_website());
	
	$msg = '';
	
	if (!(isset($_POST['local_login_wanted']) && $_POST['local_login_wanted']))
	{
		$msg .= '<form action="' . baseaddress() . 'Login/'. '" method="post">' . "\n";
		$msg .= '<p class="first_p">' . "\n";
		if ($site->force_external_login_when_trying_local_login())
		{
			$msg .= $site->return_self_closing_tag('input type="submit" name="local_login_wanted" value="Update old account from ' . $account_old_website . '"');
		} else
		{
			$msg .= $site->return_self_closing_tag('input type="submit" name="local_login_wanted" value="Local login"');
		}
		$msg .= '</p>' . "\n";
		$msg .= '</form>' . "\n";
	}
	if (isset($_POST['local_login_wanted']) && $_POST['local_login_wanted'])
	{
		$msg .= '<div class="static_page_box">' . "\n";
		
		$msg .= '<p class="first_p">';
		if ($site->convert_users_to_external_login())
		{
			require_once dirname(dirname(__FILE__)) . '/login_module_list.php';
			if (isset($module['bzbb']) && ($module['bzbb']))
			{
				$msg .= '<strong><span class="unread_messages">Before you continue make absolutely sure your account here and the my.bzflag.org/bb/ (forum) account have exactly the same username or you will give someone else access to your account and that access can never be revoked.</span></strong></p>';
			}
		}
		
		$msg .= '<p>Enter login data from <strong>' . $account_old_website . '</strong> here!</p>';
		$msg .= "\n";
		
		// load form
		require_once 'form.php';
		$msg .= writeForm();
		
		$msg .= '<p>Note: Only global login has the ability to allow more than standard permissions at the moment.</p>' . "\n";
	}
	
	$tmpl->addMSG($msg);
?>