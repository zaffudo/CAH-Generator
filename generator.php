<?php
setlocale(LC_CTYPE, "UTF8", "en_US.utf8");

// Quick and dirty way to run on the command line for testing
// Example: php generator.php 'batch-id=submit_d6bsd1asidal120&card-text=test&card-color=white&icon=none'
//
if (!isset($_SERVER["HTTP_HOST"])) {
    parse_str($argv[1], $_GET);
    parse_str($argv[1], $_POST);
}

// Detect ImageMagick command: prefer 'magick' (v7+), fall back to 'convert' (v6)
// Check common paths since PHP's exec environment may have a limited PATH
$magick = '';
$search_paths = ['/usr/local/bin', '/usr/bin', '/bin', '/opt/local/bin'];
foreach (['magick', 'convert'] as $cmd) {
	// Try which first
	exec("which $cmd 2>/dev/null", $out, $ret);
	if ($ret === 0) {
		$magick = $cmd;
		break;
	}
	// Check common paths directly
	foreach ($search_paths as $dir) {
		if (file_exists("$dir/$cmd")) {
			$magick = "$dir/$cmd";
			break 2;
		}
	}
}
if ($magick === '') {
	error_log('ImageMagick not found: checked magick and convert in PATH and common locations');
	$magick = 'convert'; // last resort fallback
}
// Simple rate limiting: max 20 requests per IP per hour
$rate_limit_dir = getcwd() . '/files/.ratelimit';
if (!is_dir($rate_limit_dir)) {
	@mkdir($rate_limit_dir, 0755, true);
}
$client_ip = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rate_file = "$rate_limit_dir/$client_ip";
$rate_limit = 20;
$rate_window = 3600; // 1 hour
$now = time();

if (file_exists($rate_file)) {
	$requests = array_filter(
		explode("\n", trim(file_get_contents($rate_file))),
		function($ts) use ($now, $rate_window) { return ($now - (int)$ts) < $rate_window; }
	);
	if (count($requests) >= $rate_limit) {
		http_response_code(429);
		exit('Rate limit exceeded. Please try again later.');
	}
	$requests[] = $now;
	file_put_contents($rate_file, implode("\n", $requests));
} else {
	file_put_contents($rate_file, $now);
}

// Cleanup generated batches older than 2 weeks
$cleanup_dir = getcwd() . '/files';
if (is_dir($cleanup_dir)) {
	$two_weeks_ago = time() - (14 * 24 * 60 * 60);
	foreach (glob("$cleanup_dir/submit_*") as $dir) {
		if (is_dir($dir) && filemtime($dir) < $two_weeks_ago) {
			array_map('unlink', glob("$dir/*"));
			rmdir($dir);
		}
	}
}

$card_color = 'white';
$fill = 'black';
$icon = '';
$mechanic = '';
$card_text = explode("\n", $_POST['card-text']);
$card_count = count($card_text);
$batch = escapeshellcmd($_POST['batch-id']);
if (!preg_match('/^[a-zA-Z0-9_]+$/', $batch)) {
	http_response_code(400);
	error_log("Invalid batch ID rejected: $batch");
	exit('Invalid batch ID');
}
$cwd = getcwd();
$path = "$cwd/files/$batch";
$coord = '1718,3494';

if ($_POST['card-color'] == 'black') {
	$card_color = 'black';
	$fill = 'white';
}

switch ($_POST['icon']) {
	case "reddit":
		$icon = 'reddit-';
		break;
	case "maple":
		$icon = 'canada-';
		break;
	case "pax":
		$icon = 'pax-';
		break;
	case "snow":
		$icon = 'christmas-';
		break;
	case "ferengi":
		$icon = 'ferengi-';
		break;
	case "reject":
		$icon = 'reject-';
		break;
	case "HOC":
		$icon = 'HOC-';
		break;
	case "box":
		$icon = 'box-';
		break;
	case "hat":
		$icon = 'hat-';
		break;
	case "emu":
		$icon = 'emu-';
		break;
	case "1":
		$icon = 'v1-';
		break;
	case "2":
		$icon = 'v2-';
		break;
	case "3":
		$icon = 'v3-';
		break;
	case "4":
		$icon = 'v4-';
		break;
	case "5":
		$icon = 'v5-';
		break;
	case "6":
		$icon = 'v6-';
		break;
	case "custom":
		$icon = 'custom-';
		break;
}

switch ($_POST['mechanic'] ?? '') {
	case "p2":
		$mechanic = '-mechanic-p2';
		break;
	case "d2p3":
		$mechanic = '-mechanic-d2p3';
		break;
	case "gear":
		$mechanic = '-mechanic-gears';
		break;
}

