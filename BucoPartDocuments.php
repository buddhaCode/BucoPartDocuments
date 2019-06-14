<?php

namespace BucoPartDocuments;

use Shopware\Bundle\AttributeBundle\Service\TypeMapping;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Models\Attribute\Document;

class BucoPartDocuments extends Plugin {
    
    const ATTRIBUTES = [
        Document::class => ['buco_is_part_document', 'buco_part_document_legacy_id'],
    ];

    const CACHE_LIST = [
        InstallContext::CACHE_TAG_TEMPLATE,
        InstallContext::CACHE_TAG_PROXY,
        InstallContext::CACHE_TAG_CONFIG,
        InstallContext::CACHE_TAG_ROUTER
    ];

    public function install(InstallContext $context)
    {
        $this->createAttributes();
        $this->addAcl();

        $context->scheduleClearCache(self::CACHE_LIST);
    }

    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache([InstallContext::CACHE_TAG_TEMPLATE]);
    }

    public function deactivate(DeactivateContext $context)
    {
        $context->scheduleClearCache([InstallContext::CACHE_TAG_TEMPLATE]);
    }

    public function uninstall(UninstallContext $context)
    {
        if(!$context->keepUserData()) {
            $this->removeAttributes();
            $this->removeAcl();
        }

        $context->scheduleClearCache(self::CACHE_LIST);
    }

    private function createAttributes()
    {
        /** @var \Shopware\Bundle\AttributeBundle\Service\CrudService $crudService */
        $crudService = $this->container->get('shopware_attribute.crud_service');
        $em = $this->container->get('models');

        $crudService->update(
            $em->getClassMetadata(Document::class)->getTableName(),
            'buco_is_part_document',
            TypeMapping::TYPE_BOOLEAN,
            [
                'label' => 'Ist Teildokument?',
                'helpText' => 'Dieses Dokument enthält nur ausgewählte Positionen der Bestellung.',
                'displayInBackend' => true,
            ]);

        $crudService->update(
            $em->getClassMetadata(Document::class)->getTableName(),
            'buco_part_document_legacy_id',
            TypeMapping::TYPE_INTEGER,
            [
                'label' => 'Teildokumente Legacy ID',
                'helpText' => 'ID dieses Teildokumentes vor der Datenmigration aus dem mbdus Plugin.',
                'displayInBackend' => false,
            ]);
    }

    private function removeAttributes()
    {
        $crudService = $this->container->get('shopware_attribute.crud_service');
        $em = $this->container->get('models');

        foreach (self::ATTRIBUTES as $modelClass => $attributes) {
            foreach ($attributes as $attribute) {
                try {
                    $crudService->delete($em->getClassMetadata($modelClass)->getTableName(), $attribute);
                }
                catch (\Exception $e) {
                    // ignore
                }
            }
        }
    }

    private function addAcl()
    {
        $aclService = $this->container->get('acl');
        $em = $this->container->get('models');

        /** @var \Shopware\Models\Plugin\Plugin $pluginModel */
        $pluginModel = $em->getRepository(\Shopware\Models\Plugin\Plugin::class)->findOneBy(['name' => $this->getName()]);

        try {
            $aclService->createResource(
                'bucopartdocumentsmigration',
                ['migrate'],
                null,
                $pluginModel ? $pluginModel->getId() : null
            );
        }
        catch (\Exception $e) {}
    }

    private function removeAcl()
    {
        $aclService = $this->container->get('acl');

        try {
            $aclService->deleteResource('bucopartdocumentsmigration');
        }
        catch (\Exception $e) {}
    }
}