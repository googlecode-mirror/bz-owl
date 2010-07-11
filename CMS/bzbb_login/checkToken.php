<?php
	// returns TRUE if user is a member of ANY of the specified groups
	// sample reply ... MSG: checktoken callsign=menotume, ip=, token=1279306227  group=gu.league TOKGOOD: menotume:gu.league BZID: 262
	function validate_token ($token, $callsign, $groups=array())
	{
		$list_server='http://my.bzflag.org/db/';
		
		$group_list='&groups=';
		foreach($groups as $group)
		{
			$group_list.="$group%0D%0A";
		}
		
		//Trim the last 6 characters, which are "%0D%0A", off of the last group
		$group_list=substr($group_list, 0, strlen($group_list)-6);
		
		// use cURL to get the remote content
		$ch = curl_init();
		
		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, ($list_server.'?action=CHECKTOKENS&checktokens='.urlencode($callsign).'%3D'.$token.$group_list));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// grab URL
		$reply = curl_exec($ch);
		
		// close cURL resource, and free up system resources
		curl_close($ch);
		
//		echo '<pre>';
//		print_r($reply);
//		echo '</pre>';
		return $reply;
	}
	
	// this returns true if someone is member of ALL groups specified
	function member_of_groups($reply, $callsign, $groups=array())
	{
		// make sure the user is in at least one group
		if (count($groups) < 1)
		{
			// return false to avoid giving permissions that should not be given
			// one does not want to know if a user is in no group so expect a user mistake
			return false;
		}
		$group_count = count($groups);
		
		if ( ($x = strpos($reply, 'TOKGOOD: ' . $callsign)) !== false)
		{
			$group_list = '';
			foreach($groups as $group)
			{
                $group_list .= ':' . $group;
			}
			$groupsearch = substr($reply, $x+8+strlen($callsign));
			$groupsearch = explode(' ', $groupsearch);
			$groupsearch = $groupsearch[0];
			
			foreach($groups as $group)
			{
				$pos = strpos($groupsearch, (':' . $group));
                if (($pos !== false) && ($pos > 0))
				{
					$group_count--;
				}
				
				if ($group_count < 1)
				{
					return true;
				}
			}
		}
		return false;
	}
	
	function login_successful($reply, $callsign)
	{
		if ( ($x = strpos($reply, "TOKGOOD: $callsign")) !== false)
		{
			return true;
		}
		return false;
	}
	
	function bzid($reply, $callsign)
	{
		if ( ( ($x = strpos($reply, "TOKGOOD: $callsign")) !== false) && ($x = strrpos($reply, "BZID: ")) !== false)
		{
			// strrpos with multiple chars as search string requires PHP 5 or later
			$number_of_trimmed_chars_at_end = ((-1) * (strlen($callsign) +1));
			// cast to int to prevent SQL injections
			return (int) (substr($reply, $x+6, $number_of_trimmed_chars_at_end));
		}
		// return -1 on error
		return -1;
	}
?>