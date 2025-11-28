<?php

namespace Webkul\Shipping\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Webkul\Shipping\Services\USPSLabelService;
use Webkul\Shipping\Models\USPSLabel;
use Webkul\Shipping\Exceptions\USPSApiException;
use Webkul\Shipping\Exceptions\USPSValidationException;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Core\Http\Controllers\BackendBaseController;

class USPSLabelController extends BackendBaseController
{
    protected $uspsService;
    protected $orderRepository;

    // Rate limiting
    protected $maxLabelsPerMinute = 10;
    protected $maxReprintsPerDay = 3;

    public function __construct(
        USPSLabelService $uspsService,
        OrderRepository $orderRepository
    ) {
        $this->uspsService = $uspsService;
        $this->orderRepository = $orderRepository;
        
        // Apply middleware
        $this->middleware('auth:admin')->except(['downloadLabel']);
        $this->middleware('throttle:' . $this->maxLabelsPerMinute . ',1')->only(['createLabel', 'createReturnLabel']);
    }

    /**
     * Generate shipping label for order
     */
    public function createLabel(CreateLabelRequest $request, $orderId): JsonResponse
    {
        try {
            // Rate limiting
            $key = 'label_creation:' . auth()->id();
            if (RateLimiter::tooManyAttempts($key, $this->maxLabelsPerMinute)) {
                return $this->errorResponse('Too many label creation attempts', 429);
            }
            RateLimiter::hit($key);

            return DB::transaction(function () use ($request, $orderId) {
                $order = $this->orderRepository->findOrFail($orderId);
                
                // Authorization
                if (!auth()->user()->can('create-label', $order)) {
                    return $this->errorResponse('Unauthorized to create labels for this order', 403);
                }

                // Check existing label
                $existingLabel = USPSLabel::forOrder($orderId)->active()->first();
                if ($existingLabel) {
                    return $this->errorResponse('Active label already exists', 409, [
                        'existing_label_id' => $existingLabel->id
                    ]);
                }

                // Build and validate label data
                $labelData = $this->uspsService->buildLabelRequestFromOrder($order, $request->validated());
                
                // Create label via USPS API
                $result = $this->uspsService->createDomesticLabel($labelData);

                // Validate response
                if (empty($result['labelMetadata']['trackingNumber'])) {
                    throw new USPSApiException('Invalid response from USPS API: missing tracking number');
                }

                // Store label image securely
                $imagePath = $this->storeLabelImage(
                    $result['labelImage'] ?? null,
                    $result['labelMetadata']['trackingNumber']
                );

                // Create label record
                $uspsLabel = USPSLabel::create([
                    'order_id' => $orderId,
                    'tracking_number' => $result['labelMetadata']['trackingNumber'],
                    'label_image_path' => $imagePath,
                    'label_metadata' => $result['labelMetadata'] ?? [],
                    'postage_amount' => $result['labelMetadata']['postage'] ?? 0,
                    'mail_class' => $request->validated('mail_class'),
                    'rate_indicator' => $request->validated('rate_indicator'),
                    'processing_category' => $request->validated('processing_category'),
                    'status' => USPSLabel::STATUS_ACTIVE,
                    'service_type' => USPSLabel::SERVICE_TYPE_OUTBOUND,
                    'created_by' => auth()->id(),
                    'response_data' => $this->sanitizeResponseData($result),
                    'request_data' => $this->sanitizeRequestData($labelData),
                ]);

                // Log successful creation
                Log::info('USPS label created successfully', [
                    'order_id' => $orderId,
                    'label_id' => $uspsLabel->id,
                    'tracking_number' => $uspsLabel->tracking_number,
                    'user_id' => auth()->id()
                ]);

                return $this->successResponse([
                    'label_id' => $uspsLabel->id,
                    'tracking_number' => $uspsLabel->tracking_number,
                    'postage_amount' => $uspsLabel->formatted_postage,
                    'tracking_url' => $uspsLabel->tracking_url,
                    'download_url' => route('usps.labels.download', $uspsLabel->id),
                ], 'Shipping label created successfully', 201);

            });

        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
            
        } catch (USPSValidationException $e) {
            Log::warning('USPS validation failed', [
                'order_id' => $orderId,
                'errors' => $e->getErrors()
            ]);
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
            
        } catch (USPSApiException $e) {
            Log::error('USPS API error creating label', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode()
            ]);
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
            
        } catch (\Exception $e) {
            Log::error('Unexpected error creating USPS label', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to create shipping label', 500);
        }
    }

    /**
     * Download label as PDF
     */
    public function downloadLabel($labelId)
    {
        try {
            $label = USPSLabel::findOrFail($labelId);
            
            // Authorization - allow order customer or admin
            if (!auth()->check() || 
                (!auth()->user()->can('download-label', $label) && 
                 !$this->isOrderCustomer($label->order_id, auth()->id()))) {
                abort(403, 'Unauthorized to download this label');
            }

            if (!$label->label_image_path || !Storage::exists($label->label_image_path)) {
                return $this->errorResponse('Label file not found', 404);
            }

            return response()->file(
                Storage::path($label->label_image_path),
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="usps-label-' . $label->tracking_number . '.pdf"'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Error downloading USPS label', ['label_id' => $labelId, 'error' => $e->getMessage()]);
            return $this->errorResponse('Label not found', 404);
        }
    }

    /**
     * Store label image securely
     */
    protected function storeLabelImage(?string $base64Image, string $trackingNumber): ?string
    {
        if (!$base64Image) {
            return null;
        }

        // Validate base64
        if (base64_decode($base64Image, true) === false) {
            throw new \InvalidArgumentException('Invalid base64 image data');
        }

        $pdfContent = base64_decode($base64Image);
        
        // Validate it's actually a PDF
        if (substr($pdfContent, 0, 4) !== '%PDF') {
            throw new \InvalidArgumentException('Invalid PDF data');
        }

        $filename = "labels/{$trackingNumber}/label-" . time() . ".pdf";
        
        Storage::put($filename, $pdfContent, 'private');
        
        return $filename;
    }

    /**
     * Sanitize response data for storage
     */
    protected function sanitizeResponseData(array $data): array
    {
        // Remove large binary data
        unset($data['labelImage'], $data['receiptImage'], $data['returnLabelImage']);
        return $data;
    }

    /**
     * Sanitize request data for storage
     */
    protected function sanitizeRequestData(array $data): array
    {
        // Remove sensitive information
        unset(
            $data['payment_token'],
            $data['auth_headers'],
            $data['api_key']
        );
        return $data;
    }
}