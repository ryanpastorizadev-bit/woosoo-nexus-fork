<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\RefillSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RefillSubmissionService
 * 
 * Durable refill submission guard to prevent duplicate POS ordered_menu inserts.
 * Uses database-level state machine for correctness, not just cache.
 * 
 * State Transitions:
 * NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED
 */
class RefillSubmissionService
{
    /**
     * Processing lock timeout in seconds (5 minutes)
     */
    private const LOCK_TIMEOUT_SECONDS = 300;

    /**
     * Acquire or find existing submission for idempotency check
     * 
     * @return array{submission: RefillSubmission|null, status: string, response: array|null}
     * status: 'new', 'processing', 'completed', 'conflict'
     */
    public function acquireOrFindSubmission(
        Device $device,
        DeviceOrder $deviceOrder,
        string $clientSubmissionId
    ): array {
        // Try to find existing submission
        $existingSubmission = RefillSubmission::where([
            'device_id' => $device->id,
            'device_order_id' => $deviceOrder->id,
            'client_submission_id' => $clientSubmissionId,
        ])->first();

        if ($existingSubmission) {
            // Check if already completed
            if ($existingSubmission->isCompleted()) {
                Log::info('[REFILL] Replaying completed submission', [
                    'device_id' => $device->id,
                    'order_id' => $deviceOrder->order_id,
                    'client_submission_id' => $clientSubmissionId,
                    'status' => $existingSubmission->status,
                ]);

                return [
                    'submission' => $existingSubmission,
                    'status' => 'completed',
                    'response' => $existingSubmission->cached_response,
                ];
            }

            // Check if currently processing
            if ($existingSubmission->isProcessing()) {
                // Check for stale lock
                if (!$existingSubmission->isLockExpired(self::LOCK_TIMEOUT_SECONDS)) {
                    Log::warning('[REFILL] Duplicate request while processing', [
                        'device_id' => $device->id,
                        'order_id' => $deviceOrder->order_id,
                        'client_submission_id' => $clientSubmissionId,
                        'status' => $existingSubmission->status,
                        'processing_started_at' => $existingSubmission->processing_started_at,
                    ]);

                    return [
                        'submission' => $existingSubmission,
                        'status' => 'conflict',
                        'response' => null,
                    ];
                }

                // Lock expired, allow retry by resetting to PROCESSING
                Log::info('[REFILL] Stale lock detected, retrying', [
                    'device_id' => $device->id,
                    'order_id' => $deviceOrder->order_id,
                    'client_submission_id' => $clientSubmissionId,
                    'previous_status' => $existingSubmission->status,
                ]);

                return $this->resetAndAcquireLock($existingSubmission);
            }

            // Failed state - allow retry
            if ($existingSubmission->status === 'FAILED') {
                Log::info('[REFILL] Retrying failed submission', [
                    'device_id' => $device->id,
                    'order_id' => $deviceOrder->order_id,
                    'client_submission_id' => $clientSubmissionId,
                    'previous_failure' => $existingSubmission->failed_at,
                ]);

                return $this->resetAndAcquireLock($existingSubmission);
            }

            // Any other state - treat as processing
            return [
                'submission' => $existingSubmission,
                'status' => 'conflict',
                'response' => null,
            ];
        }

        // Create new submission with lock
        return $this->createNewSubmission($device, $deviceOrder, $clientSubmissionId);
    }

    /**
     * Create new submission and acquire lock atomically
     */
    private function createNewSubmission(
        Device $device,
        DeviceOrder $deviceOrder,
        string $clientSubmissionId
    ): array {
        $lockId = Str::random(32);
        
        try {
            $submission = DB::transaction(function () use ($device, $deviceOrder, $clientSubmissionId, $lockId) {
                $submission = RefillSubmission::create([
                    'device_id' => $device->id,
                    'device_order_id' => $deviceOrder->id,
                    'client_submission_id' => $clientSubmissionId,
                    'status' => 'PROCESSING',
                    'processing_started_at' => now(),
                    'processing_lock_id' => $lockId,
                ]);

                return $submission;
            });

            Log::info('[REFILL] Created new submission', [
                'submission_id' => $submission->id,
                'device_id' => $device->id,
                'order_id' => $deviceOrder->order_id,
                'client_submission_id' => $clientSubmissionId,
            ]);

            return [
                'submission' => $submission,
                'status' => 'new',
                'response' => null,
            ];
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race condition - another request created the submission
            // Recursively call to find the existing one
            Log::info('[REFILL] Race condition detected, finding existing submission', [
                'device_id' => $device->id,
                'order_id' => $deviceOrder->order_id,
                'client_submission_id' => $clientSubmissionId,
            ]);

            return $this->acquireOrFindSubmission($device, $deviceOrder, $clientSubmissionId);
        }
    }

