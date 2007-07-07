<?php
$relPath="./../../pinc/";
include_once($relPath.'site_vars.php');
include_once($relPath.'dp_main.inc');
include_once($relPath.'project_states.inc');
include_once($relPath.'stages.inc');
include_once($relPath.'Project.inc');
include_once($relPath.'wordcheck_engine.inc');
include_once($relPath.'theme.inc');
include_once('./post_files.inc');
include_once('./word_freq_table.inc');

set_time_limit(0); // no time limit

$projectid = $_REQUEST["projectid"];

enforce_edit_authorization($projectid);

// $format determins what is presented from this page:
//   'html' - page is rendered with frequencies included
//   'file' - all words and frequencies are presented as a
//            downloaded file
// 'update' - update the list
$format = array_get($_REQUEST, "format", "html");

if($format=="update") {
    $postedWords = parse_posted_words($_POST);

    $words = load_project_bad_words($projectid);
    $words = array_merge($words,$postedWords);
    save_project_bad_words($projectid,$words);

    $format="html";
}

$title = _("Candidates for project's Bad Words List from diff analysis");
$page_text = _("Displayed below are the words from this project that are likely scannos based on text comparisons.");
$page_text .= " ";
$page_text .= _("The results list was generated by comparing the raw OCR text and the most recent text of each page. Words that were changed between the two that WordCheck would otherwise not flag, that are not already on the site or project word lists, and still appear in the latest copy of the text are included in the results. The results list also shows the corrected text for that word.");

list($possible_scannos_w_freq,$messages,$possible_scannos,$scanno_count,$percent_changed) = _get_word_list($projectid);

if($format == "file") {
    $filename="${projectid}_project_scannos.txt";
    header("Content-type: text/plain");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // The cache-control and pragma is a hack for IE not accepting filenames
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    echo $title . "\r\n";
    echo sprintf(_("Project: %s"),get_project_name($projectid)) . "\r\n";
    echo "\r\n";
    echo strip_tags($page_text) . "\r\n";
    echo "\r\n";
    echo_page_instruction_text( "bad", $format );
    echo "\r\n";
    echo_download_text( $projectid, $format );
    echo "\r\n";
    echo _("Format: [word] - [% changed] - [# left] - [# changed]") . "\r\n";
    echo "\r\n";

    foreach( $percent_changed as $word => $percentChanged ) {
        $percentChanged = $percent_changed[$word];
        $numChanged = $scanno_count[$word];
        $numLeft = $possible_scannos_w_freq[$word];
        echo "$word - $percentChanged - $numLeft - $numChanged\r\n";
    }

    // we're done here, exit
    exit;
}

$no_stats=1;
theme($title,"header");
echo_page_header($title,$projectid);

// how many instances (ie: frequency sections) are there?
$instances=1;
// what are the cutoff options?
$cutoffOptions = array(1,10,20,30,40,50,60,70,80,90);
// what is the intial cutoff frequecny?
$initialFreq=getInitialCutoff(50,$cutoffOptions,$percent_changed);

// echo page support text, like JS and stylesheets
echo_cutoff_script($cutoffOptions,$instances);

echo_word_freq_style();

echo "<p>$page_text</p>";

echo_page_instruction_text( "bad", $format );

echo_any_warnings_errors( $messages );

echo_download_text( $projectid, $format );

// output customized cutoff text
$cutoff_text = sprintf(_("Words with fewer than <b><span id='current_cutoff'>%d</span>%%</b> of the instances changed are not shown. Other cutoff options are available: %s"),$initialFreq,get_cutoff_string($cutoffOptions,"%"));
echo "<p>$cutoff_text</p>\n";

$project_good_words = load_project_good_words($projectid);

$word_checkbox = build_checkbox_array($percent_changed);

$context_array = build_context_array_links($possible_scannos_w_freq,$projectid);

$word_notes=array();
foreach($possible_scannos_w_freq as $word => $freq) {
   if(in_array($word,$project_good_words))
      $word_notes[$word]=_("On project GWL");
}
$word_notes["[[TITLE]]"]=_("Notes");

$percent_changed["[[TITLE]]"]=_("% Changed");
$percent_changed["[[STYLE]]"]="text-align: right;";
$scanno_count["[[TITLE]]"]=_("# Changed");
$scanno_count["[[STYLE]]"]="text-align: right;";
$context_array["[[TITLE]]"]=_("Show Context");
$possible_scannos["[[TITLE]]"]=_("Corrected Word");
$possible_scannos["[[CLASS]]"]="mono";
$possible_scannos_w_freq["[[TITLE]]"]=_("# Left");
$possible_scannos_w_freq["[[STYLE]]"]="text-align: right;";

prep_numeric_keys_for_multisort( $possible_scannos_w_freq );
prep_numeric_keys_for_multisort( $scanno_count );
prep_numeric_keys_for_multisort( $context_array );
prep_numeric_keys_for_multisort( $possible_scannos );
prep_numeric_keys_for_multisort( $word_notes );

echo_checkbox_selects(count($percent_changed));

$checkbox_form["projectid"]=$projectid;
$checkbox_form["freqCutoff"]=$freqCutoff;
echo_checkbox_form_start($checkbox_form);
echo_checkbox_form_submit(_("Add selected words to Bad Words List"));

