<?php
ini_set ('session.use_trans_sid', 0);
ini_set ('session.name', 'SID');
session_start();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
<?php
require '../../stylesheet.inc';
	$pfad = (pathinfo(realpath('../')));
	$name = $pfad['basename'];
	if (strcmp($name, 'leaguesite') == 0)
	{
		$name = 'Home';
	}
	print '  <title>' . $name . ' editor</title>' . "\n";
?>
</head>
<body>
<?php
require_once ('../../CMS/siteinfo.php');
require '../../CMS/navi.inc';

$site = new siteinfo();

if ($site->use_mysql_news())
{
    $link = @mysql_connect('localhost', $site->mysqluser, $site->mysqlpw);
    if (!$link) {
        echo('Keine Verbindung zum SQL-server m&ouml;glich: ' . mysql_error())."<br>\n";
        //die('Keine Verbindung zum SQL-server m&ouml;glich: ' . mysql_error());
    }
    //Verbindung erfolgreich
    @mysql_close($link);
}
?>

<?php
function errormsg (){
    echo "You don't have the appropriate password for that user or that user doesn't exist.<br>\n";
}

$previewSeen=$_POST["preview"];
$content=$_POST["News"];

if (!isset($_SESSION['IsAdmin']) || !$_SESSION['IsAdmin'])
{
    die(errormsg());
}

echo '<p><a href="../">[original]</a>' . '</p>' ."\n";

function readNews (){
    $handle = @fopen ('news.txt', 'r');
    if ( $handle ){
        while (!feof($handle)){
            $buffer .= fgets($handle, 4096);
        }
        fclose($handle);
        return $buffer;
    }
    else{
       print "Failed to open news archive.<br>\n";
    }
}

function file_putContents ($fileName, $contents){
  if ( ($fp = fopen ($fileName, "w")) === false)
    return null;
  $result = fwrite($fp, $contents);
  fclose($fp);
  return $result;
}


$content = str_replace('\"', "\x22", $content);
$content = str_replace("\'", "\x27", $content);
$content = str_replace('&lt;', '<', $content);
$content = str_replace('&gt;', '>', $content);
$content = str_replace("<rglnk>", "\n", $content);
$content = str_replace('<and>', '"', $content);


if ($previewSeen==2)
{
    if ((@file_putContents("news.txt", $content))===(strlen($content)))
        echo "Updating: No problems occured, changes written!<br><br>\n";
    else
        echo "Updating: An error occurred while writing the changes, news may be now completly broken.<br><br>\n";
    $buffer = $content;
} else
    $buffer = readNews ();

	echo '<form action="./" method="post">' . "\n";
	if (($previewSeen==1) && ($previewSeen!=2)){
		echo '<p>Vorschau:</p>' . "\n";
		echo $content;
		$content = str_replace("\n", '<rglnk>', $content);
		$content = str_replace('"', '<and>', $content);
		$content = str_replace("\r", '', $content);
		$content = str_replace('<', '&lt;', $content);
		$content = str_replace('>', '&gt;', $content);
		echo "<input type=\x22hidden\x22 name=\x22News\x22 value=\x22" . $content . "\x22><br>\n";
		echo "<input type=\x22hidden\x22 name=\x22preview\x22 value=\x22" . '2' . "\x22><br>\n";
		echo "<input type=\x22submit\x22 value=\x22" . '&Auml;nderungen best&auml;tigen' . "\x22>\n";
	} else{
		echo "Put the articles in p-tags and headlines into h1-tags to get their style being applied.<br>\n";
		echo "Keep in mind the home page currently uses HTML, not XHTML.<br>\n";
		echo "<textarea cols=\x2275\x22 rows=\x2220\x22 name=\x22News\x22>$buffer</textarea><br>\n";
		echo "<input type=\x22hidden\x22 name=\x22preview\x22 value=\x22" . '1' . "\x22><br>\n";
		echo "<input type=\x22submit\x22 value=\x22" . 'Vorschau' . "\x22>\n";
	}
	echo "</form>\n";
?>
</div>
</body>
</html>