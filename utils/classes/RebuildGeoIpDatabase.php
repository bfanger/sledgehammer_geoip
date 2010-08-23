<?php
/**
 * De database (opnieuw) opbouwen
 * Eerste rij: "begin_ip","end_ip","begin_num","end_num","code","country" toegevoegd aan het GeoIPCountryWhois.csv bestand.
 */
class RebuildGeoIpDatabase extends Util {

	function __construct() {
		parent::__construct('Rebuild GeoIp database', 'database.png');
	}

	function execute() {
		$dbFile = $this->paths['project'].'tmp/geoip.sqlite';
		if (file_exists($dbFile)) {
			unlink($dbFile);
		}
		$db = new SQLiteDatabase($dbFile, 0600, $error); 
		if (!$db) {
			error($error);
		}
		echo "Creating an empty database...";
		$sqlStatements = explode(';', file_get_contents(dirname(__FILE__).'/../../db/schema.sql'));
		foreach ($sqlStatements as $sql) {
			$sql = trim($sql);
			if ($sql != '' && $db->query($sql) == false) {
				throw new Exception('Failed to import schema');
			}
		}
		echo "\n  done.\nImporting ";
		// Eerst de countries importeren
		$CSV = new CSVIterator(dirname(__FILE__).'/../../settings/GeoIPCountryWhois.csv', null, ',');
		$countries = array();
		$rowCount = 0;
		foreach($CSV as $row) {
			$countries[$row['code']] = $row['country'];
			$rowCount++;
		}
		foreach ($countries as $code => $country) {
			if (!$db->query('INSERT INTO country (code, name) VALUES ("'.sqlite_escape_string($code).'", "'.sqlite_escape_string($country).'")')) {
				error('Failed to import countries');
			}
		}
		// Daarna alle ip-ranges importeren.
		echo $rowCount.' records'."\n";
		$previousTs = microtime(true);
		foreach($CSV as $index => $row) {
			$now = microtime(true);
			if ($previousTs < ($now - 3)) {
				echo round(($index / $rowCount) * 100), '% '; flush();
				$previousTs = $now;
			}
			if (!$db->query('INSERT INTO ip2country (begin, end, country_code) VALUES ("'.sqlite_escape_string($row['begin_num']).'", "'.sqlite_escape_string($row['end_num']).'", "'.sqlite_escape_string($row['code']).'")')) {
				error('Failed to import IP-ranges');
			}
		}
		echo "\n  done.\n";

	}
}
?>
