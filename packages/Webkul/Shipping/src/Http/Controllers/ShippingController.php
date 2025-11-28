<?php

namespace Webkul\Shipping\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;

class ShippingController extends Controller
{
    /**
     * Create a USPS shipping label
     */
    public function createShippingLabel(Request $request): JsonResponse
    {
        // Ensure config alias is set (as per your provider)
        config(['usps' => config('shipping.usps')]);
        
        try {
            $labelService = App::make('usps.label');
            
            $labelData = [
                'imageInfo' => [
                    'imageType' => $request->input('imageInfo.imageType', config('usps.defaults.image_type', 'PDF')),
                    'labelType' => $request->input('imageInfo.labelType', config('usps.defaults.label_type', '4X6LABEL')),
                    'receiptOption' => $request->input('imageInfo.receiptOption', config('usps.defaults.receipt_option', 'NONE')),
                    'suppressPostage' => $request->input('imageInfo.suppressPostage', config('usps.defaults.suppress_postage', false)),
                    'suppressMailDate' => $request->input('imageInfo.suppressMailDate', config('usps.defaults.suppress_mail_date', true)),
                    'returnLabel' => $request->input('imageInfo.returnLabel', config('usps.defaults.return_label', false)),
                ],
                'fromAddress' => $request->from_address,
                'toAddress' => $request->to_address,
                'packageDescription' => $request->package_details
            ];
            
            $result = $labelService->createLabel($labelData);
            
            // Extract important information
            $metadata = json_decode($result['labelMetadata'], true);
            
            return response()->json([
                'success' => true,
                'tracking_number' => $metadata['trackingNumber'],
                'cost' => $metadata['postage'],
                'label_pdf' => base64_encode(base64_decode($result['labelImage.pdf'])),
                'service' => $metadata['commitment']['name'],
                'estimated_delivery' => $metadata['commitment']['scheduleDeliveryDate'],
                'label_metadata' => $metadata
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Test USPS connection
     */
    public function testConnection(): JsonResponse
    {
        config(['usps' => config('shipping.usps')]);
        
        try {
            $labelService = App::make('usps.label');
            $result = $labelService->testConnection();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validate address using USPS service
     */
    public function validateAddress(Request $request): JsonResponse
    {
        config(['usps' => config('shipping.usps')]);
        
        try {
            $addressService = App::make('usps.address');
            $result = $addressService->validateAddress($request->all());
            
            return response()->json([
                'success' => true,
                'validated_address' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create return label
     */
    public function createReturnLabel(Request $request): JsonResponse
    {
        config(['usps' => config('shipping.usps')]);
        
        try {
            $labelService = App::make('usps.label');
            
            $labelData = [
                'imageInfo' => $request->image_info,
                'fromAddress' => $request->from_address,
                'toAddress' => $request->to_address,
                'packageDescription' => $request->package_details
            ];
            
            $result = $labelService->createReturnLabel($labelData);
            $metadata = json_decode($result['labelMetadata'], true);
            
            return response()->json([
                'success' => true,
                'tracking_number' => $metadata['trackingNumber'],
                'cost' => $metadata['postage'],
                'label_pdf' => base64_encode(base64_decode($result['labelImage.pdf'])),
                'service' => $metadata['commitment']['name'],
                'estimated_delivery' => $metadata['commitment']['scheduleDeliveryDate']
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Cancel label
     */
    public function cancelLabel(string $trackingNumber): JsonResponse
    {
        config(['usps' => config('shipping.usps')]);
        
        try {
            $labelService = App::make('usps.label');
            $result = $labelService->cancelLabel($trackingNumber);
            
            return response()->json([
                'success' => true,
                'message' => 'Label cancelled successfully',
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create simple label with minimal parameters
     */
    public function createSimpleLabel(Request $request): JsonResponse
    {
        config(['usps' => config('shipping.usps')]);
        
        try {
            $labelService = App::make('usps.label');
            
            $result = $labelService->createSimpleLabel(
                $request->from_address,
                $request->to_address,
                $request->weight,
                $request->mail_class ?? 'USPS_GROUND_ADVANTAGE',
                $request->options ?? []
            );
            
            $metadata = json_decode($result['labelMetadata'], true);
            
            return response()->json([
                'success' => true,
                'tracking_number' => $metadata['trackingNumber'],
                'cost' => $metadata['postage'],
                'label_pdf' => base64_encode(base64_decode($result['labelImage.pdf'])),
                'service' => $metadata['commitment']['name'],
                'estimated_delivery' => $metadata['commitment']['scheduleDeliveryDate']
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}