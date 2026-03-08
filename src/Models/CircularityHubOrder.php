<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class CircularityHubOrder
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public string $createdAt,
        public ?int $userId,
        public ?float $totalPrice,
        public bool $completed,
        public bool $cancelled,
        public ?string $cancellationReason,
        public ?CircularityHubBillingData $billingData,
        public array $articles,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            orderNumber: $data['order_number'],
            createdAt: $data['created_at'],
            userId: $data['user_id'] ?? null,
            totalPrice: isset($data['total_price']) ? (float) $data['total_price'] : null,
            completed: $data['completed'],
            cancelled: $data['cancelled'],
            cancellationReason: $data['cancellation_reason'] ?? null,
            billingData: isset($data['billing_data']) ? CircularityHubBillingData::fromArray($data['billing_data']) : null,
            articles: $data['articles'] ?? [],
        );
    }
}