    /**
     * Reset existing submission and acquire lock
     */
    private function resetAndAcquireLock(RefillSubmission $submission): array
    {
        $lockId = Str::random(32);
        
        $submission->update([
            'status' => 'PROCESSING',
            'processing_started_at' => now(),
            'processing_lock_id' => $lockId,
            'last_error' => null,
            'failed_at' => null,
        ]);

        return [
            'submission' => $submission->fresh(),
            'status' => 'new',
            'response' => null,
        ];
    }

    /**
     * Transition submission to POS_CREATED state
     * Records POS ordered_menu IDs for idempotency verification
     */
    public function markPosCreated(
        RefillSubmission $submission,
        array $posOrderedMenuIds
    ): bool {
        $submission->pos_ordered_menu_ids = $posOrderedMenuIds;
        
        if (!$submission->transitionTo('POS_CREATED')) {
            Log::error('[REFILL] Failed to transition to POS_CREATED', [
                'submission_id' => $submission->id,
                'current_status' => $submission->status,
            ]);
            return false;
        }
        
        $submission->save();
        
        Log::info('[REFILL] Marked POS created', [
            'submission_id' => $submission->id,
            'pos_ordered_menu_ids' => $posOrderedMenuIds,
        ]);
        
        return true;
    }

    /**
     * Transition submission to MIRRORED state
     */
    public function markMirrored(RefillSubmission $submission): bool
    {
        if (!$submission->transitionTo('MIRRORED')) {
            Log::error('[REFILL] Failed to transition to MIRRORED', [
                'submission_id' => $submission->id,
                'current_status' => $submission->status,
            ]);
            return false;
        }
        
        $submission->save();
        
        Log::info('[REFILL] Marked mirrored', [
            'submission_id' => $submission->id,
        ]);
        
        return true;
    }

    /**
     * Transition submission to PRINT_EVENT_CREATED state
     */
    public function markPrintEventCreated(RefillSubmission $submission): bool
    {
        if (!$submission->transitionTo('PRINT_EVENT_CREATED')) {
            Log::error('[REFILL] Failed to transition to PRINT_EVENT_CREATED', [
                'submission_id' => $submission->id,
                'current_status' => $submission->status,
            ]);
            return false;
        }
        
        $submission->save();
        
        Log::info('[REFILL] Marked print event created', [
            'submission_id' => $submission->id,
        ]);
        
        return true;
    }

    /**
     * Complete submission and cache response
     */
    public function completeSubmission(
        RefillSubmission $submission,
        array $response
    ): bool {
        $submission->cached_response = $response;
        
        // If already in a completed-like state, just cache response
        if ($submission->isCompleted()) {
            $submission->save();
            return true;
        }
        
        // Force transition to COMPLETED from any state
        // This allows completion even if print event step was skipped
        $submission->status = 'COMPLETED';
        $submission->completed_at = now();
        $submission->save();
        
        Log::info('[REFILL] Completed submission', [
            'submission_id' => $submission->id,
            'previous_status' => $submission->getOriginal('status'),
        ]);
        
        return true;
    }

    /**
     * Mark submission as failed
     */
    public function markFailed(RefillSubmission $submission, string $error): bool
    {
        $submission->last_error = $error;
        $submission->failed_at = now();
        
        if (!$submission->transitionTo('FAILED')) {
            // Just record the error even if transition fails
            Log::warning('[REFILL] Recording failure without state transition', [
                'submission_id' => $submission->id,
                'current_status' => $submission->status,
                'error' => $error,
            ]);
        }
        
        $submission->save();
        
        Log::error('[REFILL] Marked submission failed', [
            'submission_id' => $submission->id,
            'error' => $error,
        ]);
        
        return true;
    }

    /**
     * Check if POS ordered_menu IDs match (idempotency verification)
     */
    public function verifyPosResultMatches(RefillSubmission $submission, array $newPosOrderedMenuIds): bool
    {
        if (empty($submission->pos_ordered_menu_ids)) {
            return true; // No previous data to compare
        }
        
        $storedIds = collect($submission->pos_ordered_menu_ids)->sort()->values()->all();
        $newIds = collect($newPosOrderedMenuIds)->sort()->values()->all();
        
        $matches = $storedIds === $newIds;
        
        if (!$matches) {
            Log::warning('[REFILL] POS result mismatch detected', [
                'submission_id' => $submission->id,
                'stored_ids' => $storedIds,
                'new_ids' => $newIds,
            ]);
        }
        
        return $matches;
    }
}
