<?php

namespace BucoPartDocuments\Subscriber;

use Doctrine\DBAL\Connection;
use Enlight\Event\SubscriberInterface;
use BucoPartDocuments\Services\Config;
use Enlight_Controller_Front;
use Shopware\Components\Model\ModelManager;

class Document implements SubscriberInterface
{
    /**
     * @var \Enlight_Controller_Front
     */
    private $front;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ModelManager
     */
    private $em;

    /**
     * @var \Enlight_Components_Snippet_Manager
     */
    private $snippet;


    public function __construct(
        \Enlight_Controller_Front $front,
        ModelManager $em,
        Config $config,
        \Enlight_Components_Snippet_Manager $snippet
    )
    {
        $this->front = $front;
        $this->config = $config;
        $this->em = $em;
        $this->snippet = $snippet;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Models_Document_Order::getPositions::after' => 'removeNotSelectedPositions',
            'Shopware_Components_Document::setConfig::before' => 'injectConfigFields',
            'Enlight_Controller_Action_Backend_Order_createDocument' => 'prohibitPartDocumentCreation',
            'Shopware\Models\Order\Repository::getDocuments::replace' => 'extendBasicOrderDocumentsList',
            'Shopware_Controllers_Backend_OrderState_Notify' => 'injectPartDocumentContextIntoSendMail',
        ];
    }

    public function injectPartDocumentContextIntoSendMail(\Enlight_Event_EventArgs $args)
    {
        if(!($args->get('mailname') === 'sORDERDOCUMENTS' || strpos($args->get('mailname'), 'document_') === 0))
            return;

        /** @var Enlight_Controller_Front $front */
        $front = $args->getSubject();

        if(!$front->Request()->has('bucoIsPartDocument'))
            return;

        $values = $args->getValues() ?: [];
        $values['bucoIsPartDocument'] = filter_var($front->Request()->getParam('bucoIsPartDocument'), FILTER_VALIDATE_BOOLEAN);
        $args->setValues($values);
    }

    public function extendBasicOrderDocumentsList(\Enlight_Hook_HookArgs $args)
    {
        $orderIds = $args->get('orderIds');

        $query = $this->em->createQueryBuilder();
        $query->select(['document', 'documentType', 'documentAttribute']);
        $query->from(\Shopware\Models\Order\Document\Document::class, 'document');
        $query->leftJoin('document.type', 'documentType');
        $query->leftJoin('document.attribute', 'documentAttribute');
        $query->where('IDENTITY(document.order) IN (:ids)');
        $query->setParameter(':ids', $orderIds, Connection::PARAM_INT_ARRAY);

        $args->setReturn($query->getQuery()->getArrayResult());
    }

    public function prohibitPartDocumentCreation(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $subject */
        $subject = $args->get('subject');
        $view = $subject->View();
        $request = $subject->Request();

        $prohibitDocTypeIds = is_array($this->config->get('prohibitDocTypeIds'))
            ? $this->config->get('prohibitDocTypeIds')
            : [$this->config->get('prohibitDocTypeIds')];

        if($request->getParam('bucoCreatePartDocument') && in_array($request->getParam('documentType'), $prohibitDocTypeIds)) {
            $docTypeProhibitMsg = $this->snippet
                ->getNamespace('backend/order/main')
                ->get('configuration/bucoDocTypeProhibitMsg');

            $view->assign([
                'success' => false,
                'message' => "<br>{$docTypeProhibitMsg}",
            ]);

            return true;
        }
    }

    /**
     * Inject document config, if the document class is initiated via backend controller.
     *
     * @param \Enlight_Hook_HookArgs $args
     */
    public function injectConfigFields(\Enlight_Hook_HookArgs $args)
    {
        if(!($this->front->Request()
            && strtolower($this->front->Request()->getModuleName()) == strtolower('backend')
            && strtolower($this->front->Request()->getControllerName()) == strtolower('order')
            && strtolower($this->front->Request()->getActionName()) == strtolower('createDocument'))
        )
            return;

        $config = $args->get('config');

        if(!isset($config['bucoCreatePartDocument'])) {
            $config['bucoCreatePartDocument'] = $this->front->Request()->getParam('bucoCreatePartDocument', false);
            $config['attributes']['bucoIsPartDocument'] = $this->front->Request()->getParam('bucoCreatePartDocument', false);
            $config['_allowMultipleDocuments'] = true;
        }

        if(!isset($config['bucoPartDocPosIds'])) {
            $config['bucoPartDocPosIds'] = $this->front->Request()->getParam('bucoPartDocPosIds', []);
        }

        $args->set('config', $config);
    }

    /**
     * Hooks getPositions function, if the partDocument flag is set. Non requested positions will be purged. Order amount
     * and stuff will get calculated afterwards in the processPositions() method. That's why I didn't use the
     * 'Shopware_Models_Order_Document_Filter_Parameters' event here. Using the event would need to recalc the amount.
     * Shipping is untouched.
     *
     * There is no way to access or manipulate the $config array before it comes to this execution point. The "summaryNet"
     * property is hijacked to inject the part document parameters, if the document generation is triggered programmatically
     * and not via a controller request (e.g. for 3rd party plugins). Since the "summaryNet" property isn't used for document
     * generation, this should be no problem.
     *
     * Example:
     *
     * \Shopware_Components_Document::initDocument($orderId, $docTypeId, [
     *       'netto' => false,
     *       'shippingCostsAsPosition' => true,
     *       '_renderer' => 'pdf',
     *       'bucoCreatePartDocument' => true,                  // used in "injectConfigFields" method
     *       'bucoPartDocPosIds' => [1,2],                      // used in "injectConfigFields" method
     *       '_allowMultipleDocuments' => true,                 // optional
     *       'attributes' => ['bucoIsPartDocument' => true],    // used to distinguish documents after creation
     *       'summaryNet' => [                                  // hijacked and used in "removeNotSelectedPositions" method
     *          'bucoCreatePartDocument' => true,
     *          'bucoPartDocPosIds' => [1,2]
     *       ]
     *   ]);
     *
     * Pull Request shopware/shopware#xxx pending to make this more accessible.
     *
     * @param \Enlight_Hook_HookArgs $args
     */
    public function removeNotSelectedPositions(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Models_Document_Order $subject */
        $subject = $args->getSubject();

        if(!(($this->front->Request()
                && $this->front->Request()->getParam('bucoCreatePartDocument')
                && strtolower($this->front->Request()->getModuleName()) == strtolower('backend')
                && strtolower($this->front->Request()->getControllerName()) == strtolower('order')
                && strtolower($this->front->Request()->getActionName()) == strtolower('createDocument'))
            || $subject->summaryNet['bucoCreatePartDocument'])
        )
            return;

        // Purge unselected positions
        $requestParam = $this->front->Request() && $this->front->Request()->getParam('bucoPartDocPosIds') ? $this->front->Request()->getParam('bucoPartDocPosIds') : null;
        $selectedPositionIds =  $requestParam ?: $subject->summaryNet['bucoPartDocPosIds'];
        $selectedPositions = array_filter($subject->positions->getArrayCopy(), function($pos) use ($selectedPositionIds) {
            return in_array($pos['id'], $selectedPositionIds);
        });

        $subject->positions->exchangeArray($selectedPositions ?: [['BucoPartDocumentsDummy' => 'Order must have at least one dummy position. Otherwise a blank document will be created. This position will removed in a documents/index.tpl block']]);

        // Reset hijacked flag
        // Bug: Resetting the flag is not possible. Magic setter prohibits setting of properties.
        //$subject->summaryNet = false;
    }
}