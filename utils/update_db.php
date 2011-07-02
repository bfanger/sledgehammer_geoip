<?php
/**
 * De GeoIP database bijwerken
 */
namespace SledgeHammer;
require(dirname(__FILE__).'/../../core/init_framework.php');
$tmpDir = PATH.'tmp/';
//mkdirs($tmpDir);
echo "\nUpgrading GeoIP database\n";

// Download
echo "  Downloading...";
$zipFile = $tmpDir.'GeoIPCountryCSV.zip';
if (file_exists($zipFile) == false || filemtime($zipFile) < (time() - 3600)) { // Is het gedownloade bestand ouder dan 1 uur?
	wget('http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip', $zipFile);
	echo " done\n"; flush();
} else {
	echo " skipped\n";
}

// Unzip
$csvFile = $tmpDir.'GeoIPCountryWhois.csv';
if (file_exists($csvFile)) {
	unlink($csvFile);
}
echo "  Extracting...";

$archive = new \ZipArchive();
if ($archive->open($zipFile) !== true) {
	throw new \Exception('Failed to open zipfile');
}
$archive->extractTo($tmpDir);
echo " done\n";

// Rebuild
echo "  Creating database...";
$dbFile = PATH.'tmp/GeoIPCountry.sqlite';
if (file_exists($dbFile)) {
	unlink($dbFile);
	sleep(1);
}
$db = new \SQLiteDatabase($dbFile, 0600, $error); 
if (!$db) {
	error($error);
}


$dbSchema = array(
	'CREATE TABLE country (
		code CHAR(2) PRIMARY KEY,
		name VARCHAR(150) NOT NULL
	)',
	'CREATE TABLE ip2country (
		begin  UNSIGNED INTEGER PRIMARY KEY,
		end    UNSIGNED INTEGER NOT NULL,
		country_code CHAR(2) NOT NULL REFERENCES country(code)
	)',
	'CREATE INDEX end_ix ON ip2country (end)'
);
foreach ($dbSchema as $sql) {
	$sql = trim($sql);
	if ($sql != '' && $db->query($sql) == false) {
		throw new \Exception('Failed to import schema');
	}
}

// Kolomnamen toevoegen
//ini_set('memory_limit', '128M');
file_put_contents($csvFile, "begin_ip,end_ip,begin_num,end_num,code,country\n".file_get_contents($csvFile)); 

// Eerst de countries importeren
$csv = new CSVIterator($csvFile, null, ',');
$countries = array();
$rowCount = 0;
foreach($csv as $row) {
	$countries[$row['code']] = $row['country'];
	$rowCount++;
}
foreach ($countries as $code => $country) {
	if (!$db->query('INSERT INTO country (code, name) VALUES ("'.sqlite_escape_string($code).'", "'.sqlite_escape_string($country).'")')) {
		error('Failed to import countries');
	}
}
echo " done.\n";
echo "  Importing data (";
// Daarna alle ip-ranges importeren.
echo $rowCount." records)\n    ";
$db->query('BEGIN');
$previousTs = microtime(true);
foreach($csv as $index => $row) {
	$now = microtime(true);
	if ($previousTs < ($now - 1)) {
		echo round(($index / $rowCount) * 100), '% '; flush();
		$previousTs = $now;
	}
	if (!$db->query('INSERT INTO ip2country (begin, end, country_code) VALUES ("'.sqlite_escape_string($row['begin_num']).'", "'.sqlite_escape_string($row['end_num']).'", "'.sqlite_escape_string($row['code']).'")')) {
		error('Failed to import IP-ranges');
	}
}
$db->query('COMMIT');
echo " done\n  Upgrading module data...";
copy($dbFile, dirname(__FILE__).'/../data/geoip.sqlite');
unlink(PATH.'tmp/geoip.sqlite');
echo " done.\n";
?>
