<?php
/**
 * UpdateInstallmentsPlanResponse
 *
 * PHP version 5
 *
 * @category Class
 * @package  SplititSdkClient
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */

/**
 * splitit-web-api-public-sdk
 *
 * No description provided (generated by Swagger Codegen https://github.com/swagger-api/swagger-codegen)
 *
 * OpenAPI spec version: 1.0.0
 * 
 * Generated by: https://github.com/swagger-api/swagger-codegen.git
 * Swagger Codegen version: 2.4.12
 */

/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace SplititSdkClient\Model;

use \ArrayAccess;
use \SplititSdkClient\ObjectSerializer;

/**
 * UpdateInstallmentsPlanResponse Class Doc Comment
 *
 * @category Class
 * @package  SplititSdkClient
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */
class UpdateInstallmentsPlanResponse implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'UpdateInstallmentsPlanResponse';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'response_header' => '\SplititSdkClient\Model\ResponseHeader',
        'installment_plan' => '\SplititSdkClient\Model\InstallmentPlan',
        'gateway_transaction_results' => '\SplititSdkClient\Model\TransactionResult[]',
        'approval_url' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'response_header' => null,
        'installment_plan' => null,
        'gateway_transaction_results' => null,
        'approval_url' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'response_header' => 'ResponseHeader',
        'installment_plan' => 'InstallmentPlan',
        'gateway_transaction_results' => 'GatewayTransactionResults',
        'approval_url' => 'ApprovalUrl'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'response_header' => 'setResponseHeader',
        'installment_plan' => 'setInstallmentPlan',
        'gateway_transaction_results' => 'setGatewayTransactionResults',
        'approval_url' => 'setApprovalUrl'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'response_header' => 'getResponseHeader',
        'installment_plan' => 'getInstallmentPlan',
        'gateway_transaction_results' => 'getGatewayTransactionResults',
        'approval_url' => 'getApprovalUrl'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$swaggerModelName;
    }

    

    

    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['response_header'] = isset($data['response_header']) ? $data['response_header'] : null;
        $this->container['installment_plan'] = isset($data['installment_plan']) ? $data['installment_plan'] : null;
        $this->container['gateway_transaction_results'] = isset($data['gateway_transaction_results']) ? $data['gateway_transaction_results'] : null;
        $this->container['approval_url'] = isset($data['approval_url']) ? $data['approval_url'] : null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }


    /**
     * Gets response_header
     *
     * @return \SplititSdkClient\Model\ResponseHeader
     */
    public function getResponseHeader()
    {
        return $this->container['response_header'];
    }

    /**
     * Sets response_header
     *
     * @param \SplititSdkClient\Model\ResponseHeader $response_header response_header
     *
     * @return $this
     */
    public function setResponseHeader($response_header)
    {
        $this->container['response_header'] = $response_header;

        return $this;
    }

    /**
     * Gets installment_plan
     *
     * @return \SplititSdkClient\Model\InstallmentPlan
     */
    public function getInstallmentPlan()
    {
        return $this->container['installment_plan'];
    }

    /**
     * Sets installment_plan
     *
     * @param \SplititSdkClient\Model\InstallmentPlan $installment_plan installment_plan
     *
     * @return $this
     */
    public function setInstallmentPlan($installment_plan)
    {
        $this->container['installment_plan'] = $installment_plan;

        return $this;
    }

    /**
     * Gets gateway_transaction_results
     *
     * @return \SplititSdkClient\Model\TransactionResult[]
     */
    public function getGatewayTransactionResults()
    {
        return $this->container['gateway_transaction_results'];
    }

    /**
     * Sets gateway_transaction_results
     *
     * @param \SplititSdkClient\Model\TransactionResult[] $gateway_transaction_results gateway_transaction_results
     *
     * @return $this
     */
    public function setGatewayTransactionResults($gateway_transaction_results)
    {
        $this->container['gateway_transaction_results'] = $gateway_transaction_results;

        return $this;
    }

    /**
     * Gets approval_url
     *
     * @return string
     */
    public function getApprovalUrl()
    {
        return $this->container['approval_url'];
    }

    /**
     * Sets approval_url
     *
     * @param string $approval_url approval_url
     *
     * @return $this
     */
    public function setApprovalUrl($approval_url)
    {
        $this->container['approval_url'] = $approval_url;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}

