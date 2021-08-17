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

use Amasty\Gdpr\Controller\Customer\DownloadCsv as AmastyDownloadCsv;
use Amasty\Gdpr\Model\CustomerData;
use Amasty\Gdpr\Controller\Result\CsvFactory;
use Amasty\Gdpr\Model\GuestOrderProvider;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Psr\Log\LoggerInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Customer\Model\Authentication;
use Amasty\Gdpr\Model\Config;
use Exception;

class DownloadCsv extends AmastyDownloadCsv implements ResolverInterface
{
    /**
     * @var CustomerData
     */
    protected $customerData;

    /**
     * @var Authentication
     */
    protected $authentication;

    /**
     * @var Config
     */
    protected $configProvider;

    /**
     * @var GuestOrderProvider
     */
    protected $guestOrderProvider;

    /**
     * DownloadCsv constructor.
     *
     * @param Context $context
     * @param CustomerData $customerData
     * @param Session $customerSession
     * @param LoggerInterface $logger
     * @param File $fileDriver
     * @param Authentication $authentication
     * @param FormKeyValidator $formKeyValidator
     * @param Config $configProvider
     * @param CsvFactory $csvFactory
     * @param GuestOrderProvider $guestOrderProvider
     */
    public function __construct(
        Context $context,
        CustomerData $customerData,
        Session $customerSession,
        LoggerInterface $logger,
        File $fileDriver,
        Authentication $authentication,
        FormKeyValidator $formKeyValidator,
        Config $configProvider,
        CsvFactory $csvFactory,
        GuestOrderProvider $guestOrderProvider
    ) {
        $this->customerData = $customerData;
        $this->authentication = $authentication;
        $this->configProvider = $configProvider;

        parent::__construct(
            $context,
            $customerData,
            $customerSession,
            $logger,
            $fileDriver,
            $authentication,
            $formKeyValidator,
            $configProvider,
            $csvFactory,
            $guestOrderProvider
        );
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $response = [
            'result' => [],
            'message' => ''
        ];

        try {
            if (!$this->configProvider->isAllowed(Config::DOWNLOAD)) {
                throw new LocalizedException(__('Access denied.'));
            }

            $customerId = $context->getUserId();

            if (!$customerId) {
                throw new LocalizedException(__('Something went wrong. Please try again.'));
            }

            $customerPass = $args['customerPassword'];

            try {
                $this->authentication->authenticate($customerId, $customerPass);
            } catch (AuthenticationException $e) {
                throw new LocalizedException(__('Wrong Password. Please recheck it.'));
            }

            $customerPersonalData = $this->customerData->getPersonalData($customerId);
            $customerData = [];

            foreach ($customerPersonalData as $data) {
                $indexData = '';

                foreach ($data as $key => $customerValue) {
                    if ($key % 2 === 0) {
                        $index = $customerValue;
                    } else {
                        $indexData = $customerValue ?: '';
                    }
                }

                if (!empty($index)) {
                    $customerData[] = ['key' => $index, 'value' => $indexData];
                }
            }

            $response['result'] = $customerData;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        return $response;
    }
}
