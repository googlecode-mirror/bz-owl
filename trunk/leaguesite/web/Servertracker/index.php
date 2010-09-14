<?php
	ini_set ('session.use_trans_sid', 0);
	ini_set ('session.name', 'SID');
	ini_set ('allow_url_fopen', 0);
	ini_set('session.gc_maxlifetime', '7200');
	session_start();
	
//	if (!(isset($_SESSION['IsGoodVisitor'])) || !($_SESSION['IsGoodVisitor']))
//	{
//		// IP bestimmen
//		$ip = getenv('REMOTE_ADDR');
//		
//		// IP aufloesen und Host bestimmen
//		$host = gethostbyaddr($ip);
//		//$host = 'blablub.versanet.de';
//		if ((! preg_match("/.(arcor-ip.net|versanet.de)/", $host))
//			&& (! preg_match("/|192.168.(0|1|2|3|4|5|6|7|8|9).(0|1|2|3|4|5|6|7|8|9)/", $ip)))
//		{
//			die('Kein Zutritt!');
//		}
//	}
	require_once '../CMS/siteinfo.php';
	$site = new siteinfo();
	
	if ($site->use_xtml())
	{
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"' . "\n";
		echo '     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
	} else
	{
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"' . "\n";
		echo '        "http://www.w3.org/TR/html4/strict.dtd">';
	}
	echo "\n" . '<html';
	if ($site->use_xtml())
	{
		echo ' xmlns="http://www.w3.org/1999/xhtml"';
	}
	echo '>' . "\n";
	echo '<head>' . "\n";
	echo '	' . ($site->write_self_closing_tag('meta content="text/html; charset=utf-8" http-equiv="content-type"'));
	
	require '../stylesheet.inc';
	
	$site->write_self_closing_tag('link rel="stylesheet" media="all" href="players.css" type="text/css"');
	// perhaps exclude email string, depending on browser
	$object = new siteinfo();
	if ($object->mobile_version())
	{
		// mobile browser
		echo '<style type="text/css">*.mehl { display: none; } table.punkte { left: 25em; }</style>';
	}
	echo '  <title>Official match servers</title>' . "\n";
	echo '</head>' . "\n";
	echo '<body>' . "\n";
	
	require '../CMS/navi.inc';
    
	echo '<div class="static_page_box">' . "\n";
	if (!($logged_in && (isset($_SESSION['allow_watch_servertracker'])) && ($_SESSION['allow_watch_servertracker'])))
	{
		echo '<p>You have no permission to view this page.</p>' . "\n";
		$site->dieAndEndPage();
	}
	$use_internal_db = true;
	
	require 'list.php';
	//formatbzfquery("bzflagr.net:5154");
	//echo '<hr>' . "\n";
	
	$connection = $object->loudless_pconnect_to_db();
	
	if (isset($_GET['server']))
	{
		echo '<a class="button" href="./">overview</a>' . "\n";
		$server = urldecode($_GET['server']);
		formatbzfquery_last($server, $connection);
	} else
	{
		formatbzfquery("bzf.guleague.org:5154", $connection);
		
		formatbzfquery("bzf.guleague.org:5155", $connection);
		
		formatbzfquery("bzf.guleague.org:5156", $connection);
		
		formatbzfquery("bzf.guleague.org:5160", $connection);
		
		formatbzfquery("bzf.guleague.org:5161", $connection);
		
		formatbzfquery("bzf.guleague.org:5157", $connection);
		
		formatbzfquery("bzf.guleague.org:5158", $connection);
		
		formatbzfquery("brad.guleague.org:5158", $connection);
		
//		formatbzfquery("longdon.guleague.org:5158", $connection);
//      
		formatbzfquery("destroyer.guleague.org:5157", $connection);
		
        formatbzfquery("destroyer.guleague.org:5158", $connection);
		
		formatbzfquery("dub.guleague.org:59997", $connection);
		
		formatbzfquery("trb.guleague.org:5158", $connection);
		
		formatbzfquery("brl.arpa.net:5158", $connection);
//		
//		formatbzfquery("fairserve.bzflag.net:5157", $connection);
//		
//		formatbzfquery("fairserve.bzflag.net:5158", $connection);
//		
		formatbzfquery("quol.guleague.org:5157", $connection);
		
		formatbzfquery("quol.guleague.org:5158", $connection);
		
		formatbzfquery_last("bzflag.enuffsaid.co.nz:5158", $connection);
	}
?>

