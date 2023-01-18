<?php

header('Content-Type: application/json; charset=utf-8');


$DEBUG = true;

// Ensure that the user is specified
if(!isset($_GET["user"]))
	exit -1;


include('simple_html_dom.php');

// Remove all encoding that starts with "UTF_8"
$encodings = preg_grep('/^UTF_8/', mb_list_encodings(), PREG_GREP_INVERT);


$html = new simple_html_dom();
$html = load_file("https://scholar.google.fr/citations?hl=en&user=" . $_GET["user"],$encodings);

// Exit if there is an error
if(is_null($html))
	exit -1;

// Get the user information
print "{\n \"total_citations\": " . $html->find("#gsc_rsb_st td.gsc_rsb_std", 0)->plaintext . ",\n";

$str = " \"citations_per_year\": { ";
$years = $html->find('.gsc_g_t');
$scores = $html->find('.gsc_g_al');
foreach($scores as $key => $score) {
	$str .= "\n  \"" . trim($years[$key]->plaintext) ."\": ". trim($score->plaintext) . ",";
}
$str = substr($str, 0, -1) . "\n },\n";

// Get the publications information
$str .= getJsonAllPublications($html, $encodings);
print $str;


/**
 * Function that returns the json of all the publications
 * 
 * @param $html The html of the page that contains all the publications.
 * @param $encodings Encodings from which we try to convert content to UTF8.
 * 
 * @return string The json of all the publications or empty string if there is a problem.
 */
function getJsonAllPublications($html, $encodings): string
{
	if (!isset($html)) {
		return "";
	}

	$str = " \"publications\": [";

	foreach($html->find("#gsc_a_t .gsc_a_tr") as $pub) {
		$str .= getJsonPublication($pub, $encodings);
	}

	//If there is at least one char, remove the last comma
	if (strlen($str) > 1) {
		$str = substr($str, 0, -1);
	}

	$str .= "\n ]\n}";

	return $str;
}


/**
 * Function that returns the json of a publication
 * 
 * @param $publication The publication.
 * @param $encodings Encodings from which we try to convert content to UTF8.
 * 
 * @return string The json of the publication or empty string if there is a problem.
 */
function getJsonPublication($publication, $encodings): string
{
	// Exit if there is a problem with one of the parameters
	if(!isset($publication) || !isset($encodings)) {
		return "";
	}


	$publicationHtml = load_file("https://scholar.google.fr" . str_replace("&amp;", "&", $publication->find(".gsc_a_t > a.gsc_a_at", 0)->href), $encodings);
	
	// Exit if there is an error
	if(is_null($publicationHtml))
		return "";

	$fields = $publicationHtml->find("#gsc_oci_table .gs_scl");

	$str = "\n  {";
	$str .= "\n    \"title\": \"" . trim($publication->find(".gsc_a_at", 0)->plaintext) . "\",";

	// Iterating over all the fields that describe the publication
	foreach ($fields as $field) {
		// Get the label and replace the spaces by underscores
		$label = str_replace(" ", "_", strtolower(trim($field->find(".gsc_oci_field", 0)->plaintext)));

		// Do not add the field if it is not interesting : "total_citations" and "scholar_articles"
		if(strcmp($label, "total_citations") == 0) break;
		if(strcmp($label, "scholar_articles") == 0) break;

		// Get the value and convert the encoding to UTF-8
		$value = mb_convert_encoding(trim($field->find(".gsc_oci_value", 0)->plaintext), "UTF-8", $encodings );
		$str .= "\n    \"" . $label . "\": \"" . $value . "\",";

	}
	$str .= "\n  },";
	return $str;
}


/**
 * Function that uses load_file() from simple_html_dom.php and handle the encoding and the errors.
 * 
 * @param $url The url of the file to load.
 * @param $encodings Encodings from which we try to convert content to UTF8.
 * 
 * @return simple_html_dom The html of the file or null if there is an error.
 */
function load_file($url, $encodings): ?simple_html_dom
{
	global $DEBUG;


	$html = new simple_html_dom();
	$html->load_file($url);

	if(hasError($html)) {
		if($DEBUG) {
			print("Error while loading the file: " . $url);
			var_dump(error_get_last());
		}
		return null;
	}

	// Load the page with the encoding UTF8 specified in the header
	$html->load(mb_convert_encoding($html->save(), "UTF_8", $encodings));

	if(hasError($html)) {
		if($DEBUG) {
			print("Error while loading the file: " . $url);
			var_dump(error_get_last());
		}
		return null;
	}


	return $html;
}


/**
 * Function that checks if there is an error in the html
 * 
 * @param $html The html to check.
 * 
 * @return bool True if there is an error, false otherwise.
 */
function hasError($html): bool
{
	if(!isset($html)) {
		return true;
	}

	return !is_null(error_get_last());
}
