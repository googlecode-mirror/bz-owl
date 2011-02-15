<?php
	
	function settings()
	{
		return array(
					 'dbHost' => '127.0.0.1',
					 'dbUser' => 'insert database user here',
					 'dbPw' => 'insert database password here',
					 'dbName' => 'ts-CMS',
					 // nl2br needs php newer or equal to 4.0.5 to support xhtml
					 // if php version is higher or equal to 4.0.5 but lower than 5.3
					 // then xhtml will be always on
					 // if php version is lower than 4.0.5 then xhtml will be always off
					 // see http://www.php.net/manual/en/function.nl2br.php
					 'useXhtml' => true,
					 // either return an empty string or path to favicon
					 'favicon' => '',
					 // set the timezone used
					 // look at http://www.php.net/manual/en/timezones.php
					 // for a complete list of supported timezones
					 'timezone' => 'UTC',
					 'debugSQL' => true,
					 'domain' => 'example.com',
					 'basepath' => '/',
					 'baseaddress' => 'http://example.com/',
					 'forceExternalLoginOnly' => true,
					 'convertUsersToExternalLogin' => true,
					 'bbcodeLibAvailable' => true,
					 'displayedSystemUsername' => 'CTF League System',
					 // the name displayed in mails sent by the system
					 'oldWebsiteName' => 'gu.bzleague.com',
					 'bbcodeLibPath' => (dirname(__FILE__)) . '/nbbc-wrapper.php',
					 'bbcodeClass' => 'wrapper',
					 'bbcodeSetsLinebreaks' => true,
					 'bbcodeCommand' => 'Parse',
					 'timezone' => 'CET',
					 'test' => true
					 );
	}
	
	function database_to_be_imported()
	{
		return 'bzleague_guleague';
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