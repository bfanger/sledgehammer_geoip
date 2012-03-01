<?php
/**
 * GeoIPLookupUtil
 *
 */
namespace SledgeHammer;
class GeoIPLookupUtil extends Util {

	function __construct() {
		parent::__construct('Geolocation for IP', 'module_icons/geoip.png');
	}

	function generateContent() {
		$module = array(
			'path' => dirname($this->paths['utils'])
		);
		if (isset($_GET['ip'])) {
			Framework::$autoLoader->importModule($module);
			$geoip = new GeoIP();
			$result = $geoip->getCountry(value($_GET['ip']));
			return new MessageBox('done', 'Maxmind GeoIP', 'IP: <b>'.$_GET['ip'].'</b> is located in <b>'.$result['country'].'</b> ('.$result['code'].')', '');
		} else {
			return new Form(
				array('method' => 'get'),
				array(
					new FieldLabel('IP address', new Input('text', 'ip')),
					new Input('submit', '', array('value' => 'Lookup', 'class' => 'btn btn-primary btn-small')
				)
			));
		}
	}
}

?>
