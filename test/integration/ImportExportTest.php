<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * 
 * Copyright (c) 2008-2010 (original work) Deutsche Institut für Internationale Pädagogische Forschung (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 * 
 */

namespace oat\taoOpenWebItem\test\integration;

use oat\generis\model\GenerisRdf;
use oat\tao\model\TaoOntology;
use oat\tao\test\TaoPhpUnitTestRunner;
use oat\taoOpenWebItem\model\import\ImportService;
use oat\taoOpenWebItem\model\OwiItemCompiler;
use tao_models_classes_GenerisService;
use taoItems_models_classes_ItemsService;
use oat\taoOpenWebItem\model\OwiItemModel;

include_once dirname(__FILE__) . '/../../includes/raw_start.php';

/**
 * @author Joel Bout, <joel@taotesting.com>
 * @package taoItems
 */
class ImportExportTest extends TaoPhpUnitTestRunner
{
    /**
     * @var ImportService
     */
    protected $importService;
    /**
     * @var string
     */
    protected $dataFolder;

    /**
     * tests initialization
     */
    public function setUp()
    {
        TaoPhpUnitTestRunner::initTest();
        $this->importService = new ImportService();
        $this->dataFolder = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'samples' . DIRECTORY_SEPARATOR;
    }

    public function testImportOwi()
    {
        $itemClass = new \core_kernel_classes_Class(TaoOntology::ITEM_CLASS_URI);

        //validate malformed html
        $report = $this->importService->importXhtmlFile($this->dataFolder . 'badItem.zip', $itemClass, true);
        $this->assertFalse($report->containsSuccess());
        $this->assertTrue($report->containsError());

        $count = 0;
        foreach ($report as $element) {
            $this->assertEquals(\common_report_Report::TYPE_ERROR, $element->getType());
            $count++;
        }
        $this->assertEquals(2, $count);

        //invalid package structure
        $report = $this->importService->importXhtmlFile($this->dataFolder . 'invalid.zip', $itemClass, true);
        $this->assertFalse($report->containsSuccess());
        $this->assertTrue($report->containsError());

        $report = $this->importService->importXhtmlFile($this->dataFolder . 'complete.zip', $itemClass, false);
        $this->assertEquals(\common_report_Report::TYPE_SUCCESS, $report->getType());
        $owiItem = $report->getData();

        $this->assertInstanceOf('\core_kernel_classes_Resource', $owiItem);

        $itemService = \taoItems_models_classes_ItemsService::singleton();
        $dir = $itemService->getItemDirectory($owiItem);
        $this->assertTrue($dir->getFile('index.html')->exists());
        $this->assertTrue($dir->getFile('logo.gif')->exists());

        $this->assertTrue($itemService->deleteItem($owiItem));
    }

    /**
     * @expectedException \common_exception_Error
     * @throws \common_exception_Error
     * @throws \taoItems_models_classes_Import_ExtractException
     * @throws \taoItems_models_classes_Import_ImportException
     */
    public function testWrongClass()
    {
        $itemClass = new \core_kernel_classes_Class(GenerisRdf::CLASS_GENERIS_RESOURCE);
        $report = $this->importService->importXhtmlFile('dummy', $itemClass, true);
    }

    public function testImportContent()
    {
        $label = 'item_for_test';
        $itemService = \taoItems_models_classes_ItemsService::singleton();
        $itemClass = $itemService->getRootClass();
        $item = $itemService->createInstance($itemClass, $label);
        $item->setPropertyValue(new \core_kernel_classes_Property(taoItems_models_classes_ItemsService::PROPERTY_ITEM_MODEL), OwiItemModel::ITEMMODEL_URI);

        //validate malformed html
        $report = $this->importService->importContent($this->dataFolder . 'complete.zip', $item);
        $this->assertEquals(\common_report_Report::TYPE_SUCCESS, $report->getType());
        $content = $itemService->getItemDirectory($item)->getFile('index.html')->read();
        $this->assertFalse(empty($content));

        $report = $this->importService->importContent($this->dataFolder . 'invalid.zip', $itemClass, true);
        $this->assertNotEquals(\common_report_Report::TYPE_SUCCESS, $report->getType());

        $this->assertTrue($itemService->deleteItem($item));
    }

    public function testCompileMissingRemote()
    {
        $itemClass = new \core_kernel_classes_Class(TaoOntology::ITEM_CLASS_URI);

        $report = $this->importService->importXhtmlFile($this->dataFolder . 'missingRemote.zip', $itemClass, false);
        $missingRemote = $report->getData();
        $this->assertInstanceOf('core_kernel_classes_Resource', $missingRemote);

        $storage = \tao_models_classes_service_FileStorage::singleton();
        $compiler = new OwiItemCompiler($missingRemote, $storage);
        $compiler->setServiceLocator($this->getServiceLocator());
        $report = $compiler->compile();
        $this->assertEquals(\common_report_Report::TYPE_ERROR, $report->getType());
        $serviceCall = $report->getData();
        $this->assertNull($serviceCall);

        $itemService = \taoItems_models_classes_ItemsService::singleton();
        $this->assertTrue($itemService->deleteItem($missingRemote));
    }

    public function testCompileComplete()
    {
        $itemClass = new \core_kernel_classes_Class(TaoOntology::ITEM_CLASS_URI);

        $report = $this->importService->importXhtmlFile($this->dataFolder . 'complete.zip', $itemClass, false);
        $complete = $report->getData();
        $this->assertInstanceOf('\core_kernel_classes_Resource', $complete);

        $storage = \tao_models_classes_service_FileStorage::singleton();
        $compiler = new OwiItemCompiler($complete, $storage);
        $compiler->setServiceLocator($this->getServiceLocator());
        $report = $compiler->compile();
        
        $this->assertEquals($report->getType(), \common_report_Report::TYPE_SUCCESS, 
            'this test try to retrieve http://forge.taotesting.com/themes/tao-theme/images/logo.gif check if available');
        
        $serviceCall = $report->getData();
        $this->assertNotNull($serviceCall);
        $this->assertInstanceOf('\tao_models_classes_service_ServiceCall', $serviceCall);

        $itemService = \taoItems_models_classes_ItemsService::singleton();

        $this->assertTrue($itemService->deleteItem($complete));
    }
    


}