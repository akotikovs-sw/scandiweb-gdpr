<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandiweb/gdpr-scandipwa
 */

declare(strict_types=1);

namespace Scandiweb\GdprScandiPWA\Helper;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Zend_Validate_Exception;

/**
 * Class UpdateEavAttribute
 *
 * @package Eav
 */
class UpdateEavAttribute
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * @var Config
     */
    protected $eavConfig;

    /**
     * UpdateEavAttribute constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param Config $eavConfig
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @param array $attributes
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     */
    public function updateEavAttributes(array $attributes): void
    {
        foreach ($attributes as $entityType => $attributeList) {
            /**
             * @var EavSetup $eavSetupModel
             */
            $eavSetupModel = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
            $attributeSetId = $eavSetupModel->getDefaultAttributeSetId($entityType);
            $attributeGroupId = $eavSetupModel->getDefaultAttributeGroupId($entityType);

            foreach ($attributeList as $attributeCode => $attributeData) {
                $eavSetupModel->addAttribute($entityType, $attributeCode, $attributeData);

                if (isset($attributeData['used_in_forms'])) {
                    $attribute = $this->eavConfig->getAttribute($entityType, $attributeCode);
                    $attribute->setData('used_in_forms', $attributeData['used_in_forms']);
                    $attribute->setData('attribute_set_id', $attributeSetId);
                    $attribute->setData('attribute_group_id', $attributeGroupId);

                    $attribute->save();
                }
            }
        }
    }

    /**
     * Update all given properties to attributes
     *
     * @param array $attributes
     *
     * @throws LocalizedException
     */
    public function updateAttributeFields(array $attributes): void
    {
        foreach ($attributes as $entityType => $attributeList) {
            foreach ($attributeList as $identifier => $attributeData) {
                $attribute = $this->eavConfig->getAttribute($entityType, $identifier);
                foreach ($attributeData as $attributeCode => $attributeValue) {
                    $attribute->setData($attributeCode, $attributeValue);
                }
                $attribute->save();
            }
        }
    }

    /**
     * @param array $attributes
     */
    public function removeEavAttributes(array $attributes): void
    {
        foreach ($attributes as $entityType => $attributeList) {
            $eavSetupModel = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
            foreach ($attributeList as $attributeCode => $attributeData) {
                $eavSetupModel->removeAttribute($entityType, $attributeCode);
            }
        }
    }
}
