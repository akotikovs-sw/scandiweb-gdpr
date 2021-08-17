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

namespace Scandiweb\GdprScandiPWA\Controller\Customer;

use Amasty\Gdpr\Controller\Customer\Anonymise as AmastyAnonymise;
use Amasty\Gdpr\Model\Anonymizer;
use Amasty\Gdpr\Model\Config;
use Amasty\Gdpr\Model\GiftRegistryDataFactory;
use Amasty\Gdpr\Model\GiftRegistryProvider;
use Amasty\Gdpr\Model\GuestOrderProvider;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Authentication;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Psr\Log\LoggerInterface;
use Exception;

class Anonymise extends AmastyAnonymise implements ResolverInterface
{
    /**
     * @var Anonymizer
     */
    protected $anonymizer;

    /**
     * @var Authentication
     */
    protected $authentication;

    /**
     * @var Config
     */
    protected $configProvider;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var GiftRegistryDataFactory
     */
    protected $giftRegistryDataFactory;

    /**
     * @var GiftRegistryProvider
     */
    protected $giftRegistryProvider;

    /**
     * @var GuestOrderProvider
     */
    protected $guestOrderProvider;

    /**
     * Anonymise constructor.
     *
     * @param Context $context
     * @param Anonymizer $anonymizer
     * @param Session $customerSession
     * @param LoggerInterface $logger
     * @param FormKeyValidator $formKeyValidator
     * @param Authentication $authentication
     * @param Config $configProvider
     * @param ProductMetadataInterface $productMetadata
     * @param GiftRegistryDataFactory $giftRegistryDataFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param GiftRegistryProvider $giftRegistryProvider
     * @param GuestOrderProvider $guestOrderProvider
     */
    public function __construct(
        Context $context,
        Anonymizer $anonymizer,
        Session $customerSession,
        LoggerInterface $logger,
        FormKeyValidator $formKeyValidator,
        Authentication $authentication,
        Config $configProvider,
        ProductMetadataInterface $productMetadata,
        GiftRegistryDataFactory $giftRegistryDataFactory,
        CustomerRepositoryInterface $customerRepository,
        GiftRegistryProvider $giftRegistryProvider,
        GuestOrderProvider $guestOrderProvider
    ) {
        $this->anonymizer = $anonymizer;
        $this->authentication = $authentication;
        $this->configProvider = $configProvider;
        $this->productMetadata = $productMetadata;
        $this->giftRegistryDataFactory = $giftRegistryDataFactory;
        $this->customerRepository = $customerRepository;

        parent::__construct(
            $context,
            $anonymizer,
            $customerSession,
            $logger,
            $formKeyValidator,
            $authentication,
            $configProvider,
            $productMetadata,
            $giftRegistryProvider,
            $guestOrderProvider
        );
    }

    /**
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $response = [
            'result' => false,
            'message' => ''
        ];

        try {
            if (!$this->configProvider->isAllowed(Config::ANONYMIZE)) {
                throw new LocalizedException(__('Access denied.'));
            }

            if (!$context->getUserId()) {
                throw new LocalizedException(__('Something went wrong. Please try again.'));
            }

            $customerId = $context->getUserId();
            $customerPass = $args['customerPassword'];

            try {
                $this->authentication->authenticate($customerId, $customerPass);
            } catch (AuthenticationException $e) {
                throw new LocalizedException(__('Wrong Password. Please recheck it.'));
            }

            $ordersData = $this->anonymizer->getCustomerActiveOrders($customerId);

            if (!empty($ordersData)) {
                $orderIncrementIds = '';

                foreach ($ordersData as $order) {
                    $orderIncrementIds .= ' ' . $order['increment_id'];
                }

                throw new LocalizedException(__('We can not anonymize your account right now, because you have non-completed order(s):%1', $orderIncrementIds));
            }

            if ($this->productMetadata->getEdition() === 'Enterprise'
                && $this->configProvider->isAvoidGiftRegistryAnonymization()
                && $this->checkGiftRegistries($customerId)) {
                throw new LocalizedException(__('We can not anonymize your account right now, because you have active Gift Registry'));
            }

            $this->anonymizer->anonymizeCustomer($customerId);

            $customer = $this->customerRepository->getById($customerId);
            $customer->setCustomAttribute('is_active', 1);
            $this->customerRepository->save($customer);

            $response['result'] = true;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * @param int $customerId
     *
     * @return bool
     */
    protected function checkGiftRegistries($customerId)
    {
        return (bool)$this->giftRegistryDataFactory->create(GiftRegistryDataFactory::GIFT_REGISTRY_ENTITY_KEY)
            ->filterByCustomerId($customerId)
            ->filterByActive()
            ->getSize();
    }
}
