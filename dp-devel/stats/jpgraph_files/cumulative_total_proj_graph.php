<?
$relPath="./../../pinc/";
include_once($relPath.'v_site.inc');
include_once($relPath.'connect.inc');
include_once($code_dir.'/stats/statestats.inc');
include_once($relPath.'gettext_setup.inc');
include_once('common.inc');

new dbConnect();

// Create "cumulative projects Xed per day" graph for all days 
// since state stats started being recorded up to yesterday

$psd = get_project_status_descriptor( $_GET['which'] );

$timeframe = _('since stats began');

//query db and put results into arrays
$result = mysql_query("
	SELECT date, SUM(num_projects) AS P
	FROM project_state_stats
	WHERE $psd->state_selector
	GROUP BY date
	ORDER BY date ASC
");

$i = 0;


while ($row = mysql_fetch_assoc($result)) {
	$datay1[$i] = $row['P'];
	$datax[$i] = $row['date'];
	$i++;
}

if (empty($datay1)) {
	$datay1[0] = 0;
}

draw_projects_graph(
	$datax,
	$datay1,
	'cumulative',
	$psd->color,
	"$psd->cumulative_title ($timeframe)",
	360
);

?>
