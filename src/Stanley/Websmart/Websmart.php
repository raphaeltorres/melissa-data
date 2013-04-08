<?php

namespace Stanley\Websmart;

use Stanley\Websmart\WebsmartExceptions;

class Websmart 
{
    /**
     * XML Endpoint
     */
    private $xmlURL = 'https://addresscheck.melissadata.net/v2/XML/Service.svc/doAddressCheck';

    /**
     * REST Endpoint
     */
    private $restURL = 'https://addresscheck.melissadata.net/v2/REST/Service.svc/doAddressCheck';

    /**
     * Customer ID
     */
    private $customerID = null;

    /**
     * Submission Method
     */
    private $method = null;

    /**
     * Parse Address?
     */
    private $parseAddress = null;

    /**
     * Constructor
     * @param string $customerID Customer ID
     * @param string $method     Connection method
     */
    public function __construct( $customerID, $parseAddress = true )
    {
        $this->customerID = $customerID;
        $this->method = $method;
    }

    /**
     * Get Single Address via REST interface
     *
     * Incoming data must be a single-level array
     *      array[
     *          'key' => 'value',
     *          'key' => 'value',
     *          ...
     *          'key' => 'value'
     *      ]
     *      
     * @param  array $input      Array containing address record to be submitted 
     * @return array             Cleansed address data
     */
    public function getAddress( array $input )
    {
        // Convert to query string
        $query_string = $this->getQueryString( $input );
        
        // Do work!
        return $this->doREST( $query_string );  
    }

    /**
     * Post multiple addresses to XML interface
     *
     * Incoming data must be structured as follows
     *
     *      array[
     *          array[
     *              'key' => 'value',
     *              'key' => 'value',
     *              ...
     *              'key' => 'value'
     *              ],
     *          array[
     *              'key' => 'value',
     *              'key' => 'value',
     *              ...
     *              'key' => 'value'
     *              ]
     *      ]
     * 
     * @param  array $input     Array containing addresses to be submitted
     * @return array            Cleansed address data
     */
    public function postAddresses( array $input )
    {
        // Can only submit 100 addresses at a time this way
        if (count($input) > 100) {
            throw new Exception('Too many nodes in address array.');
        }  

        // Get the XML object
        $xml_payload = $this->getXML( $input );

        // Do work!
        return $this->doPost( $xml_payload );
    }

    /**
     * Convert array to query string for processing
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
     * @param  array   $data  Incoming address array
     * @return string         Query string for submission
     */
    public function getQueryString( array $data )
    {
        $query_string = array(
            'id'  => $this->customerID,
            'opt' => $this->parseAddress,
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

    /**
     * Convert array to xml object for submission
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
     * @param  array    $data   Incoming address array
     * @return object           XML object for submission
     */
    public function getXML( array $data )
    {
        $xml_payload = new SimpleXMLElement("<?xml version='1.0'?><RequestArray></RequestArray>");

        $xml_payload->addChild('CustomerID', $this->customerID);
        $xml_payload->addChild('OptAddressParsed', $this->parseAddress);

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
     * Do Post submission
     * @param  object $xml_payload XML string for submission
     * @return string              Cleansed address data
     */
    protected function doPost( $xml_payload )
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

        if (! $fp = @file_get_contents($this->_xml_base_url, false, $ctx, -1)) {
            throw new WebsmartConnectionError('Could not connect to service.');
        } 
        
        return $fp;
    }

    /**
     * Do REST
     *
     * Method to send a GET request to Websmart and return response
     *
     * @param   string  $query_string  Requires query string
     * @return  object|bool            If success, object is returned, else FALSE
     */
    protected function doREST( $query_string )
    {
        $request_uri = $this->restURL . $query_string;

        if (! $fp = @file_get_contents($request_uri, false)){
            throw new WebsmartConnectionError('Could not connect to service.');
        }

        return $fp;
    }

}