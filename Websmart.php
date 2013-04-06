<?php
/**
 * Websmart Address Verification
 *
 * @package Address
 */

/**
 * Protect the Script
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WebSmart Address Verification
 *
 * A simle class to handle address verification through the Melissa Data WebSmart service..
 *
 * @category    Libraries
 * @package     Address
 * @abstract
 */
abstract class Websmart {

	/**
	 * Base URL for websmart service
	 *
	 * @var string
	 */
	protected $_xml_base_url = NULL;

	/**
	 * customer id
	 *
	 * @var string
	 */
	protected $_customer_id = NULL;

	/**
	 * Should the service return the parsed address?
	 *
	 * @var bool
	 */
	protected $_parse_address = TRUE;

	/**
	 * The CI singleton
	 *
	 * @var object
	 */
	public $ci;

	/**
	 * Constructor function
	 */
	public function __construct()
	{
		$this->ci = & get_instance();

		// Lets grab the config and get ready to party
		$this->ci->load->config('address_verification');

		$this->_xml_base_url  = config_item('md_xml_base_url');
		$this->_customer_id   = config_item('md_cust_id');
		$this->_parse_address = config_item('md_parse_address');
	}

	/**
	 * Verify Addres
	 *
	 * Facade to allow the future switching between GET and POST methods
	 *
	 * @param   array  $data  Multidimensional array of address data
	 * @return  array  $a     Cleansed array of address data
	 * @uses    _array_to_xml
	 */
	public function verify_address($data)
	{
		$xml_payload = $this->_array_to_xml($data);

		if ( ! $response = $this->_do_post($xml_payload))
		{
			return FALSE;
		}

		$a = json_decode(json_encode((array) simplexml_load_string($response)),1);

		return $a;
	}

	/**
	 * Do Post
	 *
	 * Method to post data to Websmart and return response
	 *
	 * @param   object  $xml_payload  Requires XML object to post
	 * @access  private
	 * @return  object|bool           If success, object is returned, else FALSE
	 */
	private function _do_post($xml_payload)
	{
		$data = $xml_payload->asXML();

		// Set the default headers
		$headers = array(
			'Content-Type: text/xml',
			'Content-Length: ' . strlen($data),
		);

		$params = array(
			'http' => array(
				'method'  => 'POST',
				'header'  => $headers,
				'content' => $data,
			)
		);

		$ctx = stream_context_create($params);

		if ($fp = @file_get_contents($this->_xml_base_url, false, $ctx, -1))
		{
			return $fp;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Convert array to XML
	 *
	 * Method to convert incoming array to properly formatted XML object for WebSmart
	 *
	 * +> Example <+
	 *   <RequestArray>
	 *      <TransmissionReference>Web Service Test 2008/12/31
	 *      </TransmissionReference>
	 *      <CustomerID>123456789</CustomerID>
	 *      <OptAddressParsed>True</OptAddressParsed>
	 *      <Record>
	 *          <RecordID>1</RecordID>>
	 *          <Company />
	 *          <Urbanization />
	 *          <AddressLine1>22382 Avenida Empresa</AddressLine1>
	 *          <AddressLine2 />
	 *          <Suite />
	 *          <City>Rancho Santa Margarita</City>
	 *          <State>CA</State>
	 *          <Zip>92688</Zip>
	 *          <Plus4 />
	 *          <Country />
	 *      </Record>
	 *      <Record>
	 *          ...
	 *      </Record>
	 *   </RequestArray>
	 *
	 * @param   array   $data         Multidimensional array of address data
	 * @return  object  $xml_payload  XML object to be used and posted
	 */
	private function _array_to_xml($data)
	{
		$xml_payload = new SimpleXMLElement("<?xml version='1.0'?><RequestArray></RequestArray>");

		$xml_payload->addChild('CustomerID',$this->_customer_id);
		$xml_payload->addChild('OptAddressParsed',$this->_parse_address);

		foreach ($data as $key => $record)
		{
			$node = $xml_payload->addChild('Record');

			$node->addChild('RecordID',$key);

			isset($record['company'])       ? $node->addChild('Company',htmlspecialchars($record['company']))             : $node->addChild('company');
			isset($record['urbanization'])  ? $node->addChild('Urbanization',htmlspecialchars($record['urbanization']))   : $node->addChild('urbanization');
			isset($record['address1'])      ? $node->addChild('AddressLine1',htmlspecialchars($record['address1']))       : $node->addChild('address1');
			isset($record['address2'])      ? $node->addChild('AddressLine2',htmlspecialchars($record['address2']))       : $node->addChild('address2');
			isset($record['suite'])         ? $node->addChild('Suite',htmlspecialchars($record['suite']))                 : $node->addChild('suite');
			isset($record['city'])          ? $node->addChild('City',htmlspecialchars($record['city']))                   : $node->addChild('city');
			isset($record['state'])         ? $node->addChild('State',htmlspecialchars($record['state']))                 : $node->addChild('state');
			isset($record['zip'])           ? $node->addChild('Zip',htmlspecialchars($record['zip']))                     : $node->addChild('zip');
			isset($record['plus4'])         ? $node->addChild('Plus4',htmlspecialchars($record['plus4']))                 : $node->addChild('plus4');
			isset($record['country'])       ? $node->addChild('Country',htmlspecialchars($record['country']))             : $node->addChild('country');
		}

		return $xml_payload;
	}
}