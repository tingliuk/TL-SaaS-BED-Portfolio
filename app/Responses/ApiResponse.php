<?php

namespace App\Responses;

use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ApiResponse
{
    public static function rollback(
        $error,
        $message = "Something went wrong! Process did not complete."
    ): void
    {
        DB:
        rollback();
        self::throw($error, $message);
    }

    public static function throw(
        $error,
        $message = "Something went wrong! Process did not complete."
    ): void
    {
        Log::error($error);
        throw new \HttpResponseException(
            response()->json(['message' => $message], 500)
        );
    }

    public static function sendResponse(
        $result,
        $message,
        $success = true,
        $code = 200
    ): JsonResponse
    {
        $response = [
            'success' => $success,
            'message' => $message ?? null,
            'data' => $result,
        ];

        return response()->json($response, $code);
    }

    public static function success($result, $message, $code = 200): JsonResponse
    {
        return self::sendResponse($result, $message, true, $code);
    }

    public static function error($result, $message, $code = 500): JsonResponse
    {
        return self::sendResponse($result, $message, false, $code);
    }

}
