<?php

namespace BucoPartDocuments\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Components_Snippet_Namespace;
use Exception;
use Shopware\Components\StateTranslatorService;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class OrderBatchProcess implements SubscriberInterface
{
    protected $controllerSubject;
    protected $reflectedPrivateMethods = [];

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_Backend_Order_BatchProcess' => 'decorateBatchProcessAction',
        ];
    }

    public function decorateBatchProcessAction(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $subject */
        $subject = $this->controllerSubject = $args->get('subject');
        $this->makePrivateFunctionAccessible($subject, ['getCurrentLocale', 'checkOrderStatus', 'createDocument']);

        $autoSend = $subject->Request()->getParam('autoSend') === 'true';
        $orders = $subject->Request()->getParam('orders', [0 => $subject->Request()->getParams()]);
        $documentType = $subject->Request()->getParam('docType');
        $documentMode = $subject->Request()->getParam('mode');
        $addAttachments = $subject->Request()->getParam('addAttachments') === 'true';

        /** @var Enlight_Components_Snippet_Namespace $namespace */
        $namespace = $subject->get('snippets')->getNamespace('backend/order');

        if (empty($orders)) {
            $subject->View()->assign([
                'success' => false,
                'data' => $subject->Request()->getParams(),
                'message' => $namespace->get('no_order_id_passed', 'No valid order id passed.'),
            ]);

            return true; // MODIFICATION: changed returning value void to true
        }

        $modelManager = $subject->get('models');
        /** @var \Shopware\Components\StateTranslatorServiceInterface $stateTranslator */
        $stateTranslator = $subject->get('shopware.components.state_translator');

        $previousLocale = $this->getCurrentLocale();

        foreach ($orders as &$data) {
            $data['success'] = false;
            $data['errorMessage'] = $namespace->get('no_order_id_passed', 'No valid order id passed.');

            if (empty($data) || empty($data['id'])) {
                continue;
            }

            /** @var \Shopware\Models\Order\Order $order */
            $order = $modelManager->find(Order::class, $data['id']);
            if (!$order) {
                continue;
            }

            /*
                We have to flush the status changes directly, because the "createStatusMail" function in the
                sOrder.php core class, use the order data from the database. So we have to save the new status before we
                create the status mail
            */
            $statusBefore = $order->getOrderStatus();
            $clearedBefore = $order->getPaymentStatus();

            // Refresh the status models to return the new status data which will be displayed in the batch list
            if (!empty($data['status']) || $data['status'] === 0) {
                $order->setOrderStatus($modelManager->find(Status::class, $data['status']));
            }
            if (!empty($data['cleared'])) {
                $order->setPaymentStatus($modelManager->find(Status::class, $data['cleared']));
            }

            try {
                $modelManager->flush($order);
            } catch (Exception $e) {
                $data['success'] = false;
                $data['errorMessage'] = sprintf(
                    $namespace->get('save_order_failed', 'Error when saving the order. Error: %s'),
                    $e->getMessage()
                );
                continue;
            }

            /*
                The setOrder function of the Shopware_Components_Document change the currency of the shop.
                This would create a new Shop if we execute an flush(); Only create order documents when requested.
            */
            if ($documentType) {
                $this->createOrderDocuments($documentType, $documentMode, $order);
            }

            if ($previousLocale) {
                // This is necessary, since the "checkOrderStatus" method might change the locale due to translation issues
                // when sending an order status mail. Therefore we reset it here to the chosen backend language.
                $subject->get('snippets')->setLocale($previousLocale);
                $subject->get('snippets')->resetShop();
            }

            $data['paymentStatus'] = $stateTranslator->translateState(StateTranslatorService::STATE_PAYMENT, $modelManager->toArray($order->getPaymentStatus()));
            $data['orderStatus'] = $stateTranslator->translateState(StateTranslatorService::STATE_ORDER, $modelManager->toArray($order->getOrderStatus()));

            try {
                // The method '$this->checkOrderStatus()' (even its name would not imply that) sends mails and can fail
                // with an exception. Catch this exception, so the batch process does not abort.
                $data['mail'] = $this->checkOrderStatus($order, $statusBefore, $clearedBefore, $autoSend, $documentType, $addAttachments);
            } catch (\Exception $e) {
                $data['mail'] = null;
                $data['success'] = false;
                $data['errorMessage'] = sprintf(
                    $namespace->get('send_mail_failed', 'Error when sending mail. Error: %s'),
                    $e->getMessage()
                );
                continue;
            }

            $data['success'] = true;
            $data['errorMessage'] = null;
        }

        $subject->View()->assign([
            'success' => true,
            'data' => $orders,
        ]);

        return true; // MODIFICATION: execution of original shopware action
    }

    private function createOrderDocuments($documentTypeId, $documentMode, $order)
    {
        if (!empty($documentTypeId)) {
            $documentTypeId = (int) $documentTypeId;
            $documentMode = (int) $documentMode;

            /** @var \Doctrine\Common\Collections\ArrayCollection $documents */
            $documents = $order->getDocuments();
            // MODIFICATION: return only full documents
            $documents = $documents->filter(function($document) {
                return !($document->getAttribute() && $document->getAttribute()->getBucoIsPartDocument());
            });

            // Create only not existing documents
            if ($documentMode === 1) {
                $alreadyCreated = false;
                foreach ($documents as $document) {
                    if ($document->getTypeId() === $documentTypeId) {
                        $alreadyCreated = true;
                        break;
                    }
                }
                if ($alreadyCreated === false) {
                    $this->createDocument($order->getId(), $documentTypeId);
                }
            } else {
                $this->createDocument($order->getId(), $documentTypeId);
            }
        }
    }

    /**
     * @param $context object
     * @param string[] $methods
     *
     * @throws \ReflectionException
     */
    private function makePrivateFunctionAccessible($context, array $methods)
    {
        $reflection = new \ReflectionClass($context);
        foreach ($methods as $method) {
            $reflectedMethod = $reflection->getMethod($method);
            $reflectedMethod->setAccessible(true);
            $this->reflectedPrivateMethods[$reflectedMethod->getShortName()] = $reflectedMethod;
        }
    }

    /**
     * Extract short method name from class-method identifier.
     *
     * @param string $fullMethodReference \FQCN::methodName ,e.g. from __METHOD__ magic constant
     *
     * @return \ReflectionMethod
     */
    protected function getReflectedMethod(string $fullMethodReference) : \ReflectionMethod
    {
        $shortName = substr($fullMethodReference, strpos($fullMethodReference, '::') + 2);
        return $this->reflectedPrivateMethods[$shortName];
    }

    private function getCurrentLocale()
    {
        return $this->getReflectedMethod(__METHOD__)->invoke($this->controllerSubject);
    }

    private function checkOrderStatus($order, $statusBefore, $clearedBefore, $autoSend, $documentTypeId, $addAttachments)
    {
        return $this->getReflectedMethod(__METHOD__)->invoke($this->controllerSubject, $order, $statusBefore, $clearedBefore, $autoSend, $documentTypeId, $addAttachments);
    }

    private function createDocument($orderId, $documentType)
    {
        return $this->getReflectedMethod(__METHOD__)->invoke($this->controllerSubject, $orderId, $documentType);
    }
}