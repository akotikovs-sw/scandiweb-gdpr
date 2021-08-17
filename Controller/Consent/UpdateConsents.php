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

namespace Scandiweb\GdprScandiPWA\Controller\Consent;

use Amasty\Gdpr\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Scandiweb\GdprScandiPWA\Helper\ConsentUpdater;

class UpdateConsents implements ResolverInterface
{
    /** @var ConsentUpdater */
    protected $consentUpdater;

    /** @var Config */
    protected $gdprConfigProvider;

    public function __construct(
        ConsentUpdater $consentUpdater,
        Config $configProvider
    ) {
        $this->consentUpdater = $consentUpdater;
        $this->gdprConfigProvider = $configProvider;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$this->gdprConfigProvider->isAllowed(Config::CONSENT_OPTING)) {
            throw new GraphQlAuthenticationException(__('Access denied.'));
        }

        $customerId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $area = isset($args['area']) ? $args['area'] : 'settings';

        return $this->consentUpdater->processConsents($args['consentUpdates'], $customerId, $storeId, $area);
    }
}
