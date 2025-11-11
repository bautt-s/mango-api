<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configurations\Accounts\ReorderAccountsRequest;
use App\Http\Requests\Configurations\Accounts\StoreAccountRequest;
use App\Http\Requests\Configurations\Accounts\UpdateAccountRequest;
use App\Http\Resources\Configurations\AccountResource;
use App\Models\Configurations\Account;
use App\Services\Configurations\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    protected AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * Get all accounts for authenticated user
     * GET /api/v1/accounts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $includeArchived = $request->boolean('include_archived', false);

            $accounts = $this->accountService->getAccountsForUser($user, $includeArchived);

            return $this->successResponse(
                AccountResource::collection($accounts),
                'Cuentas obtenidas exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Create a new account
     * POST /api/v1/accounts
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $account = $this->accountService->createAccount($user, $request->validated());

            return $this->successResponse(
                new AccountResource($account),
                'Cuenta creada exitosamente.',
                201
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Update an existing account
     * PUT /api/v1/accounts/{account}
     */
    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        try {
            // Authorization check
            if ($account->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $account = $this->accountService->updateAccount($account, $request->validated());

            return $this->successResponse(
                new AccountResource($account),
                'Cuenta actualizada exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Archive an account
     * PATCH /api/v1/accounts/{account}/archive
     */
    public function archive(Request $request, Account $account): JsonResponse
    {
        try {
            // Authorization check
            if ($account->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $result = $this->accountService->archiveAccount($account);

            return $this->successResponse(
                new AccountResource($account->fresh()),
                'Cuenta archivada exitosamente.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Unarchive an account
     * PATCH /api/v1/accounts/{account}/unarchive
     */
    public function unarchive(Request $request, Account $account): JsonResponse
    {
        try {
            // Authorization check
            if ($account->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $account = $this->accountService->unarchiveAccount($account);

            return $this->successResponse(
                new AccountResource($account),
                'Cuenta desarchivada exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Set account as default
     * PATCH /api/v1/accounts/{account}/default
     */
    public function setDefault(Request $request, Account $account): JsonResponse
    {
        try {
            // Authorization check
            if ($account->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $result = $this->accountService->setAsDefault($account);

            if (!$result) {
                return $this->errorResponse(
                    null,
                    'No se puede setear cuenta archivada como default.',
                    400
                );
            }

            return $this->successResponse(
                new AccountResource($account->fresh()),
                'Cuenta seteada como default exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Reorder accounts
     * PATCH /api/v1/accounts/reorder
     */
    public function reorder(ReorderAccountsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $this->accountService->reorderAccounts($user, $request->validated()['accounts']);

            $accounts = $this->accountService->getAccountsForUser($user, false);

            return $this->successResponse(
                AccountResource::collection($accounts),
                'Cuentas reordenadas exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }
}