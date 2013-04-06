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
 * A simle class to handle address verification through the Melissa Data WebSmart service.
 *
 * See: http://www.melissadata.com/manuals/dqt-websmart-addresscheck-reference-guide.pdf
 *
 * @category    Libraries
 * @package     Address
 * @abstract
 */
abstract class Websmart {

	/**
	 * Base URL for websmart XML service
	 *
	 * @var string
	 */
	protected $_xml_base_url = NULL;

	/**
	 * Base URL for websmart REST service
	 *
	 * @var string
	 */
	protected $_rest_base_url = NULL;

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

		$this->_customer_id    = config_item('md_cust_id');
		$this->_parse_address  = config_item('md_parse_address');

		// Only need one of these at a time. Could move them into
		// verify_address() but that would make un-CodeIgniter-ing
		// this class in the future a bit more difficult.
		$this->_xml_base_url   = config_item('md_xml_base_url');
		$this->_rest_base_url  = config_item('md_rest_base_url');

	}

	/**
	 * Verify Address
	 *
	 * Facade to allow the future switching between GET and POST methods
	 *
	 * @param   array  $data  Multidimensional array of address data
	 * @param   string $data  XML (check 1 or more records) or REST (check 1 record)
	 * @return  array  $a     Cleansed array of address data
	 * @uses    _array_to_xml
	 */
	public function verify_address($data, $method = 'XML')
	{
		if (strtoupper($method) == 'XML')
		{
			$xml_payload = $this->_array_to_xml($data);

			if ( ! $response = $this->_do_post($xml_payload))
			{
				return FALSE;
			}
		}
		elseif (strtoupper($method) == 'REST')
		{
			// Make sure we only have are dealing with a SINGLE address record
			$record = reset($data);

			$query_string = $this->_array_to_rest($record);

			if ( ! $response = $this->_do_rest($query_string))
			{
				return FALSE;
			}
		}
		else
		{
			// Future opperations?
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
	 * Do REST
	 *
	 * Method to send a GET request to Websmart and return response
	 *
	 * @param   string  $query_string  Requires XML object to post
	 * @access  private
	 * @return  object|bool           If success, object is returned, else FALSE
	 */
	private function _do_rest($query_string)
	{
		$request_uri = $this->_rest_base_url . $query_string;

		if ($fp = @file_get_contents($request_uri, false))
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
	 * See: http://www.melissadata.com/manuals/dqt-websmart-addresscheck-reference-guide.pdf
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

	/**
	 * Convert array to REST query string
	 *
	 * Method to convert incoming array to properly formatted URI string
	 * for WebSmart REST lookup of a SINGLE ADDRESS.
	 *
	 * See: http://www.melissadata.com/manuals/dqt-websmart-addresscheck-reference-guide.pdf
	 *
	 * t     = {transMissionReference} Optional - Returned as sent allowing matching the Response to the Request.
	 * comp  = {Company}               Optional - Compnay name associated with Address
	 * u     = {Urbanization}          Optional - Urbanization only to Puerto Rican addresses
	 * a1    = {AddressLine1}          REQUIRED * First line of the street address
	 * a2    = {AddressLine2}          Optional - Second line of the street address
	 * ste   = {suite}                 Optional - Suite name and number
	 * city  = {City}                  REQUIRED * The city or municipality name
	 * state = {State}                 REQUIRED * The name or abbreviation for the state or province
	 * zip   = {Zip}                   REQUIRED * ZIP or Postal code
	 * ctry  = {Country}               Optional - Name or abbreviation of the country
	 *
	 * NOTE: REQUIRED elements are a1 AND (city/state OR zip)
	 *
	 * @param   array   $record        Array of address data for a SINGLE ADDRESS
	 * @return  string  $query_string  URL Encoded URI string
	 */
	private function _array_to_rest($record)
	{
		$query_string = array(
			'id'  => $this->_customer_id,
			'opt' => $this->_parse_address,
		);

		$query_string['t']     = isset($record['transmissionreference']) ? $record['transmissionreference'] : NULL;
		$query_string['comp']  = isset($record['company'])               ? $record['company']               : NULL;
		$query_string['u']     = isset($record['urbanization'])          ? $record['urbanization']          : NULL;
		$query_string['a1']    = isset($record['address1'])              ? $record['address1']              : NULL;
		$query_string['a2']    = isset($record['address2'])              ? $record['address2']              : NULL;
		$query_string['ste']   = isset($record['suite'])                 ? $record['suite']                 : NULL;
		$query_string['city']  = isset($record['city'])                  ? $record['city']                  : NULL;
		$query_string['state'] = isset($record['state'])                 ? $record['state']                 : NULL;
		$query_string['zip']   = isset($record['zip'])                   ? $record['zip']                   : NULL;
		$query_string['ctry']  = isset($record['country'])               ? $record['country']               : NULL;

		return http_build_query(array_filter($query_string));
	}
}