// There are currently no White Cards with Mechanics - could change
if ($card_color == 'white') {
	$mechanic = '';
}


// Mechanic cards with expansion icons have not been created yet
if ($mechanic == '-mechanic-gears') {
	$icon = '';
}

$card_front_path = $cwd .'/img/';
$card_front = "$card_color$mechanic.png";


if ($batch == '' || $card_count >= 31) {
	http_response_code(400);
	exit($batch == '' ? 'Missing batch ID' : 'Too many cards (max 30)');
}

{
	if (!is_dir("$cwd/files")) {
		mkdir("$cwd/files");
	}
	mkdir($path);
	
	if ($icon == 'custom-' && isset($_FILES["customIcon"]) && $_FILES["customIcon"]["size"] > 1048576) {
		http_response_code(400);
		exit('Custom icon too large (max 1MB)');
	}

	if ($icon == 'custom-' && isset($_FILES["customIcon"]) && getimagesize($_FILES["customIcon"]["tmp_name"]) && move_uploaded_file($_FILES["customIcon"]["tmp_name"], $path . '/custom_icon_raw')) {

	    // The White and Black cards aren't pixel perfect - the 'three card logo' in the bottom left corner is in a slightly different spot on each 
	    // Thus to get the custom icon to line up as best as possible, we need a slightly different set of coordinates
	    if ($card_color == 'black') {
		    $coord = '1722,3495';
	    }

	    $resize_cmd = $magick . ' ' . $path . '/custom_icon_raw -resize 150x150\! ' . $path . '/custom_icon';
	    exec($resize_cmd . ' 2>&1', $resize_output, $resize_retval);
	    if ($resize_retval !== 0) {
		    error_log("Custom icon resize failed (exit $resize_retval): " . implode("\n", $resize_output));
	    }

	    $overlay_cmd = $magick . ' ' . $card_front_path . $card_front . ' -units PixelsPerInch -density 1200 -draw "rotate 17 image over ' . $coord . ' 0,0 \'' . $path . '/custom_icon\'" ' . $path . '/' . $icon . $card_front;
	    exec($overlay_cmd . ' 2>&1', $overlay_output, $overlay_retval);
	    if ($overlay_retval !== 0) {
		    error_log("Custom icon overlay failed (exit $overlay_retval): " . implode("\n", $overlay_output));
	    }

	    $card_front_path = $path . '/';
	}

	$card_front = $icon . $card_front;

	foreach ($card_text as $i => $text) {

		// Replaces formatted quotations and apostrophes used by Microsoft Word
		$text = str_replace ('\“', '\"', $text);
		$text = str_replace ('\”', '\"', $text);
		$text = str_replace ('\’', '\'', $text);

		$text = escapeshellcmd($text);

		// Convert escaped Unicode sequences to actual UTF-8 characters
		// (Previously handled by Perl, now done directly in PHP)
		$text = str_replace ('\\\\x\\{201C\\}', "\xE2\x80\x9C", $text); // left double quote
		$text = str_replace ('\\\\x\\{201D\\}', "\xE2\x80\x9D", $text); // right double quote
		$text = str_replace ('\\\\x\\{2019\\}', "\xE2\x80\x99", $text); // right single quote (apostrophe)
		$text = str_replace ('\\\\n', "\n", $text);
		
		// Log the card text
		file_put_contents($cwd . '/card_log.txt', $text . "\n", FILE_APPEND);

		// Pass caption text directly as argument (@ syntax blocked by security policy on shared hosting)
		$im_cmd = $magick . ' ' . $card_front_path . $card_front . ' -page +444+444 -units PixelsPerInch -background ' . $card_color . ' -fill ' . $fill . ' -font ' . $cwd . '/fonts/HelveticaNeueBold.ttf -pointsize 15 -kerning -1 -density 1200 -size 2450x caption:"' . $text . '" -flatten ' . $path . '/' . $batch . '_' . $i . '.png';
		exec($im_cmd . ' 2>&1', $im_output, $im_retval);
		if ($im_retval !== 0) {
			error_log("ImageMagick failed (exit $im_retval): " . implode("\n", $im_output));
			error_log("Command was: $im_cmd");
		}
	}

	exec("cd $path; zip $batch.zip *.png 2>&1", $zip_output, $zip_retval);
	if ($zip_retval !== 0) {
		error_log("ZIP creation failed (exit $zip_retval): " . implode("\n", $zip_output));
		http_response_code(500);
		exit('Failed to create ZIP archive');
	}
}

?>