printTableFrequencies($initialFreq,$cutoffOptions,$percent_changed,$instances--,array($possible_scannos_w_freq,$scanno_count,$context_array,$possible_scannos,$word_notes), $word_checkbox);

echo_checkbox_form_submit(_("Add selected words to Bad Words List"));
echo_checkbox_form_end();

theme('','footer');


//---------------------------------------------------------------------------
// supporting page functions

function _get_word_list($projectid) {
    global $aspell_temp_dir;
    global $word_pattern;

    $ocr_filename = "$aspell_temp_dir/${projectid}_ocr.txt";
    $latest_filename = "$aspell_temp_dir/${projectid}_latest.txt";

    $messages = array();

    // get the OCR text
    $pages_res = page_info_query($projectid,'[OCR]','LE');
    $all_page_text = get_page_texts( $pages_res );
    $all_page_text = preg_replace("/<[\/]*\w+>/","",$all_page_text);
    file_put_contents($ocr_filename, $all_page_text);

    // get the latest project text of all pages up to last possible round
    $last_possible_round = get_Round_for_round_number(MAX_NUM_PAGE_EDITING_ROUNDS);
    $pages_res = page_info_query($projectid,$last_possible_round->id,'LE');
    $all_page_text = get_page_texts( $pages_res );
    $all_page_text = preg_replace("/<[\/]*\w+>/","",$all_page_text);
    file_put_contents($latest_filename, $all_page_text);

    $all_words_w_freq = get_distinct_words_in_text( $all_page_text );
    // clean up unused variables
    unset($all_page_text);

    $possible_scannos_w_correction = array();

    $wdiff_output = `wdiff -3 $ocr_filename $latest_filename`;
    $separater = '======================================================================';
    $wdiff_segments = explode($separater,$wdiff_output);

    // clean up the temporary files
    if(is_file($ocr_filename)) {
        unlink($ocr_filename);
    }
    if(is_file($latest_filename)) {
        unlink($latest_filename);
    }

    // process wdiff output
    foreach ($wdiff_segments as $segment) {
        // note that we're handling the case where two adjacent
        // words are updated
        $ocr_words=$latest_words=array();

        // pull out the original word(s)
        if(preg_match("/\[-(.*?)-\]/",$segment,$matches)) {
            $ocr_words = $matches[1];
            $ocr_words=get_all_words_in_text($ocr_words);
        }

        // if we don't have any ocr_words (probably because
        // the correction spanned lines) then don't bother
        // continuing with this segment
        if(!count($ocr_words)) continue;

        // pull out the replacement(s)
        if(preg_match("/{\+(.*?)\+}/",$segment,$matches)) {
            $latest_words = $matches[1];
            $latest_words=get_all_words_in_text($latest_words);
        }

        // if the number of words isn't the same between the two
        // bail since we don't handle that case yet
        if(count($ocr_words) != count($latest_words)) continue;

        // process the words, handles multi-words strings
        for($index=0; $index<count($ocr_words); $index++) {
            $ocr_word=$ocr_words[$index];
            $latest_word=$latest_words[$index];

            // if the words are the same or one of them empty, skip it
            if(($ocr_word == $latest_word) || empty($ocr_word) || empty($latest_word)) continue;

            $possible_scannos_w_correction[$ocr_word]=$latest_word;
            @$possible_scannos_w_count[$ocr_word]++;
        }
    }

    $possible_scannos = array_keys($possible_scannos_w_correction);

    // create a string of words to run through WordCheck
    $text_to_check = implode(" ",$possible_scannos);

    // run the list through WordCheck to see which it would flag
    list($possible_scannos_via_wordcheck,$languages,$messages) =
        get_bad_words_for_text($text_to_check,$projectid,'all','',array(),'FREQS');

    // load site words
    $site_bad_words = load_site_bad_words_given_project($projectid);

    // load the project bad words
    $project_bad_words = load_project_bad_words($projectid);

    // remove words that WordCheck would flag
    $possible_scannos = array_diff($possible_scannos, array_keys($possible_scannos_via_wordcheck));

    // remove any scannos already on the site and project bad word lists
    $possible_scannos = array_diff($possible_scannos, $site_bad_words, $project_bad_words);

    // $possible_scannos doesn't have frequency info, 
    // so start with the info in $all_words_w_freq,
    // and extract the items where the key matches a key in $possible_scannos
    $possible_scannos_w_freq = array_intersect_key( $all_words_w_freq, array_flip($possible_scannos));

    foreach($possible_scannos_w_count as $word => $count) {
        $totalInstances=$possible_scannos_w_freq[$word]+$count;
        $percent_changed[$word]=sprintf("%0.2f",$count/$totalInstances*100);
        if($percent_changed[$word]>=100) {
            unset($percent_changed[$word]);
        }
    }

    // multisort screws up all-numeric words so we need to preprocess first
    prep_numeric_keys_for_multisort( $percent_changed );

    // sort the list by frequency, then by word
    array_multisort( array_values( $percent_changed ), SORT_DESC, array_map( 'strtolower', array_keys( $percent_changed )), SORT_ASC, $percent_changed);

    return array($possible_scannos_w_freq,$messages,$possible_scannos_w_correction,$possible_scannos_w_count,$percent_changed);
}

// vim: sw=4 ts=4 expandtab
?>
