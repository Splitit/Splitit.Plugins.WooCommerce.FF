<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
require_once(__DIR__ . '/../vendor/autoload.php');

use SplititSdkClient\Api\InstallmentPlanApi;
use SplititSdkClient\Api\LoginApi;
use SplititSdkClient\Configuration;
use SplititSdkClient\FlexFields;
use SplititSdkClient\Model\CancelInstallmentPlanRequest;
use SplititSdkClient\Model\GetInitiatedInstallmentPlanRequest;
use SplititSdkClient\Model\GetInstallmentsPlanSearchCriteriaRequest;
use SplititSdkClient\Model\InitiateInstallmentPlanRequest;
use SplititSdkClient\Model\LoginRequest;
use SplititSdkClient\Model\MoneyWithCurrencyCode;
use SplititSdkClient\Model\PaymentWizardData;
use SplititSdkClient\Model\PlanData;
use SplititSdkClient\Model\RedirectUrls;
use SplititSdkClient\Model\RefundPlanRequest;
use SplititSdkClient\Model\StartInstallmentsRequest;
use SplititSdkClient\Model\UpdateInstallmentPlanRequest;
use SplititSdkClient\Model\VerifyPaymentRequest;
use SplititSdkClient\Model\ConsumerData;
use SplititSdkClient\Model\AddressData;

/**
 * Class API
 */
class API
{
    protected $api_key;
    protected $username;
    protected $password;
    protected $environment;
    protected $session_id;
    protected $auto_capture;
    protected $secure_3d;
    protected $default_number_of_installments;

    /**
     * API constructor.
     * @param $settings
     * @param null $default_number_of_installments
     */
    public function __construct($settings, $default_number_of_installments = null)
    {
        $this->api_key = $settings['splitit_api_key'];
        $this->username = $settings['splitit_api_username'];
        $this->password = $settings['splitit_api_password'];
        $this->environment = $settings['splitit_environment'];
        $this->auto_capture = $settings['splitit_auto_capture'];
        $this->secure_3d = $settings['splitit_settings_3d'];
        $this->default_number_of_installments = $default_number_of_installments ?? 0;

        if (!$this->session_id) {
            $this->login();
        }
    }


