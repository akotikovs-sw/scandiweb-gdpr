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

namespace Scandiweb\GdprScandiPWA\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CustomerGraphQl\Model\Resolver\GenerateCustomerToken as CoreGenerateCustomerToken;

/**
 * Customers Token resolver, used for GraphQL request processing.
 */
class GenerateCustomerToken extends CoreGenerateCustomerToken
{
    /**
     * @var CustomerTokenServiceInterface
     */
    protected $customerTokenService;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var CustomerFactory
     */
    protected $customer;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * GenerateCustomerToken constructor.
     *
     * @param CustomerTokenServiceInterface $customerTokenService
     * @param EventManager $eventManager
     * @param CustomerFactory $customer
     * @param StoreManagerInterface $storeManager
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        CustomerTokenServiceInterface $customerTokenService,
        EventManager $eventManager,
        CustomerFactory $customer,
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerTokenService = $customerTokenService;
        $this->eventManager = $eventManager;
        $this->customer = $customer;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;

        parent::__construct($customerTokenService, $eventManager);
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $email = $args['email'];
        $password = $args['password'];

        if (empty($email)) {
            throw new GraphQlInputException(__('Specify the "email" value.'));
        }

        if (empty($password)) {
            throw new GraphQlInputException(__('Specify the "password" value.'));
        }

        $websiteID = $this->storeManager->getStore()->getWebsiteId();
        $customerId = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($email)->getId();
        $customer = $this->customerRepository->getById($customerId);
        $isActive = $customer->getCustomAttribute('is_active')->getValue();

        if ($customer && !$isActive) {
            throw new GraphQlInputException(__('This account has been deleted!'));
        }

        try {
            $customerToken = $this->customerTokenService->createCustomerAccessToken($email, $password);
        } catch (AuthenticationException $e) {
            throw new GraphQlAuthenticationException(__($e->getMessage()), $e);
        }

        if (isset($args['guest_quote_id'])) {
            $guestToken = $args['guest_quote_id'];

            $this->eventManager->dispatch('generate_customer_token_after', [
                'guest_quote_id' => $guestToken,
                'customer_token' => $customerToken
            ]);
        }

        return ['token' => $customerToken];
    }
}
