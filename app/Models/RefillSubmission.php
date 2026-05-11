<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\RefillSubmission
 *
 * Durable refill submission tracking to prevent duplicate POS ordered_menu inserts.
 * 
 * State Machine:
 * - NEW: Submission record created, not yet started
 * - PROCESSING: Currently being processed (locked)
 * - POS_CREATED: POS insert completed successfully
 * - MIRRORED: Local mirror completed
 * - PRINT_EVENT_CREATED: Print event created
 * - COMPLETED: Full completion, response cached
 * - FAILED: Processing failed, may retry
 */
class RefillSubmission extends Model
{
    use HasFactory;

    protected $table = 'refill_submissions';
    
    protected $fillable = [
        'device_id',
        'device_order_id',
        'client_submission_id',
        'status',
        'pos_created_at',
        'mirrored_at',
        'print_event_created_at',
        'completed_at',
        'failed_at',
        'pos_ordered_menu_ids',
        'cached_response',
        'last_error',
        'processing_started_at',
        'processing_lock_id',
    ];

    protected $casts = [
        'pos_ordered_menu_ids' => 'array',
        'cached_response' => 'array',
        'pos_created_at' => 'datetime',
        'mirrored_at' => 'datetime',
        'print_event_created_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'processing_started_at' => 'datetime',
    ];

    /**
     * Valid state transitions
     */
    public const VALID_TRANSITIONS = [
        'NEW' => ['PROCESSING'],
        'PROCESSING' => ['POS_CREATED', 'FAILED'],
        'POS_CREATED' => ['MIRRORED', 'FAILED'],
        'MIRRORED' => ['PRINT_EVENT_CREATED', 'FAILED'],
        'PRINT_EVENT_CREATED' => ['COMPLETED', 'FAILED'],
        'COMPLETED' => [], // Terminal state
        'FAILED' => ['PROCESSING'], // Can retry from failed
    ];

    /**
     * States that indicate the refill is already complete
     */
    public const COMPLETED_STATES = ['COMPLETED'];

    /**
     * States that indicate processing is in-flight
     */
    public const PROCESSING_STATES = ['PROCESSING', 'POS_CREATED', 'MIRRORED'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function deviceOrder(): BelongsTo
    {
        return $this->belongsTo(DeviceOrder::class, 'device_order_id', 'id');
    }

    /**
     * Attempt to transition to a new state
     */
    public function transitionTo(string $newState): bool
    {
        $currentState = $this->status;
        
        if (!isset(self::VALID_TRANSITIONS[$currentState])) {
            return false;
        }
        
        if (!in_array($newState, self::VALID_TRANSITIONS[$currentState], true)) {
            return false;
        }
        
        $this->status = $newState;
        
        // Set timestamp fields based on state
        switch ($newState) {
            case 'POS_CREATED':
                $this->pos_created_at = now();
                break;
            case 'MIRRORED':
                $this->mirrored_at = now();
                break;
            case 'PRINT_EVENT_CREATED':
                $this->print_event_created_at = now();
                break;
            case 'COMPLETED':
                $this->completed_at = now();
                break;
            case 'FAILED':
                $this->failed_at = now();
                break;
        }
        
        return true;
    }

    /**
     * Check if submission is in a completed state
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, self::COMPLETED_STATES, true);
    }

    /**
     * Check if submission is currently processing
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, self::PROCESSING_STATES, true);
    }

    /**
     * Check if lock has expired (for stale lock detection)
     */
    public function isLockExpired(int $timeoutSeconds = 300): bool
    {
        if (!$this->processing_started_at) {
            return true;
        }
        
        return $this->processing_started_at->diffInSeconds(now()) > $timeoutSeconds;
    }
}
