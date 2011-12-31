<?php
$relPath="./../pinc/";
include_once($relPath.'site_vars.php');
include_once($relPath.'misc.inc');
include_once($relPath.'xml.inc');
include_once($relPath.'gettext_setup.inc');

$content = get_enumerated_param($_GET, 'content', 'posted', array('posted', 'postprocessing', 'proofing', 'news')); // Which feed the user wants
$refreshDelay = 30 * 60; // Time in seconds for how often the feeds get refreshed
$refreshAge = time()-$refreshDelay; // How long ago $refreshDelay was in UNIX time

// Determine if we should display a 0.91 compliant RSS feed or our own feed
if (isset($_GET['type'])) {
    $xmlfile = "$xmlfeeds_dir/${content}_rss.$intlang.xml";
} else {
    $xmlfile = "$xmlfeeds_dir/${content}.$intlang.xml";
}

// If the file does not exist or is stale, let's (re)create it
if(!file_exists($xmlfile) || filemtime($xmlfile) < $refreshAge) {
    $relPath="./../pinc/";
    include($relPath.'site_vars.php');
    include($relPath.'pg.inc');
    include($relPath.'connect.inc');
    include($relPath.'project_states.inc');
    $db_Connection=new dbConnect();

    if ($content == "posted" || $content == "postprocessing" || $content == "proofing") {
        switch($content) {
            case "posted":
                $state=PROJ_SUBMIT_PG_POSTED;
                $x="g";
                break;
            case "postprocessing":
                $state=PROJ_POST_FIRST_AVAILABLE;
                $x="s";
                break;
            case "proofing":
                $state=PROJ_P1_AVAILABLE;
                $x="b";
                break;
        }
        $data = '';
        $result = mysql_query("SELECT * FROM projects WHERE state='$state' ORDER BY modifieddate DESC LIMIT 10");
        while ($row = mysql_fetch_array($result)) {
            $posteddate = date("r",($row['modifieddate']));
            if (isset($_GET['type'])) {
                $data .= "<item>
                <title>".xmlencode($row['nameofwork'])." - ".xmlencode($row['authorsname'])."</title>
                <link>$code_url/project.php?id=".$row['projectid']."</link>
                <description>" . sprintf(_("Language: %1\$s - Genre: %2\$s"), xmlencode($row['language']), xmlencode($row['genre'])) . "</description>
                </item>
                ";
            } else {
                $data .= "<project id=\"".$row['projectid']."\">
                <nameofwork>".xmlencode($row['nameofwork'])."</nameofwork>
                <authorsname>".xmlencode($row['authorsname'])."</authorsname>
                <language>".xmlencode($row['language'])."</language>
                <posteddate>".$posteddate."</posteddate>
                <genre>".xmlencode($row['genre'])."</genre>
                <links>
                <PG_catalog>".get_pg_catalog_url_for_etext($row['postednum'])."</PG_catalog>
                <library>$code_url/project.php?id=".$row['projectid']."</library>
                </links>
                </project>
                ";
            }
        }

        $lastupdated = date("r");
        if (isset($_GET['type'])) {
            $xmlpage = "<"."?"."xml version=\"1.0\" encoding=\"$charset\" ?".">
                <!DOCTYPE rss SYSTEM \"http://my.netscape.com/publish/formats/rss-0.91.dtd\">
                <rss version=\"0.91\">
                <channel>
                <title>".xmlencode($site_name)." - " . _("Latest Releases") . "</title>
                <link>".xmlencode($code_url)."</link>
                <description>" . sprintf( _("The latest releases posted to Project Gutenberg from %1\$s."), xmlencode($site_name)) . "</description>
                <language>" . $intlang /* from gettext_setup.inc */ . "</language>
                <webMaster>".xmlencode($site_manager_email_addr)."</webMaster>
                <pubDate>".xmlencode($lastupdated)."</pubDate>
                <lastBuildDate>".xmlencode($lastupdated)."</lastBuildDate>
                $data
                </channel>
                </rss>";
        } else {
            $xmlpage = "<"."?"."xml version=\"1.0\" encoding=\"$charset\" ?".">
                <!-- Last Updated: $lastupdated -->
                <projects xmlns:xsi=\"http://www.w3.org/2000/10/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"projects.xsd\">
                $data
                </projects>";
        }
    }

    if ($content == "news") {
        $data = '';
        $result = mysql_query("SELECT * FROM news_items ORDER BY date_posted DESC LIMIT 10");
        while ($news_item = mysql_fetch_array($result)) {
            $posteddate = date("l, F jS, Y",($news_item['date_posted']));
            $data .= "<item>
                <title>" . sprintf( _("News Update for %1\$s."), xmlencode($posteddate)) . "</title>
    <description>" . sprintf( _("The latest news related to %1\$s."), xmlencode($site_name)) . "</description>
                <link>".xmlencode("$code_url/pastnews.php?#".$news_item['id'])."</link>
                <description>".xmlencode(strip_tags($news_item['content']))."</description>
                </item>
                ";
        }
        $lastupdated = date("r");
        $xmlpage = "<"."?"."xml version=\"1.0\" encoding=\"$charset\" ?".">
                <!DOCTYPE rss SYSTEM \"http://my.netscape.com/publish/formats/rss-0.91.dtd\">
                <rss version=\"0.91\">
                <channel>
                <title>".xmlencode($site_name) . " - " . _("Latest News") . "</title>
                <link>".xmlencode($code_url)."</link>
                <description>" . sprintf( _("The latest news related to %1\$s."), xmlencode($site_name)) . "</description>
                <language>en-us</language>
                <webMaster>".xmlencode($site_manager_email_addr)."</webMaster>
                <pubDate>".xmlencode($lastupdated)."</pubDate>
                <lastBuildDate>".xmlencode($lastupdated)."</lastBuildDate>
                $data
                </channel>
                </rss>";
    }

    $file = fopen($xmlfile,"w");
    fwrite($file,$xmlpage);
    $file = fclose($file);
}

// If we're here, the file exists and is fresh, output it

$fileModifiedTime=filemtime($xmlfile);
$secondsOfFreshnessRemaining=$fileModifiedTime + $refreshDelay - time();

// Let the browser cache it until the local cache becomes stale
header("Content-Type: text/xml");
header("Expires: " . gmdate("D, d M Y H:i:s",$fileModifiedTime + $refreshDelay) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $fileModifiedTime) . " GMT");
header("Cache-Control: max-age=$secondsOfFreshnessRemaining, public, must-revalidate");

readfile($xmlfile);

// vim: sw=4 ts=4 expandtab
