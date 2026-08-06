<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AuditLog extends Model
{

    public const ACTION_CREATE = 'CREATE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    public const ACTION_PRINT = 'PRINT';
    public const ACTION_EXPORT_EXCEL = 'EXPORT_EXCEL';
    public const ACTION_EXPORT_PDF = 'EXPORT_PDF';
    public const ACTION_LOGIN = 'LOGIN';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_WHATSAPP = 'WHATSAPP';
    public const ACTION_CUSTOM = 'CUSTOM';
    /**
     * -------------------------------------------------------------
     * Table
     * -------------------------------------------------------------
     */

    protected $table = 'audit_logs';

    /**
     * -------------------------------------------------------------
     * Primary Key
     * -------------------------------------------------------------
     */

    protected $primaryKey = 'id';

    /**
     * -------------------------------------------------------------
     * No Updated At
     * -------------------------------------------------------------
     */

    public const UPDATED_AT = null;
    /**
     * -------------------------------------------------------------
     * Mass Assignable
     * -------------------------------------------------------------
     */

    protected $fillable = [

        'request_id',

        'module_code',
        'module_name',

        'table_name',
        'record_id',

        'action',

        'old_data',
        'new_data',
        'changed_data',

        'remarks',

        'user_id',
        'user_name',

        'ip_address',
        'user_agent',
        'request_url',

        'created_at',

    ];

    /**
     * -------------------------------------------------------------
     * Casts
     * -------------------------------------------------------------
     */

    protected $casts = [

        'old_data' => 'array',
        'new_data' => 'array',
        'changed_data' => 'array',

        'created_at' => 'datetime',

    ];

    /**
     * -------------------------------------------------------------
     * Relationships
     * -------------------------------------------------------------
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * -------------------------------------------------------------
     * Scopes
     * -------------------------------------------------------------
     */

    public function scopeModule(
        Builder $query,
        ?string $module
    ): Builder {

        return empty($module)
            ? $query
            : $query->where('module_code', $module);

    }

    public function scopeAction(
        Builder $query,
        ?string $action
    ): Builder {

        return empty($action)
            ? $query
            : $query->where('action', $action);

    }

    public function scopeUser(
        Builder $query,
        ?int $userId
    ): Builder {

        return empty($userId)
            ? $query
            : $query->where('user_id', $userId);

    }

    public function scopeRecord(
        Builder $query,
        ?string $recordId
    ): Builder {

        return empty($recordId)
            ? $query
            : $query->where('record_id', $recordId);

    }

    public function scopeFromDate(
        Builder $query,
        ?string $date
    ): Builder {

        return empty($date)
            ? $query
            : $query->whereDate('created_at', '>=', $date);

    }

    public function scopeToDate(
        Builder $query,
        ?string $date
    ): Builder {

        return empty($date)
            ? $query
            : $query->whereDate('created_at', '<=', $date);

    }

    /**
     * -------------------------------------------------------------
     * Accessors
     * -------------------------------------------------------------
     */

    public function getCreatedDateAttribute()
    {
        return optional($this->created_at)
            ->format('d-m-Y H:i:s');
    }

    /**
     * -------------------------------------------------------------
     * Helper Functions
     * -------------------------------------------------------------
     */

    public function hasChangedData(): bool
    {
        return !empty($this->changed_data);
    }

    public function changedFields(): array
    {
        return array_keys(
            $this->changed_data ?? []
        );
    }

    public function oldValue(string $field): mixed
    {
        return $this->old_data[$field] ?? null;
    }

    public function newValue(string $field): mixed
    {
        return $this->new_data[$field] ?? null;
    }

    /**
     * -------------------------------------------------------------
     * Default Ordering
     * -------------------------------------------------------------
     */

        
        public function scopeRequestId(
            Builder $query,
            ?string $requestId
        ): Builder {

            return empty($requestId)
                ? $query
                : $query->where('request_id', $requestId);

        }
        public function scopeToday(
            Builder $query
        ): Builder {

            return $query->whereDate(
                'created_at',
                today()
            );

        }
        public function scopeBetweenDates(
            Builder $query,
            ?string $fromDate,
            ?string $toDate
        ): Builder {

            if (!empty($fromDate)) {
                $query->whereDate('created_at', '>=', $fromDate);
            }

            if (!empty($toDate)) {
                $query->whereDate('created_at', '<=', $toDate);
            }

            return $query;

        }
        public function scopeLatestFirst(
            Builder $query
        ): Builder {

            return $query
                ->orderByDesc('created_at')
                ->orderByDesc('id');

        }

        /**
         * Without this, Eloquent's default JSON serialization converts the
         * 'datetime' cast on created_at to a full UTC ISO-8601 timestamp
         * (shifted by the app's +05:30 offset) instead of a local time --
         * the same defect fixed on MembershipFeeRate/DoctorTestPaymentMaster.
         */
        protected function serializeDate(\DateTimeInterface $date): string
        {
            return $date->format('Y-m-d H:i:s');
        }
}
