<?php
	function writeForm()
	{
		global $site;
		
		$msg = '<form action="' . baseaddress() . 'Login/'. '" method="post">' . "\n";
		$msg .= '<div class="p">Name:</div>' . "\n";
		$msg .= '<p class="first_p">' . "\n";
		$msg .= $site->return_self_closing_tag('input type="text" class="small_input_field" name="loginname" value="" maxlength="300"');
		$msg .= '</p>' . "\n";
		
		$msg .= '<div class="p">Password:</div>' . "\n";
		$msg .= '<p class="first_p">' . "\n";
		$msg .= $site->return_self_closing_tag('input type="password" name="pw" value="" maxlength="300"');
		$msg .= '</p>' . "\n";
		
		$msg .= '<div>' . "\n";
		$msg .= $site->return_self_closing_tag('input type="hidden" name="module" value="local" maxlength="300"');
		$msg .= '</div>' . "\n";
		
		$msg .= '<div>' . "\n";
		if ($site->force_external_login_when_trying_local_login())
		{
			$msg .= $site->return_self_closing_tag('input type="submit" value="Update"');
		} else
		{
			$msg .= $site->return_self_closing_tag('input type="submit" value="Login"');
		}
		$msg .= '</div>' . "\n";
		$msg .= '</form>' . "\n";
		
		return $msg;
	}
?>

