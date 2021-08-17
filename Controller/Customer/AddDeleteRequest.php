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

use Amasty\Gdpr\Api\DeleteRequestRepositoryInterface;
use Amasty\Gdpr\Controller\Customer\AddDeleteRequest as AmastyAddDeleteRequest;
use Amasty\Gdpr\Model\ActionLogger;
use Amasty\Gdpr\Model\DeleteRequest\DeleteRequestSource;
use Amasty\Gdpr\Model\DeleteRequestFactory;
use Amasty\Gdpr\Model\GiftRegistryProvider;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Psr\Log\LoggerInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Customer\Model\Authentication;
use Amasty\Gdpr\Model\Config;
use Amasty\Gdpr\Model\ResourceModel\DeleteRequest\CollectionFactory;
use Amasty\Gdpr\Model\DeleteRequest\Notifier;
use Exception;

class AddDeleteRequest extends AmastyAddDeleteRequest implements ResolverInterface
{
    /**
     * @var DeleteRequestRepositoryInterface
     */
    protected $deleteRequestRepository;

    /**
     * @var DeleteRequestFactory
     */
    protected $deleteRequestFactory;

    /**
     * @var ActionLogger
     */
    protected $actionLogger;

    /**
     * @var Authentication
     */
    protected $authentication;

    /**
     * @var Config
     */
    protected $configProvider;

    /**
     * @var CollectionFactory
     */
    protected $deleteRequestCollectionFactory;

    /**
     * @var Notifier
     */
    protected $notifier;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var GiftRegistryProvider
     */
    protected $giftRegistryProvider;

    /**
     * AddDeleteRequest constructor.
     *
     * @param Context $context
     * @param Session $customerSession
     * @param LoggerInterface $logger
     * @param DeleteRequestFactory $deleteRequestFactory
     * @param DeleteRequestRepositoryInterface $deleteRequestRepository
     * @param ActionLogger $actionLogger
     * @param FormKeyValidator $formKeyValidator
     * @param Authentication $authentication
     * @param Config $configProvider
     * @param CollectionFactory $deleteRequestCollectionFactory
     * @param Notifier $notifier
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        LoggerInterface $logger,
        DeleteRequestFactory $deleteRequestFactory,
        DeleteRequestRepositoryInterface $deleteRequestRepository,
        ActionLogger $actionLogger,
        FormKeyValidator $formKeyValidator,
        Authentication $authentication,
        Config $configProvider,
        CollectionFactory $deleteRequestCollectionFactory,
        Notifier $notifier,
        ProductMetadataInterface $productMetadata,
        GiftRegistryProvider $giftRegistryProvider
    ) {
        $this->deleteRequestRepository = $deleteRequestRepository;
        $this->deleteRequestFactory = $deleteRequestFactory;
        $this->actionLogger = $actionLogger;
        $this->authentication = $authentication;
        $this->configProvider = $configProvider;
        $this->deleteRequestCollectionFactory = $deleteRequestCollectionFactory;
        $this->notifier = $notifier;
        parent::__construct(
            $context,
            $customerSession,
            $logger,
            $deleteRequestFactory,
            $deleteRequestRepository,
            $actionLogger,
            $formKeyValidator,
            $authentication,
            $configProvider,
            $deleteRequestCollectionFactory,
            $notifier,
            $productMetadata,
            $giftRegistryProvider
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
            if (!$this->configProvider->isAllowed(Config::DELETE)) {
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

            $deleteRequests = $this->deleteRequestCollectionFactory->create();

            if ($deleteRequests->addFieldToFilter('customer_id', $customerId)->getSize()) {
                throw new LocalizedException(__('Your delete account request is awaiting for the review by the administrator.'));
            }

            $request = $this->deleteRequestFactory->create();
            $request->setCustomerId($customerId);
            $request->setGotFrom(DeleteRequestSource::CUSTOMER_REQUEST);
            $this->deleteRequestRepository->save($request);
            $this->actionLogger->logAction('delete_request_submitted', $request->getCustomerId());
            if ($this->configProvider->isAdminDeleteNotificationEnabled()) {
                $this->notifier->notifyAdmin($customerId);
            }

            $response['result'] = true;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        return $response;
    }
}
