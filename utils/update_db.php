<?php
/**
 * De GeoIP database bijwerken
 */
namespace SledgeHammer;
require(dirname(__FILE__).'/../../core/init_framework.php');
$ErrorHandler->html = true;
echo "\nUpgrading GeoIP database\n";

// Download
echo "  Downloading...";
mkdirs(TMP_DIR.'GeoIP_update');
$zipFile = TMP_DIR.'GeoIP_update/CountryCSV.zip';
if (file_exists($zipFile) == false || filemtime($zipFile) < (time() - 3600)) { // Is het gedownloade bestand ouder dan 1 uur?
	wget('http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip', $zipFile);
	echo " done\n"; flush();
} else {
	echo " skipped\n";
}

// Unzip
$csvFile = TMP_DIR.'GeoIP_update/GeoIPCountryWhois.csv';
if (file_exists($csvFile)) {
	unlink($csvFile);
}
echo "  Extracting...";

$archive = new \ZipArchive();
if ($archive->open($zipFile) !== true) {
	throw new \Exception('Failed to open zipfile');
}
$archive->extractTo(TMP_DIR.'GeoIP_update/');
echo " done\n";

// Rebuild
echo "  Creating database...";
$dbFile = TMP_DIR.'GeoIP_update/Country.sqlite';
if (file_exists($dbFile)) {
	unlink($dbFile);
	sleep(1);
}
$db = new Database('sqlite:'.$dbFile);

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
	if ($db->query($sql) == false) {
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
	if (!$db->query('INSERT INTO country (code, name) VALUES ('.$db->quote($code).', '.$db->quote($country).')')) {
		error('Failed to import countries');
	}
}
echo " done.\n";
echo "  Importing data (";
// Daarna alle ip-ranges importeren.
echo $rowCount." records)\n    ";
$db->beginTransaction();
$previousTs = microtime(true);
foreach($csv as $index => $row) {
	$now = microtime(true);
	if ($previousTs < ($now - 1)) {
		echo round(($index / $rowCount) * 100), '% '; flush();
		$previousTs = $now;
	}
	if ($db->query('INSERT INTO ip2country (begin, end, country_code) VALUES ('.$db->quote($row['begin_num']).', '.$db->quote($row['end_num']).', '.$db->quote($row['code']).')') == false) {
		error('Failed to import IP-ranges');
	}
}
$db->commit();
echo " done\n  Upgrading files...";
copy($dbFile, TMP_DIR.'geoip.sqlite');
$filename = realpath(dirname(__FILE__).'/../data/geoip.sqlite');
if (copy($dbFile, $filename)) {
	echo " done.\n";
} else {
	echo " FAILED.\n";
}


?>
