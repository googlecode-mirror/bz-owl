<?php
ini_set ('session.use_trans_sid', 1);
ini_set ('session.name', 'SID');
session_start();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
<?php
require '../stylesheet.inc';
?>
  <link href="../news.css" rel="stylesheet" type="text/css">
 <title>SilverCat News Editor</title></head>
<body>
<div class="content">
<?php
require_once ('../CMS/siteinfo.php');
require '../CMS/navi.inc';

function errormsg (){
    echo "You don't have the appropriate password for that user or that user doesn't exist.<br>\n";
}

$previewSeen=$_POST["preview"];
$content=$_POST["News"];

if (!$_SESSION['IsAdmin'])
{
    die(errormsg());
}

echo '<p><a href="../">[Original]</a>' . '</p>' ."\n";

?>

<?php

$site = new siteinfo();

$connection = $site->connect_to_db();
if (!$connection)
{
    print mysql_error();
    die("Query $query ist ungŸltiges SQL.");
}
// Verbindung erfolgreich

// Datenbank auswaehlen
mysql_select_db("ts-CMS", $connection);

// INSERT INTO news (head, body)  VALUES (900, '10.Jan.1999')
// DELETE FROM `news` WHERE `id` = '3'

$result = mysql_query("select * from news ORDER BY id", $connection);
if (!$result)
{
    print mysql_error();
    die("Query $query ist ungŸltiges SQL.");
}

$zeilen = mysql_num_rows($result);

while($row = mysql_fetch_array($result))
{    
    echo '<div class="article">' . "\n";
    printf("<h1>%s</h1>\n", $row["head"]) . "<br>";
    echo '<p class="article">';
    printf("%s<br>\n</p></div>\n", $row["body"]);
    echo '<br<br>' . "\n";
}

mysql_free_result($result);
mysql_close($connection);
?>
test
<?php

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

//\x22 == "
echo "<form action=\x22" . baseaddress() . 'Nachrichten/' . "\x22 method=\x22post\x22>\n";
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
    echo "<input type=\x22hidden\x22 name=\x22preview\x22 value=\x221\x22><br>\n";
    echo "<input type=\x22submit\x22 value=\x22" . 'Vorschau' . "\x22>\n";
}
echo "</form>\n";
?>
</div>
</body>
</html>