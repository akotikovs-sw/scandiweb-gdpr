<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandiweb/gdpr-scandipwa
 * @author    Reinis Mazeiks <info@scandiweb.com>
 */

namespace Scandiweb\GdprScandiPWA\Model\Resolver;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\CustomerGraphQl\Model\Customer\CreateCustomerAccount;
use Magento\CustomerGraphQl\Model\Resolver\CreateCustomer;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Scandiweb\GdprScandiPWA\Exception\GdprModuleDisabledException;
use Scandiweb\GdprScandiPWA\Helper\ConsentUpdater;

class CreateCustomerConsentSaver
{

    /** @var ConsentUpdater */
    protected $consentUpdater;

    public function __construct(
        ConsentUpdater $consentUpdater
    ) {
        $this->consentUpdater = $consentUpdater;
    }

    /**
     * Plugin for \Magento\CustomerGraphQl\Model\Customer\CreateCustomerAccount::execute
     * @param CreateCustomer $authModel
     * @param $result
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return CustomerInterface
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException
     */
    public function afterExecute(
        CreateCustomerAccount $subject,
        CustomerInterface $result,
        array $data,
        StoreInterface $store
    ) {
        $customerId = (int) $result->getId();
        $storeId = (int) $result->getStoreId();

        try {
            $this->consentUpdater->processConsents($data['privacyConsentSelection'], $customerId, $storeId, 'registration');
        } catch (GdprModuleDisabledException $ignored) {
        }

        return $result;
    }
}
