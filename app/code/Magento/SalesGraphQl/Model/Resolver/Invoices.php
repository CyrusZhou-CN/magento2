<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\SalesGraphQl\Model\Resolver;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\InvoiceInterface;

/**
 * Resolver for Invoice
 */
class Invoices implements ResolverInterface
{
    /**
     * @param TimezoneInterface|null $timezone
     */
    public function __construct(
        private ?TimezoneInterface $timezone = null
    ) {
        $this->timezone = $timezone ?: ObjectManager::getInstance()->get(TimezoneInterface::class);
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!(($value['model'] ?? null) instanceof OrderInterface)) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var OrderInterface $orderModel */
        $orderModel = $value['model'];
        $invoices = [];
        /** @var InvoiceInterface $invoice */
        foreach ($orderModel->getInvoiceCollection() as $invoice) {
            $invoices[] = [
                'id' => base64_encode($invoice->getEntityId()),
                'number' => $invoice['increment_id'],
                'comments' => $this->getInvoiceComments($invoice),
                'model' => $invoice,
                'order' => $orderModel
            ];
        }
        return $invoices;
    }

    /**
     * Get invoice comments in proper format
     *
     * @param InvoiceInterface $invoice
     * @return array
     */
    private function getInvoiceComments(InvoiceInterface $invoice): array
    {
        $comments = [];
        foreach ($invoice->getComments() as $comment) {
            if ($comment->getIsVisibleOnFront()) {
                $comments[] = [
                    'timestamp' => $this->timezone->date($comment->getCreatedAt())
                        ->format(DateTime::DATETIME_PHP_FORMAT),
                    'message' => $comment->getComment()
                ];
            }
        }
        return $comments;
    }
}
