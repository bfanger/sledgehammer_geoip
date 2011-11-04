<?php
/**
 * Determine the Country based on an IP-address
 *
 * @package GeoIP
 */
namespace SledgeHammer;

class GeoIP extends Object {

	/**
	 * @var array  Code to Country mapping.
	 */
	private static $countries;

	function __construct() {
		if (empty($GLOBALS['Databases']['_GeoIP_'])) {
			$dbFile = TMP_DIR.'geoip.sqlite';
			if (file_exists($dbFile) == false) {
				copy(dirname(__FILE__).'/../data/geoip.sqlite', $dbFile);
			}
			$GLOBALS['Databases']['_GeoIP_'] = new Database('sqlite:'.$dbFile);
			if (!$this->tableExists('country') || !$this->tableExists('ip2country')) {
				throw new \Exception('GeoIP database is corrupt, run `php sledgehammer/geoip/utils/upgrade.php`');
			}
		}
		if (self::$countries === null) {
			$db = getDatabase('_GeoIP_');
			$countries = $db->query('SELECT code, name FROM country ORDER BY name ASC');
			foreach ($countries as $row) {
				self::$countries[$row['code']] = $row['name'];
			}
		}
	}

	/**
	 * Determine the country based on an IP-address.
	 *
	 * @param string $ip  (optional) The IP-address, defaults to the client-IP
	 * @return array  array('code' => $code, 'country' => $country)
	 */
	function getCountry($ip = null) {
		if ($ip === null) {
			$ip = $this->getClientIp();
		}
		$db = getDatabase('_GeoIP_');
		$quotedLong = $db->quote(ip2long($ip), \PDO::PARAM_INT);
		$code = $db->fetchValue('SELECT country_code FROM ip2country WHERE begin <= '.$quotedLong.' AND end >= '.$quotedLong, true);
		if ($code) {
			return array('code' => $code, 'country' => self::$countries[$code]);
		}
		notice('IP: "'.$ip.'" not found');
		return false;
	}

	/**
	 * Check if an IP is in the same network.
	 *
	 * @param string $ip  (optional) The IP-address, defaults to the client-IP
	 * @return bool
	 */
	function isLocalNetwork($ip = null) {
		if ($ip === null) {
			$ip = $this->getClientIp();
		}
		if ($ip === '127.0.0.1' || $ip === '::1') { // Is it on the same machine?
			return true;
		}
		if ($_SERVER['SERVER_ADDR'] === '127.0.0.1' || $_SERVER['SERVER_ADDR'] === '::1') {
			$server = gethostbyname(gethostname());
		} else {
			$server = $_SERVER['SERVER_ADDR'];
		}
		$parts = explode('.', $server); // Splits het server IP op in 4 stukken
		$start = ip2long($parts[0].'.'.$parts[1].'.'.$parts[2].'.0'); // Ga uit van een netmask van 255.255.255.0
		$end = ip2long($parts[0].'.'.$parts[1].'.'.$parts[2].'.255');
		$ipNumber = ip2long($ip);
		if ($ipNumber >= $start && $ipNumber <= $end) {
			return true;
		}
		return false;
	}

	/**
	 * Check if an IP is located in a given country.
	 *
	 * @param string $country  The country (Both code or name are supported)
	 * @param string $ip  (optional) The IP-address, defaults to the client-IP
	 * @return bool
	 */
	function inCountry($country, $ip = null) {
		$code = $this->getCountryCode($country);
		if ($code == false) {
			throw new \Exception('Unable to determime the country_code');
		}
		$country = $this->getCountry($ip);
		return ($country['code'] == $code);
		/* // Deprecated optimalisation. Creates a tmp table with only entries for the chosen language.
		if ($ip === null) {
			$ip = $this->getClientIp();
		}
		$db = getDatabase('_GeoIP_');
		$table = 'ip_in_'.strtolower($code);
		if (!$this->tableExists($table)) {
			$sqlStatements = array(
				'CREATE TABLE '.$table.' (begin UNSIGNED INTEGER PRIMAIRY KEY, end UNSIGNED INTEGER)',
				'CREATE INDEX '.$code.'_end_ix ON '.$table.' (end)',
				'INSERT INTO '.$table.' (begin, end) SELECT begin, end FROM ip2country WHERE country_code = '.$db->quote($code),
			);
			foreach ($sqlStatements as $sql) {
				if (!$db->query($sql)) {
					throw new \Exception('Unable to create cache table');
				}
			}
		}
		$quotedLong = $db->quote(ip2long($ip), \PDO::PARAM_INT);
		return ($db->fetchValue('SELECT begin FROM '.$table.' WHERE begin <= '.$quotedLong.' AND end >= '.$quotedLong, true) != false);
		 */
	}

	/**
	 * Check if a table exists in the (sqlite) database.
	 *
	 * @param string $table
	 * @return bool
	 */
	private function tableExists($table) {
		static $tables = array();
		if (isset($tables[$table]) === false) {
			$db = getDatabase('_GeoIP_');
			$tables[$table] = (bool) $db->fetchValue('SELECT count(*) FROM sqlite_master WHERE type="table" AND name='.$db->quote($table));
		}
		return $tables[$table];
	}

	/**
	 * Validate adn return the code or lookup the country-name and return the code.
	 *
	 * @param string $country  Code or Country name
	 * @return string  Country code
	 */
	private function getCountryCode($country) {
		if (isset(self::$countries[$country])) { // $country is a code? NL, US, etc
			return $country;
		}
		$code = array_search($country, self::$countries); // Lookup the country name based on the name. "Netherlands", "Australia", etc
		if ($code) {
			return $code;
		}
		notice('Country: "'.$country.'" is unknown', array('Available codes/countries' => self::$countries));
		return false;
	}

	/**
	 * @return string  The IP-address of the client/browser
	 */
	private function getClientIp() {
		if ($_SERVER['REMOTE_ADDR'] === '::1' || $_SERVER['REMOTE_ADDR'] === '127.0.0.1') {
			return '127.0.0.1';
		}
		return getClientIp();
	}

}

?>
