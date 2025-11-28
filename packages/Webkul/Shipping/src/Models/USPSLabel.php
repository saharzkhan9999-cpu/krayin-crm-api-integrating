<?php

namespace Webkul\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class USPSLabel extends Model
{
    protected $table = 'usps_labels';

    protected $fillable = [
        'order_id',
        'tracking_number',
        'label_image',
        'receipt_image',
        'label_metadata',
        'postage_amount',
        'mail_class',
        'rate_indicator',
        'processing_category',
        'status',
        'carrier',
        'service_type',
        'label_url',
        'response_data',
        'request_data',
        'cancelled_at',
        'reprint_count',
    ];

    protected $casts = [
        'label_metadata' => 'array',
        'response_data' => 'array',
        'request_data' => 'array',
        'postage_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'reprint_count' => 'integer',
    ];

    protected $attributes = [
        'status' => 'active',
        'carrier' => 'USPS',
        'reprint_count' => 0,
    ];

    /**
     * Get the order that owns the label.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderProxy::modelClass());
    }

    /**
     * Scope a query to only include active labels.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include canceled labels.
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    /**
     * Scope a query to only include labels for specific order.
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Check if label can be canceled.
     */
    public function canBeCanceled(): bool
    {
        return $this->status === 'active' && empty($this->cancelled_at);
    }

    /**
     * Check if label can be reprinted.
     */
    public function canBeReprinted(): bool
    {
        return $this->status === 'active' && $this->reprint_count < 3;
    }

    /**
     * Mark label as canceled.
     */
    public function markAsCanceled(): bool
    {
        return $this->update([
            'status' => 'canceled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Increment reprint count.
     */
    public function incrementReprintCount(): bool
    {
        return $this->increment('reprint_count');
    }

    /**
     * Get tracking URL attribute.
     */
    protected function trackingUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tracking_number ? 
                "https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1={$this->tracking_number}" : 
                null
        );
    }

    /**
     * Get formatted postage amount.
     */
    protected function formattedPostage(): Attribute
    {
        return Attribute::make(
            get: fn () => '$' . number_format($this->postage_amount, 2)
        );
    }

    /**
     * Get label image as base64.
     */
    protected function labelImageBase64(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->label_image ? base64_encode($this->label_image) : null
        );
    }
}