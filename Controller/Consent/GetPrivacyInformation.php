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

use Amasty\Gdpr\Api\Data\WithConsentInterface;
use Amasty\Gdpr\Api\PolicyRepositoryInterface;
use Amasty\Gdpr\Model\Config;
use Amasty\Gdpr\Model\Consent\Consent;
use Amasty\Gdpr\Model\Consent\DataProvider\ConsentPrivacyLinkResolver;
use Amasty\Gdpr\Model\Consent\DataProvider\FrontendData;
use Amasty\Gdpr\Model\Consent\RegistryConstants;
use Amasty\Gdpr\Model\ResourceModel\WithConsent\CollectionFactory;
use Amasty\Gdpr\Model\Source\ConsentLinkType;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

class GetPrivacyInformation implements ResolverInterface
{
    const CONSENT_AREAS = [
        'registration',
        'checkout',
        'contactus',
        'subscription'
    ];

    /**
     * When the consent text contains a link with this value, the FE plugin magically replaces it with a button that opens a popup
     * Same as in src/scandipwa/app/plugin/Html.plugin.js
     */
    const PRIVACY_POPUP_MAGIC_LINK = '#amasty-scandipwa-gdpr-popup-magic-link';

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var FrontendData */
    protected $frontendData;

    /** @var ConsentPrivacyLinkResolver */
    protected $consentPrivacyLinkResolver;

    /** @var PolicyRepositoryInterface */
    protected $policyRepository;

    /** @var Config */
    protected $gdprConfigProvider;

    /** @var FilterProvider */
    protected $filterProvider;

    /** @var CollectionFactory */
    protected $withConsentCollectionFactory;

    public function __construct(
        StoreManagerInterface $storeManager,
        FrontendData $frontendData,
        ConsentPrivacyLinkResolver $consentPrivacyLinkResolver,
        PolicyRepositoryInterface $policyRepository,
        Config $gdprConfigProvider,
        FilterProvider $filterProvider,
        CollectionFactory $withConsentCollectionFactory
    ) {
        $this->storeManager = $storeManager;
        $this->frontendData = $frontendData;
        $this->consentPrivacyLinkResolver = $consentPrivacyLinkResolver;
        $this->policyRepository = $policyRepository;
        $this->gdprConfigProvider = $gdprConfigProvider;
        $this->filterProvider = $filterProvider;
        $this->withConsentCollectionFactory = $withConsentCollectionFactory;
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
        if (!$this->gdprConfigProvider->isModuleEnabled()) {
            return [];
        }

        $agreements = $this->getAgreements($context);

        return [
            'consents' => $this->getConsents($agreements),
            'privacyPolicy' => $this->getPrivacyPolicy()
        ];
    }

    protected function getAgreements(ContextInterface $context)
    {
        $customerId = (int)$context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $currentPolicy = $this->policyRepository->getCurrentPolicy($storeId);

        if (!$customerId) {
            return [];
        }

        // next few lines is, unfortunately, duplicate code from bas Amasty GDPR extension, as the user and store ID have to be identified with a different method
        $consentLogCollection = $this->withConsentCollectionFactory->create();
        $consentLogCollection->filterByLastConsentRecord()
            ->addFieldToFilter('main_table.' . WithConsentInterface::CUSTOMER_ID, $customerId)
            ->addFieldToFilter('main_table.' . WithConsentInterface::ACTION, true)
            ->addFieldToFilter(
                'main_table.' . WithConsentInterface::POLICY_VERSION,
                $currentPolicy->getPolicyVersion()
            );

        return $consentLogCollection->getColumnValues(WithConsentInterface::CONSENT_CODE);
    }

    protected function getConsents($agreements)
    {
        $consents = [];
        foreach (self::CONSENT_AREAS as $area) {
            $consentItems = $this->frontendData->getData($area);

            $consents[$area] = array_map(function ($consent) use ($agreements) {
                return $this->getConsentData($consent, $agreements);
            }, $consentItems);
        }
        return $consents;
    }

    protected function getConsentData($consent, $agreements)
    {
        return [
            'name' => $consent->getConsentName(),
            'code' => $consent->getConsentCode(),
            'id' => $consent->getConsentId(),
            'isRequired' => $consent->isRequired(),
            'isAgreed' => $this->isAgreed($consent, $agreements),
            'text' => $this->getConsentText($consent)
        ];
    }

    public function getConsentText(Consent $consent): string
    {
        $linkType = $consent->getPrivacyLinkType() ?: ConsentLinkType::PRIVACY_POLICY;

        if ($linkType == ConsentLinkType::PRIVACY_POLICY) {
            $newLink = self::PRIVACY_POPUP_MAGIC_LINK;
        } else {
            $newLink = $this->consentPrivacyLinkResolver->getPrivacyLink($consent);
        }

        return str_replace(
            RegistryConstants::LINK_PLACEHOLDER,
            $newLink,
            $consent->getConsentText()
        );
    }

    protected function getPrivacyPolicy()
    {
        $policy = $this->policyRepository->getCurrentPolicy(
            $this->storeManager->getStore()->getId()
        );

        if ($policy) {
            return $policy->getContent();
        }

        return null;
    }

    protected function isAgreed($consent, $agreements)
    {
        return in_array($consent->getConsentCode(), $agreements);
    }
}
