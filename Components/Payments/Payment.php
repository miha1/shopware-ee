<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

namespace WirecardShopwareElasticEngine\Components\Payments;

use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Routing\Router;
use Shopware\Components\Routing\RouterInterface;
use Shopware\Models\Shop\Shop;
use Shopware_Components_Config;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use UnknownTransactionTypeException;
use Wirecard\PaymentSdk\Config\Config;
use Wirecard\PaymentSdk\Config\PaymentMethodConfig;
use Wirecard\PaymentSdk\TransactionService;
use WirecardShopwareElasticEngine\WirecardShopwareElasticEngine;

abstract class Payment implements PaymentInterface
{
    const ACTION = 'WirecardElasticEnginePayment';

    const TRANSACTION_TYPE_AUTHORIZATION = 'authorization';
    const TRANSACTION_TYPE_PURCHASE = 'purchase';
    const TRANSACTION_TYPE_UNKNOWN = 'unknown';

    /**
     * @var Shopware_Components_Config
     */
    protected $shopwareConfig;

    public function __construct(Shopware_Components_Config $shopwareConfig)
    {
        $this->shopwareConfig = $shopwareConfig;
    }

    /**
     * @inheritdoc
     */
    public function getLabel()
    {
        return 'Wirecard EE ' . preg_replace('/Payment$/', '', get_class($this));
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return str_replace(' ', '_', strtolower($this->getLabel()));
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentOptions()
    {
        return [
            'name'                  => $this->getName(),
            'description'           => $this->getLabel(),
            'action'                => self::ACTION,
            'active'                => 0,
            'position'              => $this->getPosition(),
            'additionalDescription' => '',
        ];
    }

    /**
     * @return string
     */
    public function getTransactionType()
    {
        $type = $this->getPaymentConfig()->getTransactionType();

        switch ($type) {
            case 'pay':
                return Payment::TRANSACTION_TYPE_PURCHASE;

            case 'reserve':
                return Payment::TRANSACTION_TYPE_AUTHORIZATION;
        }

        throw new UnknownTransactionTypeException($type);
    }

    /**
     * @inheritdoc
     */
    public function getTransactionConfig(
        Shop $shop,
        ParameterBagInterface $parameterBag,
        InstallerService $installerService
    )
    {
        $config = new Config(
            $this->getPaymentConfig()->getBaseUrl(),
            $this->getPaymentConfig()->getHttpUser(),
            $this->getPaymentConfig()->getHttpPassword()
        );

        $config->setShopInfo(
            $parameterBag->get('kernel.name'),
            $parameterBag->get('shopware.release.version')
        );

        $plugin = $installerService->getPluginByName(WirecardShopwareElasticEngine::NAME);

        $config->setPluginInfo($plugin->getName(), $plugin->getVersion());

        return $config;
    }

    /**
     * @inheritdoc
     */
    public function processReturn(
        TransactionService $transactionService,
        \Enlight_Controller_Request_Request $request,
        RouterInterface $router
    ) {
        return null;
    }

    /**
     * adds request id to transaction model
     *
     * @params string $requestId
     *
     * @return boolean
     */
//    public function addTransactionRequestId($requestId)
//    {
//        if (! $this->orderNumber) {
//            return false;
//        }
//
//        $transactionModel = Shopware()->Models()
//                                      ->getRepository(OrderNumberAssignment::class)
//                                      ->findOneBy(['id' => $this->orderNumber]);
//
//        if (! $transactionModel) {
//            return false;
//        }
//        $transactionModel->setRequestId($requestId);
//        Shopware()->Models()->persist($transactionModel);
//        Shopware()->Models()->flush();
//
//        return true;
//    }

    /**
     * @inheritdoc
     */
//    public function getBackendOperations($transactionId)
//    {
//        $configData = $this->getConfigData();
//        $config     = $this->getConfig($configData);
//
//        $transaction = $this->getTransaction();
//        $transaction->setParentTransactionId($transactionId);
//        $service = new BackendService($config, Shopware()->PluginLogger());
//
//        return $service->retrieveBackendOperations($transaction, true);
//    }

    /**
     * @inheritdoc
     */
//    public function processBackendOperationsForOrder($orderNumber, $operation, $amount = 0, $currency = '')
//    {
//        if ($amount && ! $currency) {
//            return ['success' => false, 'msg' => 'AmountWithoutCurrency'];
//        }
//
//        if ($operation === 'Refund') {
//            return $this->refundForOrder($orderNumber, $amount, $currency);
//        }
//
//        if ($operation === 'Capture') {
//            return $this->captureForOrder($orderNumber, $amount, $currency);
//        }
//
//        if ($operation === 'Cancel') {
//            return $this->cancelOrder($orderNumber);
//        }
//
//        return ['success' => false, 'msg' => 'InvalidOperation'];
//    }

    /**
     * @param string $orderNumber
     * @param float  $amount
     * @param string $currency
     *
     * @return array
     */
//    protected function refundForOrder($orderNumber, $amount = 0, $currency = '')
//    {
//        $elasticEngineTransaction = Shopware()->Models()->getRepository(OrderNumberAssignment::class)
//                                              ->findOneBy(['orderNumber' => $orderNumber]);
//
//        $parentTransactionId = $elasticEngineTransaction->getTransactionId();
//        if (! $elasticEngineTransaction) {
//            return ['success' => false, 'msg' => 'NoTransactionFound'];
//        }
//
//        $configData = $this->getConfigData();
//        $config     = $this->getConfig($configData);
//
//        $transaction = $this->getTransaction();
//        $transaction->setParentTransactionId($parentTransactionId);
//        $notificationUrl = Shopware()->Front()->Router()->assemble([
//            'module'      => 'frontend',
//            'controller'  => 'WirecardElasticEnginePayment',
//            'action'      => 'notifyBackend',
//            'method'      => $this->getName(),
//            'transaction' => $parentTransactionId,
//            'forceSecure' => true,
//        ]);
//
//        $transaction->setNotificationUrl($notificationUrl);
//
//        if ($amount) {
//            $amountObj = new Amount($amount, $currency);
//            $transaction->setAmount($amountObj);
//        }
//
//        $transactionService = new TransactionService($config, Shopware()->PluginLogger());
//
//        try {
//            $response = $transactionService->process($transaction, Operation::CANCEL);
//        } catch (\Exception $exception) {
//            Shopware()->PluginLogger()->error('Processing refund failed: ' .
//                                              get_class($exception) . ' ' .
//                                              $exception->getMessage());
//            return ['success' => false, 'msg' => 'RefundFailed'];
//        }
//
//        if ($response instanceof SuccessResponse) {
//            Shopware()->PluginLogger()->info($response->getData());
//            $transactionId         = $response->getTransactionId();
//            $providerTransactionId = $response->getProviderTransactionId() ? $response->getProviderTransactionId() : '';
//
//            $orderTransaction = Shopware()->Models()->getRepository(Transaction::class)
//                                          ->findOneBy(['transactionId'       => $parentTransactionId,
//                                                       'parentTransactionId' => $transactionId,
//                                          ]);
//
//            if (! $orderTransaction) {
//                $orderTransaction = new Transaction();
//                $orderTransaction->setOrderNumber($orderNumber);
//                $orderTransaction->setParentTransactionId($parentTransactionId);
//                $orderTransaction->setTransactionId($transactionId);
//                $orderTransaction->setProviderTransactionId($providerTransactionId);
//                $orderTransaction->setCreatedAt(new \DateTime('now'));
//                $orderTransaction->setTransactionType('pending');
//            }
//
//            $orderTransaction->setReturnResponse(serialize($response->getData()));
//
//            Shopware()->Models()->persist($orderTransaction);
//            Shopware()->Models()->flush();
//
//            return ['success' => true, 'transactionId' => $response->getTransactionId()];
//        }
//        if ($response instanceof FailureResponse) {
//            $rawData          = $response->getData();
//            $transactionId    = $rawData['transaction-id'];
//            $orderTransaction = Shopware()->Models()->getRepository(Transaction::class)
//                                          ->findOneBy(['transactionId'       => $parentTransactionId,
//                                                       'parentTransactionId' => $transactionId,
//                                          ]);
//            if (! $orderTransaction) {
//                $orderTransaction = new Transaction();
//                $orderTransaction->setOrderNumber($orderNumber);
//                $orderTransaction->setParentTransactionId($parentTransactionId);
//                $orderTransaction->setTransactionId($transactionId);
//                $orderTransaction->setCreatedAt(new \DateTime('now'));
//                $orderTransaction->setTransactionType('failed');
//            }
//
//            $orderTransaction->setReturnResponse(serialize($response->getData()));
//
//            Shopware()->Models()->persist($orderTransaction);
//            Shopware()->Models()->flush();
//            return ['success' => false, 'msg' => 'RefundFailed'];
//        }
//
//        return ['success' => false, 'msg' => 'RefundFailed'];
//    }

    /**
     * @param string $orderNumber
     * @param float  $amount
     * @param string $currency
     *
     * @return array
     */
//    protected function captureForOrder($orderNumber, $amount = 0, $currency = '')
//    {
//        $elasticEngineTransaction = Shopware()->Models()->getRepository(OrderNumberAssignment::class)
//                                              ->findOneBy(['orderNumber' => $orderNumber]);
//
//        $parentTransactionId = $elasticEngineTransaction->getTransactionId();
//        if (! $elasticEngineTransaction) {
//            return ['success' => false, 'msg' => 'NoTransactionFound'];
//        }
//
//        $configData = $this->getConfigData();
//        $config     = $this->getConfig($configData);
//
//        $transaction = $this->getTransaction();
//        $transaction->setParentTransactionId($parentTransactionId);
//        $notificationUrl = Shopware()->Front()->Router()->assemble([
//            'module'      => 'frontend',
//            'controller'  => 'WirecardElasticEnginePayment',
//            'action'      => 'notifyBackend',
//            'method'      => $this->getName(),
//            'transaction' => $parentTransactionId,
//            'forceSecure' => true,
//        ]);
//
//        $transaction->setNotificationUrl($notificationUrl);
//
//        if ($amount) {
//            $amountObj = new Amount($amount, $currency);
//            $transaction->setAmount($amountObj);
//        }
//
//        $transactionService = new BackendService($config, Shopware()->PluginLogger());
//
//        try {
//            $response = $transactionService->process($transaction, Operation::PAY);
//        } catch (\Exception $exception) {
//            Shopware()->PluginLogger()->error('Processing capture failed:' . $exception->getMessage());
//            return ['success' => false, 'msg' => 'CaptureFailed'];
//        }
//
//        if ($response instanceof SuccessResponse) {
//            Shopware()->PluginLogger()->info($response->getData());
//            $transactionId         = $response->getTransactionId();
//            $providerTransactionId = $response->getProviderTransactionId() ? $response->getProviderTransactionId() : '';
//
//            $orderTransaction = Shopware()->Models()->getRepository(Transaction::class)
//                                          ->findOneBy(['transactionId'       => $parentTransactionId,
//                                                       'parentTransactionId' => $transactionId,
//                                          ]);
//
//            if (! $orderTransaction) {
//                $orderTransaction = new Transaction();
//                $orderTransaction->setOrderNumber($orderNumber);
//                $orderTransaction->setParentTransactionId($parentTransactionId);
//                $orderTransaction->setTransactionId($transactionId);
//                $orderTransaction->setProviderTransactionId($providerTransactionId);
//                $orderTransaction->setCreatedAt(new \DateTime('now'));
//                $orderTransaction->setTransactionType('pending');
//            }
//
//            $orderTransaction->setReturnResponse(serialize($response->getData()));
//
//            Shopware()->Models()->persist($orderTransaction);
//            Shopware()->Models()->flush();
//
//            return ['success' => true, 'transactionId' => $response->getTransactionId()];
//        }
//        if ($response instanceof FailureResponse) {
//            $rawData          = $response->getData();
//            $transactionId    = $rawData['transaction-id'];
//            $orderTransaction = Shopware()->Models()->getRepository(Transaction::class)
//                                          ->findOneBy(['transactionId'       => $parentTransactionId,
//                                                       'parentTransactionId' => $transactionId,
//                                          ]);
//            if (! $orderTransaction) {
//                $orderTransaction = new Transaction();
//                $orderTransaction->setOrderNumber($orderNumber);
//                $orderTransaction->setParentTransactionId($parentTransactionId);
//                $orderTransaction->setTransactionId($transactionId);
//                $orderTransaction->setCreatedAt(new \DateTime('now'));
//                $orderTransaction->setTransactionType('failed');
//            }
//
//            $orderTransaction->setReturnResponse(serialize($response->getData()));
//
//            Shopware()->Models()->persist($orderTransaction);
//            Shopware()->Models()->flush();
//            return ['success' => false, 'msg' => 'CaptureFailed'];
//        }
//
//        return ['success' => false, 'msg' => 'CaptureFailed'];
//    }

    /**
     * @param string $orderNumber
     *
     * @return array
     */
//    protected function cancelOrder($orderNumber)
//    {
//        $elasticEngineTransaction = Shopware()->Models()->getRepository(OrderNumberAssignment::class)
//                                              ->findOneBy(['orderNumber' => $orderNumber]);
//
//        $parentTransactionId = $elasticEngineTransaction->getTransactionId();
//        if (! $elasticEngineTransaction) {
//            return ['success' => false, 'msg' => 'NoTransactionFound'];
//        }
//
//        $configData = $this->getConfigData();
//        $config     = $this->getConfig($configData);
//
//        $transaction = $this->getTransaction();
//        $transaction->setParentTransactionId($parentTransactionId);
//        $notificationUrl = Shopware()->Front()->Router()->assemble([
//            'module'      => 'frontend',
//            'controller'  => 'WirecardElasticEnginePayment',
//            'action'      => 'notifyBackend',
//            'method'      => $this->getName(),
//            'transaction' => $parentTransactionId,
//            'forceSecure' => true,
//        ]);
//
//        $transaction->setNotificationUrl($notificationUrl);
//
//        $transactionService = new BackendService($config, Shopware()->PluginLogger());
//
//        try {
//            $response = $transactionService->process($transaction, Operation::CANCEL);
//        } catch (\Exception $exception) {
//            Shopware()->PluginLogger()->error('Processing cancel failed:' . $exception->getMessage());
//            return ['success' => false, 'msg' => 'CancelFailed'];
//        }
//
//        if ($response instanceof SuccessResponse) {
//            Shopware()->PluginLogger()->info($response->getData());
//            $transactionId         = $response->getTransactionId();
//            $providerTransactionId = $response->getProviderTransactionId() ? $response->getProviderTransactionId() : '';
//
//            $orderTransaction = Shopware()->Models()->getRepository(Transaction::class)
//                                          ->findOneBy(['transactionId'       => $parentTransactionId,
//                                                       'parentTransactionId' => $transactionId,
//                                          ]);
//
//            if (! $orderTransaction) {
//                $orderTransaction = new Transaction();
//                $orderTransaction->setOrderNumber($orderNumber);
//                $orderTransaction->setParentTransactionId($parentTransactionId);
//                $orderTransaction->setTransactionId($transactionId);
//                $orderTransaction->setProviderTransactionId($providerTransactionId);
//                $orderTransaction->setCreatedAt(new \DateTime('now'));
//                $orderTransaction->setTransactionType('pending');
//            }
//
//            $orderTransaction->setReturnResponse(serialize($response->getData()));
//
//            Shopware()->Models()->persist($orderTransaction);
//            Shopware()->Models()->flush();
//
//            return ['success' => true, 'transactionId' => $response->getTransactionId()];
//        }
//        if ($response instanceof FailureResponse) {
//            $rawData          = $response->getData();
//            $transactionId    = $rawData['transaction-id'];
//            $orderTransaction = Shopware()->Models()->getRepository(Transaction::class)
//                                          ->findOneBy(['transactionId'       => $parentTransactionId,
//                                                       'parentTransactionId' => $transactionId,
//                                          ]);
//            if (! $orderTransaction) {
//                $orderTransaction = new Transaction();
//                $orderTransaction->setOrderNumber($orderNumber);
//                $orderTransaction->setParentTransactionId($parentTransactionId);
//                $orderTransaction->setTransactionId($transactionId);
//                $orderTransaction->setCreatedAt(new \DateTime('now'));
//                $orderTransaction->setTransactionType('failed');
//            }
//
//            $orderTransaction->setReturnResponse(serialize($response->getData()));
//
//            Shopware()->Models()->persist($orderTransaction);
//            Shopware()->Models()->flush();
//            return ['success' => false, 'msg' => 'CancelFailed'];
//        }
//
//        return ['success' => false, 'msg' => 'CancelFailed'];
//    }

    /**
     * @param string                     $name
     * @param string                     $prefix
     *
     * @return string
     */
    protected function getPluginConfig($name, $prefix = 'wirecardElasticEngine')
    {
        return $this->shopwareConfig->getByNamespace(WirecardShopwareElasticEngine::NAME, $prefix . $name);
    }
}