</div>
</div>
</body>
</html><?php
	// GU-spezifisch!
	//require_once '../../../CMS/siteinfo.php';
	//
	//$object = new siteinfo();
	@mysql_close($connection);
	unset($connection);
	$connection = $object->loudless_connect_to_db();
	if (!$connection)
	{
		// HTML oben schon beendet
		die('no connection to database');
	}
	
	if (!$use_internal_db)
	{
		// only execute once per day to avoid overhead
		date_default_timezone_set($site->used_timezone());
		
		$heute = date("d.m.y");
		$datei = 'maintenance.txt';
		
		if (is_writable($datei)) {
			
			// Wir öffnen $filename im "Anhänge" - Modus.
			// Der Dateizeiger befindet sich am Ende der Datei, und
			// dort wird $somecontent später mit fwrite() geschrieben.
			if (!$handle = fopen($datei, "r")) {
				print "Kann die Datei $datei nicht öffnen";
				exit;
			}
		} else {
			print "Die Datei $datei ist nicht schreibbar";
		}
		
		// lese die ersten 10 Zeichen
		$text = fread($handle, 10);
		
		// Ist DB auf dem Stand von heute?
		if (strcasecmp($text, $heute) == 0)
		{
			// Nichts zu tun
			die();
		}
		
		// Datenbank auswaehlen
		mysql_select_db("playerlist", $connection);
		
		// Daten loeschen, wenn nicht mehr aktuell
		// teure Operation
		$query = 'TRUNCATE teams';
		$result = mysql_query($query, $connection);
		if (!$result)
		{
			print mysql_error();
			die("<br>\nQuery $query ist ungültiges SQL.");
		}
		$query = 'TRUNCATE players';
		$result = mysql_query($query, $connection);
		if (!$result)
		{
			print mysql_error();
			die("<br>\nQuery $query ist ungültiges SQL.");
		}
		
		$row = 1; // Anzahl der Felder
		// create a new cURL resource
		$ch = curl_init();
		
		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, 'http://gu.bzleague.com/rss/export2.php');
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// grab URL and pass it to the browser
		$output = curl_exec($ch);
		
		// close cURL resource, and free up system resources
		curl_close($ch);
		$handleRSS = $output;
		
		//	$handleRSS = fopen ("http://gu.bzleague.com/rss/export2.php","r"); // Datei zum Lesen oeffnen
		//	if (!$handle)
		//	{
		//		die('Could not connect to league website');
		//	}
		
		preg_match_all('/(TE:(.*))|(PL:(.*))/', $handleRSS, $handleRSS);
		$handleRSS = $handleRSS[0];
		//    $handleRSS = str_getcsv ($handleRSS, "\n");
		//    print_r($handleRSS);
		
		//	while ( ($data = str_getcsv ($handleRSS, ",")) !== FALSE )
		foreach ($handleRSS as $dataRow)
		{ // Daten werden aus der Datei in ein Feld $data gelesen
			
			//        preg_match_all('/((.*),(.*))|((.*),(.*),(.*))/' , $dataRow, $data);
			$data = explode(',', $dataRow, 4);
			//        $data = $data[0];
			//        $data = str_getcsv ($dataRow, ',');
			
			
			
			$num = count ($data); // Felder im Array $data werden gezaehlt
			$row++; // Anzahl der Arrays wird inkrementiert
			
			if ($num == 2)
			{
				$data[1] = ltrim($data[1]);
			}
			if ($num > 2)
			{
				$data[2] = ltrim($data[2]);
			}
			//		print_r($data);
			
			// teams
			if ($num == 2)
			{
				// mysql_real_escape_string zerschießt " in /"
				$teamid = mysql_real_escape_string((int) (str_replace('TE: ', '', $data[0])));
				
				$name = '"' . mysql_real_escape_string(htmlentities($data[1])) . '"';
				
				$query = 'INSERT INTO teams (teamid, name) Values(' . $teamid . ',' . $name . ')';
				$result = mysql_query($query, $connection);
				
				if (!$result)
				{
					print mysql_error();
					die("<br>\nQuery $query ist ung&uuml;ltiges SQL.");
				}
			}
			
			// players
			if ($num == 3)
			{
				$teamid = '"' . mysql_real_escape_string((int) (str_replace('PL: ', '', $data[0]))) . '"';
				
				$name = '"' . mysql_real_escape_string(htmlentities($data[2])) . '"';
				
				$query = 'INSERT INTO players (teamid, name) Values(' . $teamid . ', ' . $name . ')';
				$result = mysql_query($query, $connection);
				
				if (!$result)
				{
					print mysql_error();
					die("<br>\nQuery $query ist ung&uuml;ltiges SQL.");
				}
			}
		}
		
		if (!(strcasecmp($text, $heute) == 0))
		{
			// Inhalt leeren
			if (!fclose($handle)) {
				print "Kann die Datei $datei nicht schlie&szlig;en";
				exit;
			}
			if (!$handle = fopen($datei, 'w')) {
				print "Kann die Datei $datei nicht &ouml;ffnen";
				exit;
			}
			if (!fwrite($handle, $heute)) {
				print "Kann in die Datei $datei nicht schreiben";
				exit;
			}
			@fclose($handle);
		}
	}
	//	fclose ($handleRSS);
?>