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
	?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
<?php
	require '../stylesheet.inc';
	
	echo '  <link rel="stylesheet" media="all" href="spieler.css" type="text/css">' . "\n";
	// mehl eventuell ausblenden
	$objekt = new siteinfo();
	if ($objekt->mobile_version())
	{
		// mobiler brauser
		echo '<style type="text/css">*.mehl { display: none; } table.punkte { left: 25em; }</style>';
	}
	echo '  <title>BZFlag Ligen</title>' . "\n";
	echo '</head>' . "\n";
	echo '<body>' . "\n";
	
	require '../CMS/navi.inc';
    
	require 'list.php';
	//formatbzfquery("bzflagr.net:5154");
	//echo '<hr>' . "\n";
	
	$connection = $objekt->loudless_pconnect_to_db();
	
	if (isset($_GET['server']))
	{
		echo '<a href=' . "\x22" . './' . "\x22" . '>&Uuml;bersicht</a>' . "\n";
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
//		
//		formatbzfquery("longdon.guleague.org:5158", $connection);
//        
//        formatbzfquery("destroyer.guleague.org:5158", $connection);
		
		formatbzfquery("dub.guleague.org:59997", $connection);
		
		formatbzfquery("trb.guleague.org:5158", $connection);
		
		formatbzfquery("fairserve.bzflag.net:5157", $connection);
		
		formatbzfquery("fairserve.bzflag.net:5158", $connection);
		
		formatbzfquery("quol.guleague.org:5157", $connection);
		
		formatbzfquery("quol.guleague.org:5158", $connection);
		
		formatbzfquery_last("bzflag.enuffsaid.co.nz:5158", $connection);
	}
?>
</div>
</html><?php
	// GU-spezifisch!
	//require_once '../../../CMS/siteinfo.php';
	//
	//$objekt = new siteinfo();
	@mysql_close($connection);
	unset($connection);
	$connection = $objekt->loudless_connect_to_db();
	if (!$connection)
	{
		// HTML oben schon beendet
		die('no connection to database');
	}
	
	// Jeden Tag nur ein Mal ausfuehren, um Kosten zu sparen
	
	date_default_timezone_set('Europe/Berlin');
	
	$heute = date("d.m.y");
	$datei = 'maintenance.txt';
	
	if (is_writable($datei)) {
		
		// Wir šffnen $filename im "AnhŠnge" - Modus.
		// Der Dateizeiger befindet sich am Ende der Datei, und
		// dort wird $somecontent spŠter mit fwrite() geschrieben.
		if (!$handle = fopen($datei, "r")) {
			print "Kann die Datei $datei nicht šffnen";
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
		die("<br>\nQuery $query ist ungŸltiges SQL.");
	}
	$query = 'TRUNCATE players';
	$result = mysql_query($query, $connection);
	if (!$result)
	{
		print mysql_error();
		die("<br>\nQuery $query ist ungŸltiges SQL.");
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
			// mysql_real_escape_string zerschie§t " in /"
			$teamid = mysql_real_escape_string((int) (str_replace('TE: ', '', $data[0])));
			
			$name = "\x22" . mysql_real_escape_string(htmlentities($data[1])) . "\x22";
			
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
			// mysql_real_escape_string zerschie§t " in /"
			$teamid = "\x22" . mysql_real_escape_string((int) (str_replace('PL: ', '', $data[0]))) . "\x22";
			
			$name = "\x22" . mysql_real_escape_string(htmlentities($data[2])) . "\x22";
			
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
//	fclose ($handleRSS);
?>