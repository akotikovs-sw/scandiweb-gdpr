<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandiweb/gdpr-scandipwa
 * @author    Aivars Arbidans <info@scandiweb.com>
 */

declare(strict_types=1);

namespace Scandiweb\GdprScandiPWA\Setup\Patch\Data;

use Exception;
use Scandiweb\GdprScandiPWA\Helper\UpdateEavAttribute;
use Magento\Customer\Model\Customer;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Zend_Validate_Exception;

/**
 * Class AddNewCustomerIsActiveAttribute
 * @package Scandiweb\Customer\Setup\Patch\Data
 */
class AddNewCustomerIsActiveAttribute implements DataPatchInterface
{
    /**
     * Product data structure
     */
    protected const ATTRIBUTES_DATA = [
        Customer::ENTITY => [
            'is_active' => [
                'type' => 'int',
                'label' => 'Is Customer Active',
                'input' => 'boolean',
                'default' => 1,
                'required' => true,
                'visible' => true,
                'user_defined' => false,
                'position' => 100,
                'system' => 0,
                'is_used_in_grid' => false,
                'add_to_default_set' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => true,
                'used_in_forms' => [
                    'adminhtml_customer',
                    'adminhtml_checkout',
                    'customer_account_edit'
                ]
            ]
        ]
    ];

    /**
     * @var UpdateEavAttribute
     */
    protected $createEavAttribute;

    /**
     * @var State
     */
    protected $state;

    /**
     * AddNewCustomerIsActiveAttribute constructor.
     *
     * @param UpdateEavAttribute $createEavAttribute
     * @param State $state
     */
    public function __construct(
        UpdateEavAttribute $createEavAttribute,
        State $state
    ) {
        $this->createEavAttribute = $createEavAttribute;
        $this->state = $state;
    }

    /**
     * @return DataPatchInterface|void
     * @throws Exception
     */
    public function apply()
    {
        $this->state->emulateAreaCode(Area::AREA_ADMINHTML, [$this, 'replaceInvalidEavAttributes']);
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     */
    public function replaceInvalidEavAttributes(): void
    {
        $this->createEavAttribute->removeEavAttributes(self::ATTRIBUTES_DATA);
        $this->createEavAttribute->updateEavAttributes(self::ATTRIBUTES_DATA);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
