<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configurations\Transactions\BulkTagRequest;
use App\Http\Requests\Configurations\Transactions\SearchTransactionsRequest;
use App\Http\Requests\Configurations\Transactions\StoreExpenseRequest;
use App\Http\Requests\Configurations\Transactions\StoreIncomeRequest;
use App\Http\Requests\Configurations\Transactions\StoreTransferRequest;
use App\Http\Requests\Configurations\Transactions\UpdateTransactionRequest;
use App\Http\Resources\Configurations\TransactionResource;
use App\Models\Configurations\Transaction;
use App\Services\Configurations\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * List and search transactions with filters
     * GET /api/v1/transactions
     */
    public function index(SearchTransactionsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filters = $request->validated();

            $transactions = $this->transactionService->searchTransactions($user, $filters);

            return $this->successResponse([
                'transactions' => TransactionResource::collection($transactions->items()),
                'pagination' => [
                    'total' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                ],
            ], 'Transacciones obtenidas exitosamente.');
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Create a new expense transaction
     * POST /api/v1/transactions/expense
     */
    public function storeExpense(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $transaction = $this->transactionService->createExpense($user, $request->validated());

            return $this->successResponse(
                new TransactionResource($transaction),
                'Gasto registrado exitosamente.',
                201
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Create a new income transaction
     * POST /api/v1/transactions/income
     */
    public function storeIncome(StoreIncomeRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $transaction = $this->transactionService->createIncome($user, $request->validated());

            return $this->successResponse(
                new TransactionResource($transaction),
                'Ingreso registrado exitosamente.',
                201
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Create a new transfer transaction
     * POST /api/v1/transactions/transfer
     */
    public function storeTransfer(StoreTransferRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $transaction = $this->transactionService->createTransfer($user, $request->validated());

            return $this->successResponse(
                new TransactionResource($transaction),
                'Transferencia registrada exitosamente.',
                201
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Update an existing transaction
     * PUT /api/v1/transactions/{transaction}
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        try {
            // Verify ownership
            if ($transaction->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $updated = $this->transactionService->updateTransaction($transaction, $request->validated());

            return $this->successResponse(
                new TransactionResource($updated),
                'Transacción actualizada exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Delete a transaction
     * DELETE /api/v1/transactions/{transaction}
     */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            // Verify ownership
            if ($transaction->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $this->transactionService->deleteTransaction($transaction);

            return $this->successResponse(
                null,
                'Transacción eliminada exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Update tags for a transaction
     * PATCH /api/v1/transactions/{transaction}/tags
     */
    public function updateTags(BulkTagRequest $request, Transaction $transaction): JsonResponse
    {
        try {
            // Verify ownership
            if ($transaction->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $updated = $this->transactionService->updateTags($transaction, $request->validated()['tags']);

            return $this->successResponse(
                new TransactionResource($updated),
                'Etiquetas actualizadas exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Get recurring transaction groups
     * GET /api/v1/transactions/recurring-groups
     */
    public function recurringGroups(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $groups = $this->transactionService->getRecurringGroups($user);

            return $this->successResponse(
                $groups,
                'Grupos de transacciones recurrentes obtenidos exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }
}