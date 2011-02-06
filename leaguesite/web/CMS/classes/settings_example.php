<?php
	
	function settings()
	{
		return array(
					 'dbHost' => '127.0.0.1',
					 'dbUser' => 'insert database user here',
					 'dbPw' => 'insert database password here',
					 'dbName' => 'ts-CMS',
					 'useXhtml' => true,
					 // either return an empty string or path to favicon
					 'favicon' => '',
					 'timezone' => 'CET',
					 'debugSQL' => true,
					 'domain' => domain(),
					 'basepath' => basepath(),
					 'baseaddress' => baseaddress(),
					 'forceExternalLoginOnly' => true,
					 'convertUsersToExternalLogin' => true,
					 'timezone' => 'CET'
					 );
	}
	
    function domain()
	{
		return '127.0.0.1';
	}
	
	function basepath()
	{
		return '/~user/';
	}
	
	function baseaddress()
	{
		return 'http://' . domain() . basepath();
	}
	
	function database_to_be_imported()
	{
		return 'bzleague_guleague';
	}
	function old_website_name()
	{
		return 'gu.bzleague.com';
	}
	
	// make posts anonymous
	function force_username(&$section)
	{
		if (strcmp($section, 'bans') === 0)
		{
			return 'GU League Council';
		} else
		{
			return '';
		}
	}
	
	// the name displayed in mails sent by the system
	function system_username()
	{
		return 'CTF League System';
	}
	
	function timezone()
	{
		// set the timezone used
		// values like Europe/Berlin or UTC are accepted
		// look at http://www.php.net/manual/en/timezones.php
		// for a complete list of supported timezones
		return 'Europe/Berlin';
	}
	
    function xhtml_on()
    {
		// nl2br needs php newer or equal to 4.0.5 to support xhtml
		// if php version is higher or equal to 4.0.5 but lower than 5.3
		// then xhtml will be always on
		// if php version is lower than 4.0.5 then xhtml will be always off
		// see http://www.php.net/manual/en/function.nl2br.php
		return true;
    }
    
	function bbcode_lib_path()
	{
		return ((dirname(__FILE__)) . '/nbbc-wrapper.php');
	}
	
	function bbcode_class()
	{
		return 'wrapper';
	}
	
	function bbcode_sets_linebreaks()
	{
		return true;
	}
	
	function bbcode_command()
	{
		return 'Parse';
	}
	
	function maintain_inactive_teams()
	{
		// remove or deactivate teams that do not match anymore?
		return true;
	}
	
	function maintain_inactive_teams_with_active_players()
	{
		// players from inactive team do login but they do not match
		// remove or deactive the teams with active players?
		return false;
	}
?>