<?php
/**
 * Bepaal o.b.v. IP-adres het land van herkomst.
 * 
 * 
 */

class GeoIP extends Object {

	private 
		$db, // SQLite database
		$countries;

	function __construct() {
		$dbFile = PATH.'tmp/geoip.sqlite';
		if (file_exists($dbFile) == false) {
			copy(dirname(__FILE__).'/../data/geoip.sqlite', $dbFile);
		}
		$this->db = new SQLiteDatabase($dbFile, 0600, $error); 
		if (!$this->db) {
			throw new Exception($error);
		}
		if (!$this->table_exists('country') || !$this->table_exists('ip2country')) {
			throw new Exception('GeoIP database is corrupt, run `php sledgehammer/geoip/utils/upgrade.php`');
		}
		$countries = $this->db->query('SELECT code, name FROM country ORDER BY code ASC', SQLITE_ASSOC);
		foreach ($countries as $row) {
			$this->countries[$row['code']] = $row['name'];
		}
	}

	/**
	 * Vraag de landcode en de naam van het land op. (o.b.v. het IP-adres)
	 */
	function getCountry($ip = null) {
		$ip = $this->getIP($ip);
		$countryCode = $this->db->singleQuery('SELECT country_code FROM ip2country WHERE begin <= '.ip2long($ip).' AND end >= '.ip2long($ip));
		if ($countryCode) {
			return array('code' => $countryCode, 'country' => $this->countries[$countryCode]);
		}
		notice('IP: "'.$ip.'" not found');
		return false;
	}

	function isLocalNetwork($ip = null) {
		$ip = $this->getIP($ip);
		if ($ip == '127.0.0.1' || $ip == '::1') {
			return true;
		}
		$parts = explode('.', $_SERVER['SERVER_ADDR']); // Splits het server IP op in 4 stukken
		$start = ip2long($parts[0].'.'.$parts[1].'.'.$parts[2].'.0'); // Ga uit van een netmask van 255.255.255.0
		$end = ip2long($parts[0].'.'.$parts[1].'.'.$parts[2].'.255');
		$ipNumber = ip2long($ip);
		if ($ipNumber >= $start && $ipNumber <= $end) {
			return true;
		}
		return false;
	}

	/**
	 * Controleer of het ip zich in een land bevind
	 *
	 * @param string $country De country code of naam.
	 * @return bool
	 */
	function inCountry($country, $ip = null) {
		$ip = $this->getIP($ip);
		$code = $this->getCountryCode($country);
		if ($code == false) {
			throw new Exception('Unable to determime the country_code');
		}
		/*
		if (false) { // No cache tables?
			$found = $this->db->singleQuery('SELECT country_code FROM ip2country WHERE begin <= '.ip2long($ip).' AND end >= '.ip2long($ip));
			return ($found == $code);
		}*/
		$table = 'ip_in_'.strtolower($code);
		if (!$this->table_exists($table)) {
			$sqlStatements = array(
				'CREATE TABLE '.$table.' (begin UNSIGNED INTEGER PRIMAIRY KEY, end UNSIGNED INTEGER)',
				'CREATE INDEX '.$code.'_end_ix ON '.$table.' (end)',
				'INSERT INTO '.$table.' (begin, end) SELECT begin, end FROM ip2country WHERE country_code = "'.sqlite_escape_string($code).'"',
			);
			foreach ($sqlStatements as $sql) {
				if (!$this->db->query($sql)) {
					throw new Exception('Unable to create cache table');
				}
			}
		}
		$found = $this->db->singleQuery('SELECT begin FROM '.$table.' WHERE begin <= '.ip2long($ip).' AND end >= '.ip2long($ip));
		return ($found != false);
	}

	/**
 	 * Zoek een IP in csv bestand en retourneer de gevonden rij.
	 *
	 * @return array|false Retourneert false als het ip niet voorkwam in het csv bestand.
 	 */
	private function search($file, $ip) {
		$number = ip2long($ip);
		$CSV = new CSVIterator($file, null,  ',');
		foreach($CSV as $row) {
			if ($number >= $row['begin_num'] && $number <= $row['end_num']) {
				return $row;
			}
		}
		return false;
	}

	private function table_exists($table) {
		return (bool) $this->db->singleQuery('SELECT count(*) FROM sqlite_master WHERE type="table" and name="'.sqlite_escape_string($table).'"');
	}

	/**
	 *
	 */
	private function getIP($address) {
		if ($address == null) {
			$ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$ip = $address;
		}
		return $ip;
	}

	private function getCountryCode($country) {
		if (isset($this->countries[$country])) { //is het al een code?
			return $country;
		}
		$code = array_search($country, $this->countries);
		if ($code) {
			return $code;
		}
		notice('Country: "'.$country.'" is unknown', array('Available codes/countries' => $this->countries));
		return false;
	}
}
?>
