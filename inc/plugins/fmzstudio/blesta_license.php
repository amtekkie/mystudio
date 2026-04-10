<?php
/**
 * License Manager Integration Library
 *
 * Application integration code, used for validating license data supplied by
 * the Blesta License Manager Plugin.
 *
 * Requires PHP 5.1.2 or greater.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @copyright Copyright (c) 2014, Phillips Data Inc.
 */
class License {

	/**
	 * @param LicenseManager The license manager
	 */
	private $LicenseManager;

	/**
	 * Initializes the LicenseManager only after verifying its trustworhiness
	 *
	 * @param string $path_to_phpseclib The full path to the phpseblib library
	 * @param string $install_path The full server path to the root directory of the client software. Defaults to current directory.
	 * @see License::getManager()
	 */
	public function __construct($path_to_phpseclib, $install_path = null) {
		$signature_mode = "sha256";
		$signatures = array(
			$path_to_phpseclib . "Crypt" . DIRECTORY_SEPARATOR . "AES.php" => array(
				"3521de44d12be4fb4d3e2e1ae1e79a920afdac2d4caef44503492ca759d7b1b7",
				"72ed065258b3f169e65e56fe78fc0d3ce850db9a26c0d20ca138a098c7046185",
				"52e928d5bcc51dc29cb9944f210242f0d14c9cd02b7609d2e1092ca1870e3b1b",
				"e6172a71e2bddc13ef2eda35b60b5272c4d622114cfaa4de434e618c7e2365d4"
			),
			$path_to_phpseclib . "Crypt" . DIRECTORY_SEPARATOR . "Rijndael.php" => array(
				"8ed3842f0367910b5adcf362625c2d100fb06a84e3c2120f50d3cabbcc4219ba",
				"b35c479b55205c0333b85f03e777f8fa1373a718c9b90612face823b173cd468",
				"0c5c9f99c929d2fe6a5c5d3f4b9f6706bb0b26afde72366cd3f2e8b00c19d04a",
				"d13fe2de7e55650daa108c0ce51ede9442c3d326549c5fd54354da9e93e57a77"
			),
			$path_to_phpseclib . "Crypt" . DIRECTORY_SEPARATOR . "RSA.php" => array(
				"e1ce34273b49f6f45517008233bd6b28065e939bfe343fcd1f2027c91db565aa",
				"5529c25826eb7c5d5cef47a9f1816856d7eb237396c3824ff36b2b46ce23f03a",
				"e863ebe1d2b79c25f347c35f7a5bc9821cd4a54ec04843a102575f1e1cbcbf6f",
				"8da97806bb59826bf2347a43ea4712d9ec8c6d67b62e47047bf7e03e8e1e9bd1",
				"4bb621cd9e82e386e4fe0b809d71332f20b76d09fc789e6a3964d3d01d41e83e",
				"82bd9fcd06b5b3ef7ea5bafd3c4397ba969c3dccfa655fa05120ebf4f6990f1b"
			),
			$path_to_phpseclib . "Crypt" . DIRECTORY_SEPARATOR . "Hash.php" => array(
				"345fd70bff1ca53d86628a5d98575501f58899755dccdfb1a3984ff747a26b57",
				"de9bbe372ed6e8b9021d72c831c99aaba3affeaab86ddd57afdaaa00cabba94b",
				"d51fcf59b049f847d543d9241d9e898e1bf16060ae9afecea9cec12da10f6a11",
				"29dc47478e819e53c0827767764792f7ff8249a19c1584602d744787aa97945b"
			),
			$path_to_phpseclib . "Math" . DIRECTORY_SEPARATOR . "BigInteger.php" => array(
				"d4962ca226ba6825cabb3cf319884bed5690908b60c7645a9da0cecfc1c1e010",
				"3e36bae441de3f5e9f7d2064681179def7489e5202f5574b17cf33ddce744e69",
				"9348e9beeeb2606cf7c4180403f4adff3d82ad60376aea6558475bac8c9cc169",
				"e65ce5854faf0606cd567b782746ca448cec58c74d008cff60e4c9bfb3e51bb2"
			)
		);

		// Initialize LicenseManager if possible
		if ($this->verify()) {
			require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "blesta_license_manager.php";
			$this->LicenseManager = new LicenseManager($path_to_phpseclib, $signature_mode, $signatures, $install_path);
		}
	}

	/**
	 * Returns the LicenseManager
	 *
	 * @return LicenseManager The initialized LicenseManager object
	 */
	public function getManager() {
		return $this->LicenseManager;
	}

	/**
	 * Verify that the license manager can be initialized
	 *
	 * @return boolean True if the license manager is valid
	 * @throws Exception If the license manager is invalid and therefore cannot be initialized
	 */
	private function verify() {
		$hash = array(
			"90de55a2ea56ff212e6242af771c9a17dbdc87a61bfea724425c9066e0ea390a", // with \n EOL
			"d879ba04cfaadfcbdbebbdb43f2607bab82b9d98b3e5c41d3ce2f08765e2d541", // with \r\n EOL
		);

		if (!in_array(hash_file("sha256", dirname(__FILE__) . DIRECTORY_SEPARATOR . "blesta_license_manager.php"), $hash))
			throw new Exception("LicenseManager invalid, LicenseManager can not be initialized.");
		return true;
	}
}
?>