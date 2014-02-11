<?php
/**
 * Plugin Name: Pubman Redux
 * Description: Plugin para mostrar publicaciones
 * Version: 2.0
 * Author: Ivan Martinez-Ortiz
 * Author: &Aacute;ngel Serrano
 * License: Apache2
 */

require_once __DIR__.'/config.php';

/* 
 * Simulates minimum WP functionality to test the plugin without WP
 */
if (WP_PUBMAN_DEBUG == true ) { 
	if (!function_exists('esc_html')) {
		function esc_html($text) {
			return htmlspecialchars($text);
		}
	}

	if (!function_exists('add_shortcode')) {
		function add_shortcode($code, $function) {
			echo pubman_publications(array());
		}
	}
}

function pubman_log($message) {
	if ((defined('WP_DEBUG') && WP_DEBUG === true) || WP_PUBMAN_DEBUG === true) {
		if ( is_array($message) || is_object($message) ) {
			error_log( print_r($message, true) );
		} else {
			error_log( $message );
		}
	}
}

/** Prints the publication **/
function pubman_print_publication($publication, $addclasses){
	$result = '';
	if ($addclasses){
		$result .= '<span class="pubclass-authors">';
	}

	$i = 0;
	foreach ($publication->authors as $author) {
		if ($i>0){
			$result .= ', ';
		}
		$result .= $author->name;
		$i++;
	}

	if ( $i == 1 ){
		$result .= 'No authors (';
	}
	if ($addclasses){
		$result .= '</span><span class="pubclass-year">';
	}
	
	$result .= ' (' . $publication->year . '): ';
	
	if ($addclasses){
		$result .= '</span>';
	}
	$result .= '<em><strong>';
	if ($publication->fileURL){
		$result .= '<a ';
		if ($addclasses){
			$result .='id="single-publication" class="pubclass-link" ';
		}
		$result .='href="' . $publication->fileURL . '">';
	}
	$result .= esc_html($publication->title);
	if ($publication->fileURL){
		$result .= '</a>';
	}
	$result .= '</strong></em>. ';
	if ($addclasses){
		$result .='<span class="pubclass-details">';
	}
	$result .= esc_html($publication->details) .'.';
	if ($addclasses){
		$result .='</span>';
	}
	return $result;
}

/** Prints an array with publications **/
function pubman_print_publications_list($publications){
	$result = '';
	if (count($publications)>1){
		$last_year = -1;
		$last_category = '';
		$result .= '<ul>';
		foreach ($publications as $publication ) {
			$year = $publication->year;
			if ($last_year != $year ){
				$result .= '<br/><h3>' . esc_html($year) . '</h3>';
				$last_year = $year;
				$last_category = '';
			}
			$category = $publication->category->name;
			if ($last_category != $category ){
				$result .= '<h4>' . esc_html($category). '</h4>';
				$last_category = $category;
			}

			$addclasses = false;
			$result .= '<li>' . pubman_print_publication($publication, $addclasses) . '</li>';
		}
		$result .= '</ul>';
	} else {
		foreach ($publications as $publication ) {
			//$result .= '<p>' . print_publication($publication) . '</p>';
			$addclasses = true;
			$result .= pubman_print_publication($publication, $addclasses);
		}
	}
	return $result;
}


function pubman_calculate_signature ($method, $url, $params, $apiSecret) {
	ksort($params);
	$params_string='';
	$i=0;
	foreach ($params as $key => $value) {
		if ($i > 0) {
			$params_string .= '&';
		}
		$params_string .= rawurlencode($key). '='. rawurlencode($value);
		$i++;
	}

	$request = strtoupper($method) . '&' . rawurlencode($url) . '&' . $params_string;
	return array($params_string, rawurlencode(base64_encode(hash_hmac('sha256', $request, $apiSecret))));
}

function pubman_sign_url($method, $url, $params, $apiSecret) {
	$params['nonce'] = base64_encode(openssl_random_pseudo_bytes(32));

	list($params_string, $signature) =  pubman_calculate_signature($method, $url, $params, $apiSecret);
	return $url.'?'.$params_string.'&'.rawurlencode('signature') . '=' . $signature;
}

/** Prints all the publications of the author. If no author is provided, prints ALL AUTHORS publications **/
function pubman_publications($atts){
	$url = WP_PUBMAN_SERVICE_URL;
	$req = curl_init();
	$curlopt = array(
		CURLOPT_RETURNTRANSFER => true, 
	);

	if ($atts){
		extract($atts);
	}

	if ($atts && $author) {
		$url .= "/authors/$author";
	} else if ($atts && $article) {
		$url .= "/publications/$article";
	} else {
		$url .= '/publications';
	}

	$params = array('apikey' => WP_PUBMAN_API_KEY);
	$url = pubman_sign_url('GET', $url, $params, WP_PUBMAN_API_SECRET);
	$curlopt[CURLOPT_URL] = $url;

	// Configure curl request
	curl_setopt_array($req, $curlopt);

	$publications = array();
	// $output contains the output string 
	$output = curl_exec($req);
	if ( curl_errno($req) === 0 ) {
		$info = curl_getinfo($req);
		if ($info['http_code'] == 200) {
			$publications = json_decode($output);
		} else {
			pubman_log(array($output, $info));
		}
	} else {
		pubman_log(array(curl_errno($req), curl_error($req)));
	}

	// close curl resource to free up system resources 
	curl_close($req);

	return pubman_print_publications_list($publications);
}

add_shortcode('pubman', 'pubman_publications');
