<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

abstract class Controller
{
    use AuthorizesRequests;

    protected function validateRequest(array $data, array $rules, array $messages = [])
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails())
            return $this->errorResponse($validator->errors(), 'Error en la validación de datos', 422);

        return $validator->validated();
    }

    protected function successResponse($data = null, $message = null, $code = 200)
    {
        $response = [
            'success' => true,
        ];

        if ($data)
            $response['data'] = $data;
        if ($message)
            $response['message'] = $message;

        return response()->json($response, 200);
    }

    protected function errorResponse($errors = null, $message = null, $code = 404)
    {
        $response = [
            'success' => false,
        ];

        if ($errors)
            $response['errors'] = $errors;
        if ($message)
            $response['message'] = $message;

        return response()->json($response, $code);
    }

    protected function throwableError(\Throwable $th)
    {
        $request = request();

        $route = $request->route()->uri();
        Log::channel('errors')->error("'$route':", [
            'error' => $th->getMessage(),
            'user' => Auth::check() ? Auth::user()->id : [],
            'request' => $request->all(),
        ]);

        $response = [
            'success' => false,
        ];

        $response['message'] = 'Ocurrió un error, intente más tarde';
        $response['error'] = $th->getMessage();

        return response()->json($response, 500);
    }

    protected function unauthorizedResponse()
    {
        return $this->errorResponse('No posees autorización', [], 403);
    }
}
