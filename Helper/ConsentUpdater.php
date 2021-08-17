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

namespace Scandiweb\GdprScandiPWA\Helper;

use Amasty\Gdpr\Model\Config;
use Amasty\Gdpr\Model\Consent\RegistryConstants;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Scandiweb\GdprScandiPWA\Exception\GdprModuleDisabledException;

/**
 * Class ConsentUpdater
 * Saves the user's consent updates
 * @package Scandiweb\GdprScandiPWA\Helper
 */
class ConsentUpdater
{
    /** @var UserContextInterface */
    protected $userContext;

    /** @var Config */
    protected $gdprConfigProvider;

    /** @var ManagerInterface */
    protected $eventManager;

    public function __construct(
        ManagerInterface $eventManager,
        UserContextInterface $userContext,
        Config $gdprConfigProvider
    ) {
        $this->eventManager = $eventManager;
        $this->userContext = $userContext;
        $this->gdprConfigProvider = $gdprConfigProvider;
    }

    /**
     * @param $consents
     * @param $customerId
     * @param $storeId
     * @param $area
     * @return bool
     * @throws GraphQlAuthenticationException
     * @throws GdprModuleDisabledException
     */
    public function processConsents($consents, $customerId, $storeId, $area)
    {
        if (!$this->gdprConfigProvider->isModuleEnabled()) {
            throw new GdprModuleDisabledException(__('Error: the GDPR module is disabled.'));
        }

        $consentsByCode = [];

        foreach ($consents as $consent) {
            $consentsByCode[$consent['code']] = $consent['accept'];
        }

        if (!$customerId) {
            throw new GraphQlAuthenticationException(__('Error: the user appears to not be logged in.'));
        }

        $this->eventManager->dispatch(
            'amasty_gdpr_consent_accept',
            [
                RegistryConstants::CONSENTS => $consentsByCode,
                RegistryConstants::CONSENT_FROM => $area,
                RegistryConstants::CUSTOMER_ID => $customerId,
                RegistryConstants::STORE_ID => $storeId
            ]
        );

        return true;
    }
}