    /**
     * Login method
     * @param false $check_credentials
     * @return array[]|string
     */
    public function login($check_credentials = false)
    {

        $environment = $this->set_api_key();

        $loginApi = new LoginApi($environment);

        try {
            $request = new LoginRequest();

            # Replace with your login information
            $request->setUserName($this->username);
            $request->setPassword($this->password);
            $loginResponse = $loginApi->loginPost($request);

            $session_id = $loginResponse->getSessionId();

            $this->session_id = $session_id ?? null;
            if (is_null($session_id)) {
                $message = 'Login session id is null';
                $data = [
                    'user_id' => get_current_user_id(),
                    'method' => 'Method login API',
                    'message' => $message
                ];
                Log::save_log_info($data, $message, 'error');
            }

            return $session_id;

        } catch (Exception $e) {
            $message = 'Error. File - ' . $e->getFile() . ', message - ' . $e->getMessage() . ', row' . $e->getLine();
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'Method login API',
                'message' => $message
            ];
            Log::save_log_info($data, $message, 'error');

            return ['error' => ['message' => $e->getMessage()]];
        }
    }

    /**
     * Initiate method
     * @param $data
     * @return false|string
     */
    public function initiate($data)
    {
        try {
            $session_id = $this->login();
            $environment = $this->set_api_key();

            $environment->setTouchPoint(['Code' => 'WooCommercePlugin', 'Version' => 'v3.0']);


            if (isset($session_id) && !isset($session_id['error'])) {
                $installmentPlanApi = new InstallmentPlanApi(
                    $environment,
                    $session_id
                );

                $installmentPlanApi->setCulture(str_replace("_", "-", get_locale()));

                $initiateRequest = new InitiateInstallmentPlanRequest();

                if(isset($data['ipn'])) {
                    $initiateRequest->setInstallmentPlanNumber($data['ipn']);
                }

                $planData = new PlanData();

                $planData->setNumberOfInstallments($data['numberOfInstallments']);
                $planData->setAmount(new MoneyWithCurrencyCode(["value" => number_format($data['amount'], 2, '.', ''), "currency_code" => $data['currency_code']]));
                $planData->setAutoCapture((bool)$this->auto_capture);
                $planData->setAttempt3DSecure((bool)$this->secure_3d);

                $full_installments = $data['installments'];

                $paymentWizard = new PaymentWizardData();
                $successAsyncUrl = site_url() . '/wc-api/splitit_payment_success_async';
                $paymentWizard->setSuccessAsyncUrl($successAsyncUrl);
                $paymentWizard->setRequestedNumberOfInstallments(implode(',', $full_installments));

                if(isset($data['billingAddress'])) {
                    $billingAddress = new AddressData(array(
                        "address_line" => $data['billingAddress']['AddressLine'],
                        "address_line2" => $data['billingAddress']['AddressLine2'],
                        "city" => $data['billingAddress']['City'],
                        "state" => $data['billingAddress']['State'],
                        "country" => $data['billingAddress']['Country'],
                        "zip" => $data['billingAddress']['Zip']
                    ));
                }
                if(isset($data['consumerData'])) {
                    $consumerData = new ConsumerData(array(
                        "full_name" => $data['consumerData']['FullName'],
                        "email" => $data['consumerData']['Email'],
                        "phone_number" => $data['consumerData']['PhoneNumber'],
                        "culture_name" => $data['consumerData']['CultureName'],
                    ));
                }


                $initiateRequest->setPlanData($planData)
                    ->setPaymentWizardData($paymentWizard);

                if(isset($data['billingAddress'])) {
                    $initiateRequest->setBillingAddress($billingAddress);
                }
                if(isset($data['consumerData'])) {
                    $initiateRequest->setConsumerData($consumerData);
                }

                $initiateResponse = $installmentPlanApi->installmentPlanInitiate($initiateRequest);

                $success = $initiateResponse->getResponseHeader()->getSucceeded();

                if ($success) {
                    $fieldData = [
                        "installmentPlan" => json_decode($initiateResponse->getInstallmentPlan()),
                        "privacyPolicyUrl" => $initiateResponse->getPrivacyPolicyUrl(),
                        "termsAndConditionsUrl" => $initiateResponse->getTermsAndConditionsUrl(),
                        "approvalUrl" => $initiateResponse->getApprovalUrl(),
                        "publicToken" => $initiateResponse->getPublicToken(),
                        "checkoutUrl" => $initiateResponse->getCheckoutUrl(),
                        "installmentPlanInfoUrl" => $initiateResponse->getInstallmentPlanInfoUrl(),
                    ];

                    $message = 'Successful initiate';
                    $data = [
                        'user_id' => get_current_user_id(),
                        'method' => 'Method initiate API',
                        'message' => $message
                    ];
                    Log::save_log_info($data, $message, 'info');

                    return json_encode($fieldData);
                } else {
                    $message = 'Failed initiate';
                    $data = [
                        'user_id' => get_current_user_id(),
                        'method' => 'Method initiate API',
                        'message' => $message
                    ];
                    Log::save_log_info($data, $message, 'error');
                    $error_data = ['error' => ['message' => $message]];

                    return json_encode($error_data);
                }
            } else {
                $message = 'Initiate failed login';
                $data = [
                    'user_id' => get_current_user_id(),
                    'method' => 'Method initiate API',
                    'message' => $message
                ];
                Log::save_log_info($data, $message, 'error');
                $error_data = ['error' => ['message' => $message]];

                return json_encode($error_data);
            }

        } catch (Exception $e) {
            $message = 'Error. File - ' . $e->getFile() . ', message - ' . $e->getMessage() . ', row' . $e->getLine();
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'Method login API',
                'message' => $message
            ];
            Log::save_log_info($data, $message, 'error');

            return json_encode(['error' => ['message' => $e->getMessage()]]);
        }

    }

    /**
     * Update method
     * @param $order_id
     * @param $ipn
     * @return false|string
     */
    public function update($order_id, $ipn)
    {
        try {
            $apiInstance = $this->get_api_instance();

            $updateRequest = new UpdateInstallmentPlanRequest();
            $planData = new PlanData();
            $planData->setRefOrderNumber($order_id);
            $updateRequest->setInstallmentPlanNumber($ipn);
            $updateRequest->setPlanData($planData);

            $apiInstance->installmentPlanUpdate($updateRequest);

            $message = 'Update was successful';
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'update() API',
                'message' => $message
            ];
            Log::save_log_info($data, $message, 'info');

        } catch (Exception $e) {
            $message = 'Error. File - ' . $e->getFile() . ', message - ' . $e->getMessage() . ', row' . $e->getLine();
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'Method update API',
                'message' => $message
            ];
            Log::save_log_info($data, $message, 'error');

            return json_encode(['error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Flex fields initiate
     * @param int $amount
     * @param string $currencyCode
     * @param array $installments
     * @return array
     */
    public function get_flex_field_token($amount = 0, $currencyCode = 'USD', $installments = [])
    {
        $environment = $this->set_api_key();

        try {
            $ff = FlexFields::authenticate($environment, $this->username, $this->password);

            $full_installments = [];
            if (!empty($installments)) {
                $end = end($installments);
                for ($i = 1; $i <= (int)$end; $i++) {
                    $full_installments[] = $i;
                }
            }

            if (!empty($full_installments)) {
                $ff->addInstallments($full_installments, $this->default_number_of_installments);
            }
            $ff->addCaptureSettings((bool)$this->auto_capture);
            if ($this->secure_3d) {
                $ff->add3DSecure(new RedirectUrls([
                    'succeeded' => esc_url(wc_get_checkout_url()),
                    'canceled' => esc_url(wc_get_checkout_url()),
                    'failed' => esc_url(wc_get_checkout_url())
                ]));
            }
            $publicToken = $ff->getPublicToken($amount, $currencyCode);

            return ['success' => true, 'publicToken' => $publicToken];
        } catch (Exception $e) {
            $message = 'Error. File - ' . $e->getFile() . ', message - ' . $e->getMessage() . ', row' . $e->getLine();
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'Method get_flex_field_token API',
                'message' => $message
            ];
            Log::save_log_info($data, $message, 'error');

            return ['success' => false, 'error' => ['message' => $e->getMessage()]];
        }
    }

    /**
     * Refund method
     * @param null $amount
     * @param string $currency_code
     * @param array $spilitit_info
     * @return bool
     * @throws \SplititSdkClient\ApiException
     */
    public function refund($amount = null, $currency_code = '', $spilitit_info = [])
    {
        $apiInstance = $this->get_api_instance();

        $request = new RefundPlanRequest();

        $request->setInstallmentPlanNumber($spilitit_info->installment_plan_number);
        $request->setAmount(new MoneyWithCurrencyCode(["value" => number_format($amount, 2, '.', ''), "currency_code" => $currency_code]));
        $request->setRefundStrategy('FutureInstallmentsFirst');

        $response = $apiInstance->installmentPlanRefund($request);
        if ($response->getResponseHeader()->getSucceeded()) {
            $message = 'Refund was successful';
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'refund() API',
                'message' => $message
            ];
            Log::save_log_info($data, $message);

            return true;
        } else {
            throw new Exception('Refund unable to be processed online, consult your Splitit Account to process manually');
        }
    }

    /**
     * Method for getting instance
     * @return InstallmentPlanApi
     */
    public function get_api_instance()
    {
        $environment = $this->set_api_key();

        $environment->setTouchPoint(['Code' => 'WooCommercePlugin', 'Version' => 'v3.0']);

        $installment_plan_api = new InstallmentPlanApi(
            $environment,
            $this->session_id
        );

        $installment_plan_api->setCulture(str_replace("_", "-", get_locale()));

        return $installment_plan_api;
    }

    /**
     * Cancel method
     * @param $installmentPlanNumber
     * @param string $refundUnderCancelation
     * @return bool
     * @throws \SplititSdkClient\ApiException
     */
    public function cancel($installmentPlanNumber, $refundUnderCancelation = 'NoRefunds')
    {
        $apiInstance = $this->get_api_instance();

        $request = new CancelInstallmentPlanRequest();
        $request->setInstallmentPlanNumber($installmentPlanNumber);
        $request->setRefundUnderCancelation($refundUnderCancelation);

        $response = $apiInstance->installmentPlanCancel($request);
        if ($response->getResponseHeader()->getSucceeded()) {
            $message = 'Canceled was successful';
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'cancel() API',
                'message' => $message
            ];
            Log::save_log_info($data, $message);

            return true;
        } else {
            throw new Exception($response->getResponseHeader()->getErrors()[0]->getMessage());
        }
    }

    /**
     * Method for getting information by ipn
     * @param $installmentPlanNumber
     * @return \SplititSdkClient\Model\InstallmentPlan
     * @throws \SplititSdkClient\ApiException
     */
    public function get_ipn_info($installmentPlanNumber)
    {
        $apiInstance = $this->get_api_instance();

        $request = new GetInstallmentsPlanSearchCriteriaRequest();
        $request->setQueryCriteria(['InstallmentPlanNumber' => $installmentPlanNumber]);

        $response = $apiInstance->installmentPlanGet($request);
        if ($response->getResponseHeader()->getSucceeded()) {
            return $response->getPlansList()[0];
        } else {
            throw new Exception($response->getResponseHeader()->getErrors()[0]->getMessage());
        }
    }

    /**
     * Method for getting auto capture by ipn
     * @param $installmentPlanNumber
     * @return bool
     * @throws \SplititSdkClient\ApiException
     */
    public function get_auto_capture_by_ipn($installmentPlanNumber)
    {
        $apiInstance = $this->get_api_instance();

        $request = new GetInitiatedInstallmentPlanRequest();
        $request->setInstallmentPlanNumber($installmentPlanNumber);
        $response = $apiInstance->installmentPlanGetInitiatedInstallmentPlanRequest($request);
        if ($response->getResponseHeader()->getSucceeded()) {
            return (bool)$response->getPlanData()->getAutoCapture();
        } else {
            throw new Exception($response->getResponseHeader()->getErrors()[0]->getMessage());
        }
    }

    /**
     * StartInstallment method
     * @param $installmentPlanNumber
     * @return bool
     * @throws \SplititSdkClient\ApiException
     */
    public function start_installments($installmentPlanNumber)
    {
        $apiInstance = $this->get_api_instance();

        $request = new StartInstallmentsRequest();
        $request->setInstallmentPlanNumber($installmentPlanNumber);

        $response = $apiInstance->installmentPlanStartInstallments($request);
        if ($response->getResponseHeader()->getSucceeded()) {
            $message = 'StartInstallment was successful';
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'start_installments() API',
                'message' => $message
            ];
            Log::save_log_info($data, $message);

            return true;
        } else {
            throw new Exception($response->getResponseHeader()->getErrors()[0]->getMessage());
        }
    }

    /**
     * Verify method
     * @param $installmentPlanNumber
     * @return \SplititSdkClient\Model\VerifyPaymentResponse
     * @throws \SplititSdkClient\ApiException
     */
    public function verifyPayment($installmentPlanNumber)
    {
        $apiInstance = $this->get_api_instance();

        $request = new VerifyPaymentRequest();
        $request->setInstallmentPlanNumber($installmentPlanNumber);

        $response = $apiInstance->installmentPlanVerifyPayment($request);
        if ($response->getResponseHeader()->getSucceeded()) {
            $message = 'VerifyPayment was successful';
            $data = [
                'user_id' => get_current_user_id(),
                'method' => 'verifyPayment() API',
                'message' => $message
            ];
            Log::save_log_info($data, $message, 'info');

            return $response;
        } else {
            throw new Exception($response->getResponseHeader()->getErrors()[0]->getMessage());
        }
    }

    /**
     * Method for set api key
     * @return Configuration
     */
    private function set_api_key()
    {
        $environment = $this->get_configuration_by_environment();
        $environment->setApiKey($this->api_key);

        return $environment;
    }

    /**
     * Method for getting configuration
     * @return Configuration
     */
    public function get_configuration_by_environment()
    {
        if ($this->environment == 'sandbox') {
            return Configuration::sandbox();
        } else if ($this->environment == 'production') {
            return Configuration::production();
        }
    }
}
