<?php

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Plugin\Plugin;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class Shopware_Controllers_Backend_BucoPartDocumentsMigration extends Shopware_Controllers_Backend_ExtJs
{
    const MBDUS_PLUGIN_NAME = 'MbdusPartDocuments';

    /** @var ModelManager */
    private $em;
    
    /** @var Connection */
    private $dbal;
    
    /** @var AbstractSchemaManager */
    private $schema;
    
    /** @var bool */
    private $tableFound;
    
    /** @var int */
    private $tableRecords;

    /** @var int */
    private $foundMigrated;

    protected function initAcl()
    {
        $this->addAclPermission('doMdbusMigration', 'migrate', 'Insufficient Permissions');
        $this->addAclPermission('removeMbdusPluginAndData', 'migrate', 'Insufficient Permissions');
    }

    public function preDispatch()
    {
        parent::preDispatch();

        $this->em = $this->container->get('models');
        $this->dbal = $this->container->get('dbal_connection');
        $this->schema = $this->dbal->getSchemaManager();

        $this->tableFound = $this->schema->tablesExist('mbdus_order_documents');
        $this->tableRecords = $this->tableFound ? (int) $this->dbal->fetchColumn('SELECT count(*) FROM mbdus_order_documents') : 0;
        $this->foundMigrated = $this->tableFound ? (int) $this->dbal->fetchColumn('SELECT count(*) FROM mbdus_order_documents mbdus INNER JOIN s_order_documents sw ON sw.hash = mbdus.hash') : 0;
    }

    public function getMbdusMigrationInfoAction()
    {
        /** @var Plugin $mbdusPlugin */
        $mbdusPlugin = $this->em->getRepository(Plugin::class)->findOneBy(['name' => self::MBDUS_PLUGIN_NAME]);

        $this->View()->assign('pluginFound', (bool) $mbdusPlugin);
        $this->View()->assign('pluginInstalled', $mbdusPlugin ? (bool) $mbdusPlugin->getInstalled() : false);
        $this->View()->assign('pluginActivated', $mbdusPlugin ? $mbdusPlugin->getActive() : false);
        $this->View()->assign('dataTableFound', $this->tableFound);
        $this->View()->assign('dataTableRecords', $this->tableRecords);
        $this->View()->assign('migratedRows', $this->foundMigrated);
    }
    
    public function doMdbusMigrationAction()
    {
        if(!$this->Request()->isPost()) {
            die('Method not allowed.');
        }

        try {
            $insertedRows = $this->dbal->exec('
                INSERT IGNORE INTO s_order_documents
                SELECT NULL, o.date, o.type, o.userID, o.orderID, o.amount, o.docID, o.hash FROM mbdus_order_documents o;
            ');

            // TODO use attribute column constant
            $insertedAttributeRows = $this->dbal->exec('
                INSERT IGNORE INTO s_order_documents_attributes (documentid, buco_is_part_document, buco_part_document_legacy_id) 
                SELECT n.ID, 1, o.ID FROM mbdus_order_documents o LEFT JOIN s_order_documents n ON n.hash = o.hash;
            ');

            $success = true;
        }
        catch (\Exception $e) {
            $success = false;
            $msg = $e->getMessage();
        }

        $this->View()->assign('success', $success);

        if($success) {
            $this->View()->assign('insertedRows', $insertedRows);
            $this->View()->assign('insertedAttributeRows', $insertedAttributeRows);
            $this->View()->assign('dataTableRecords', $this->tableRecords);
            $this->View()->assign('migratedRows', $this->foundMigrated);
        }
        else {
            $this->View()->assign('message', $msg);
        }
    }

    public function removeMbdusPluginAndDataAction()
    {
        if(!$this->Request()->isPost()) {
            die('Method not allowed.');
        }

        try {
            /** @var InstallerService $pluginManager */
            $pluginManager = $this->container->get('shopware_plugininstaller.plugin_manager');

            try {
                $plugin = $pluginManager->getPluginByName(self::MBDUS_PLUGIN_NAME);
                $pluginManager->uninstallPlugin($plugin);
            } catch (\Exception $e) {
            }

            try {
                $this->deletePath($pluginManager->getPluginPath(self::MBDUS_PLUGIN_NAME));
                $this->em->remove($plugin);
                $this->em->flush();
            } catch (\Exception $e) {
            }

            $this->dbal->exec('DROP TABLE mbdus_articles_categories;');
            $this->dbal->exec('DROP TABLE mbdus_articles_related_sort;');
            $this->dbal->exec('DROP TABLE mbdus_articles_similar_sort;');
            $this->dbal->exec('DROP TABLE mbdus_order;');
            $this->dbal->exec('DROP TABLE mbdus_order_billingaddress;');
            $this->dbal->exec('DROP TABLE mbdus_order_details;');
            $this->dbal->exec('DROP TABLE mbdus_order_details_att;');
            $this->dbal->exec('DROP TABLE mbdus_order_docids;');
            $this->dbal->exec('DROP TABLE mbdus_order_documents;');
            $this->dbal->exec('DROP TABLE mbdus_order_shippingaddress;');

            $success = true;
        }
        catch (\Exception $e) {
            $success = false;
            $msg = $e->getMessage();
        }

        $this->View()->assign('success', $success);

        if(!$success) {
            $this->View()->assign('message', $msg);
        }
    }

    private function deletePath($path)
    {
        $fs = new Filesystem();

        try {
            $fs->remove($path);
        } catch (IOException $e) {
            return false;
        }

        return true;
    }
}

