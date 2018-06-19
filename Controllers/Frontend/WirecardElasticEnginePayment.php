<?php
/**
 * Shop System Plugins - Terms of Use
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Status;

use Wirecard\PaymentSdk\Config\Config;
use Wirecard\PaymentSdk\Config\PaymentMethodConfig;
use Wirecard\PaymentSdk\Entity\Amount;
use Wirecard\PaymentSdk\Entity\Redirect;
use Wirecard\PaymentSdk\Response\FailureResponse;
use Wirecard\PaymentSdk\Response\SuccessResponse;
use Wirecard\PaymentSdk\Transaction\PayPalTransaction;
use Wirecard\PaymentSdk\TransactionService;

use WirecardShopwareElasticEngine\Components\StatusCodes;
use WirecardShopwareElasticEngine\Components\Payments\PaypalPayment;

use WirecardShopwareElasticEngine\Models\Transaction;

class Shopware_Controllers_Frontend_WirecardElasticEnginePayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /**
     * Index Action starting payment - redirect to method
     */
    public function indexAction()
    {
        if ($this->getPaymentShortName() == 'wirecard_elastic_engine_paypal') {
            return $this->redirect(['action' => 'paypal', 'forceSecure' => true]);
        }

        return $this->errorHandling(StatusCodes::ERROR_NOT_A_VALID_METHOD);
    }

    /**
     * Starts transaction with PayPal.
     * User gets redirected to Paypal payment page.
     */
    public function paypalAction()
    {
        if (!$this->validateBasket()) {
            return $this->redirect([
                'controller'                        => 'checkout',
                'action'                            => 'cart',
                'wirecard_elast_engine_update_cart' => 'true'
            ]);
        }
        
        $paymentData = $this->getPaymentData('paypal');

        $paypal = new PaypalPayment();

        $paymentProcess = $paypal->processPayment($paymentData);

        if ($paymentProcess['status'] === 'success') {
            return $this->redirect($paymentProcess['redirect']);
        } else {
            $this->errorHandling(StatusCodes::ERROR_STARTING_PROCESS_FAILED);
        }
    }

    /**
     * After paying user gets redirected to this action.
     * The order gets saved (if not already existing through notification).
     * Required parameter:
     *  (string) method
     *  Wirecard\PaymentSdk\Response
     */
    public function returnAction()
    {
        $request = $this->Request()->getParams();

        if (!isset($request['method'])) {
            return $this->errorHandling(StatusCodes::ERROR_NOT_A_VALID_METHOD);
        }

        $response = null;
        if ($request['method'] === 'paypal') {
            $paypal = new PaypalPayment();
            $response = $paypal->getPaymentResponse($request);
        }
        
        if (!$response) {
            return $this->errorHandling(StatusCodes::ERROR_NOT_A_VALID_METHOD);
        }

        if ($response instanceof SuccessResponse) {
            $transactionType = $response->getTransactionType();
            $customFields = $response->getCustomFields();

            $transactionId = $response->getTransactionId();
            $paymentUniqueId = $response->getProviderTransactionId();
            $signature = $customFields->get('signature');

            $wirecardOrderNumber = $response->findElement('order-number');
            
            $elasticEngineTransaction = Shopware()->Models()->getRepository(Transaction::class)->findOneBy(['id' => $wirecardOrderNumber]);
            $elasticEngineTransaction->setTransactionId($transactionId);
            $elasticEngineTransaction->setProviderTransactionId($paymentUniqueId);
            $elasticEngineTransaction->setReturnResponse(serialize($response->getData()));
            $paymentStatus = intval($elasticEngineTransaction->getPaymentStatus());

            $sql = '
                SELECT id FROM s_order
                WHERE transactionID=? AND temporaryID=?
                AND status!=-1
            ';

            $orderId = Shopware()->Db()->fetchOne($sql, [
                $transactionId,
                $paymentUniqueId,
            ]);

            if ($orderId) {
                Shopware()->Models()->persist($elasticEngineTransaction);
                Shopware()->Models()->flush();
                return $this->redirect([
                    'module' => 'frontend',
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => $paymentUniqueId
                ]);
            }
            try {
                $basket = $this->loadBasketFromSignature($signature);
                if ($paymentStatus) {
                    $orderNumber = $this->saveOrder($transactionId, $paymentUniqueId, $paymentStatus);
                } else {
                    $orderNumber = $this->saveOrder($transactionId, $paymentUniqueId);
                }

                $elasticEngineTransaction->setOrderNumber($orderNumber);
                Shopware()->Models()->persist($elasticEngineTransaction);
                Shopware()->Models()->flush();

                return $this->redirect([
                    'module' => 'frontend',
                    'controller' => 'checkout',
                    'action' => 'finish',
                ]);
            } catch (RuntimeException $e) {
                Shopware()->PluginLogger()->error($e->getMessage());
                $this->errorHandling(StatusCodes::ERROR_CRITICAL_NO_ORDER);
            }
        } elseif ($response instanceof FailureResponse) {
            Shopware()->PluginLogger()->error('Response validation status: %s', $response->isValidSignature() ? 'true' : 'false');

            foreach ($response->getStatusCollection() as $status) {
                $severity = ucfirst($status->getSeverity());
                $code = $status->getCode();
                $description = $status->getDescription();
                $errorMessage = sprintf('%s with code %s and message "%s" occurred.', $severity, $code, $description);
                Shopware()->PluginLogger()->error($errorMessage);
            }
        }
        $this->errorHandling(StatusCodes::ERROR_FAILURE_RESPONSE);
    }

    /**
     * User gets redirected to this action after canceling payment.
     */
    public function cancelAction()
    {
        $this->errorHandling(StatusCodes::CANCLED_BY_USER);
    }

    /**
     * This method handles errors.
     * @see StatusCodes
     *
     * @param int $code
     */
    protected function errorHandling($code)
    {
        $this->redirect([
            'controller'                       => 'checkout',
            'action'                           => 'shippingPayment',
            'wirecard_elast_engine_error_code' => $code
        ]);
    }

    /**
     * The action gets called by Server after payment.
     * If not already existing the order gets saved here.
     * order gets its finale state.
     */
    public function notifyAction()
    {
        $request = $this->Request()->getParams();
        $notification = file_get_contents("php://input");

        Shopware()->PluginLogger()->info("Notifiation: " . $notification);
        
        $response = null;
        
        if ($request['method'] === 'paypal') {
            $paypal = new PaypalPayment();
            $response = $paypal->getPaymentNotification($notification);
        }

        if (!$response) {
            echo "no response";
            Shopware()->PluginLogger()->error("no response");
            //return $this->errorHandling(StatusCodes::ERROR_NOT_A_VALID_METHOD);
        }

        if ($response instanceof SuccessResponse) {
            $transactionId = $response->getTransactionId();
            $paymentUniqueId = $response->getProviderTransactionId();
            $transactionType = $response-> getTransactionType();

            $wirecardOrderNumber = $response->findElement('order-number');
            
            if ($transactionType === 'authorization') {
                $paymentStatusId = Status::PAYMENT_STATE_RESERVED;
            } else {
                $paymentStatusId = Status::PAYMENT_STATE_COMPLETELY_PAID;
            }
        
            $elasticEngineTransaction = Shopware()->Models()->getRepository(Transaction::class)->findOneBy(['id' => $wirecardOrderNumber]);
            $elasticEngineTransaction->setTransactionId($transactionId);
            $elasticEngineTransaction->setProviderTransactionId($paymentUniqueId);
            $elasticEngineTransaction->setNotificationResponse(serialize($response->getData()));
            $elasticEngineTransaction->setPaymentStatus($paymentStatusId);
            Shopware()->Models()->persist($elasticEngineTransaction);
            Shopware()->Models()->flush();

            $sql = '
                SELECT id FROM s_order
                WHERE transactionID=? AND temporaryID=?
                AND status!=-1
            ';

            $orderId = Shopware()->Db()->fetchOne($sql, [
                $transactionId,
                $paymentUniqueId,
            ]);

            if ($orderId) {
                $order = Shopware()->Modules()->Order()->getOrderById($orderId);

                if (intval($order['cleared']) === Status::PAYMENT_STATE_OPEN) {
                    Shopware()->PluginLogger()->info("set PaymentStatus for Order " . $orderId);
                    $this->savePaymentStatus($transactionId, $paymentUniqueId, $paymentStatusId, false);
                } else {
                    Shopware()->PluginLogger()->error("Order with ID " . $orderId .  " already set");
                    // payment state already set
                }
            }
        }
        exit();
    }

    /**
     * Important data of order for further processing in transaction get collected-
     *
     * @param string $method
     * @return array $paymentData
     */
    protected function getPaymentData($method)
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $amount = $this->getAmount();
        $currency = $this->getCurrencyShortName();
        $router = $this->Front()->Router();

        $paymentData = array(
            'user'      => $user,
            'ipAddr'    => $this->Request()->getClientIp(),
            'basket'    => $basket,
            'amount'    => $amount,
            'currency'  => $currency,
            'returnUrl' => $router->assemble(['action' => 'return', 'method' => $method, 'forceSecure' => true]),
            'cancelUrl' => $router->assemble(['action' => 'cancel', 'forceSecure' => true]),
            'notifyUrl' => $router->assemble(['action' => 'notify', 'method' => $method, 'forceSecure' => true]),
            'signature' => $this->persistBasket()
        );

        return $paymentData;
    }

    /**
     * Validate basket for availability of products.
     *
     * @return boolean
     */
    protected function validateBasket()
    {
        $basket = $this->getBasket();

        foreach ($basket['content'] as $item) {
            $article = Shopware()->Modules()->Articles()->sGetProductByOrdernumber($item['ordernumber']);

            if (!$article) {
                continue;
            }
            
            if (!$article['isAvailable'] || ($article['laststock'] && intval($item['quantity']) > $article['instock'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Whitelist notifyAction and returnAction
     */
    public function getWhitelistedCSRFActions()
    {
        return ['return', 'notify'];
    }
}
