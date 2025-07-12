<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Success response
     */
    protected function sendResponse($result, $message = '', $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];

        return response()->json($response, $code);
    }

    /**
     * Error response
     */
    protected function sendError($error, $errorMessages = [], $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    /**
     * Validation error response
     */
    protected function sendValidationError($errors, $message = 'Validation failed'): JsonResponse
    {
        return $this->sendError($message, $errors, 422);
    }

    /**
     * Not found response
     */
    protected function sendNotFound($message = 'Resource not found'): JsonResponse
    {
        return $this->sendError($message, [], 404);
    }

    /**
     * Unauthorized response
     */
    protected function sendUnauthorized($message = 'Unauthorized'): JsonResponse
    {
        return $this->sendError($message, [], 401);
    }

    /**
     * Forbidden response
     */
    protected function sendForbidden($message = 'Forbidden'): JsonResponse
    {
        return $this->sendError($message, [], 403);
    }

    /**
     * Server error response
     */
    protected function sendServerError($message = 'Internal server error'): JsonResponse
    {
        return $this->sendError($message, [], 500);
    }
} 