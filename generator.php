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

$card_color = 'white';
$fill = 'black';
$icon = '';
$mechanic = '';
$card_text = explode("\n", $_POST['card-text']);
$card_count = count($card_text);
$batch = escapeshellcmd($_POST['batch-id']);
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


if ($batch != '' && $card_count < 31) {
	if (!is_dir("$cwd/files")) {
		mkdir("$cwd/files");
	}
	mkdir($path);
	
	if ($icon == 'custom-' && getimagesize($_FILES["customIcon"]["tmp_name"]) && move_uploaded_file($_FILES["customIcon"]["tmp_name"], $path . '/custom_icon_raw')) {

	    // The White and Black cards aren't pixel perfect - the 'three card logo' in the bottom left corner is in a slightly different spot on each 
	    // Thus to get the custom icon to line up as best as possible, we need a slightly different set of coordinates
	    if ($card_color == 'black') {
		    $coord = '1722,3495';
	    }

	    exec($magick . ' ' . $path . '/custom_icon_raw -resize 150x150\! ' . $path . '/custom_icon');
	    exec($magick . ' ' . $card_front_path . $card_front . ' -units PixelsPerInch -density 1200 -draw "rotate 17 image over ' . $coord . ' 0,0 \'' . $path . '/custom_icon\'" ' . $path . '/' . $icon . $card_front);
	    $card_front_path = $path . '/';
	}

	$card_front = $icon . $card_front;

	foreach ($card_text as $i => $text) {

		// Replaces formatted quotations and apostrophes used by Microsoft Word
		$text = str_replace ('\“', '\"', $text);
		$text = str_replace ('\”', '\"', $text);
		$text = str_replace ('\’', '\'', $text);

		$text = escapeshellcmd($text);

		$text = str_replace ('\\\\x\\{201C\\}', '\\x{201C}', $text);
		$text = str_replace ('\\\\x\\{201D\\}', '\\x{201D}', $text);
		$text = str_replace ('\\\\x\\{2019\\}', '\\x{2019}', $text);
		$text = str_replace ('\\\\n', '\\n', $text);
		
		exec('perl -e \'use utf8; binmode(STDOUT, ":utf8"); print "' . $text . '\n";\' | tee -a ' . $cwd . '/card_log.txt | ' . $magick . ' ' . $card_front_path . $card_front . ' -page +444+444 -units PixelsPerInch -background ' . $card_color . ' -fill ' . $fill . ' -font ' . $cwd . '/fonts/HelveticaNeueBold.ttf -pointsize 15 -kerning -1 -density 1200 -size 2450x caption:@- -flatten ' . $path . '/temp.png; mv ' . $path . '/temp.png ' . $path . '/' . $batch . '_' . $i . '.png');
	}

	exec("cd $path; zip $batch.zip *.png");
}

?>
