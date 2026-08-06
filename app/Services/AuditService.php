<?php

/**
 * --------------------------------------------------------------------------
 * OHMS - Open Hospital Management System
 * --------------------------------------------------------------------------
 *
 * File        : AuditService.php
 * Module      : Audit Framework
 * Purpose     : Centralized Audit Logging Service
 *
 * PHP Version : 8.3+
 * Laravel     : 13.x
 * --------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditService
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * Current Request UUID
     */
    protected static ?string $requestId = null;

    /**
     * Audit Enabled
     */
    protected bool $enabled;

    /**
     * Ignored Fields
     */
    protected array $ignoredFields = [];

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->enabled = (bool) config('audit.enabled', true);

        $this->ignoredFields = config(
            'audit.ignored_fields',
            []
        );

        $this->generateRequestId();
    }

    /*
    |--------------------------------------------------------------------------
    | Public Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Log Create
     *
     * @param string $moduleCode
     * @param Model  $model
     * @param array  $newData
     * @param string|null $remarks
     *
     * @return bool
     */
    public function logCreate(
        string $moduleCode,
        Model $model,
        array $newData,
        ?string $remarks = null
    ): bool {

        if (!$this->enabled) {
            return true;
        }

        try {

            return $this->saveAudit(

                moduleCode: $moduleCode,

                moduleName: $this->getModuleName($moduleCode),

                tableName: $model->getTable(),

                recordId: (string) $model->getKey(),

                action: AuditLog::ACTION_CREATE,

                oldData: null,

                newData: $this->filterIgnoredFields($newData),

                changedData: $this->filterIgnoredFields($newData),

                remarks: $remarks

            );

        } catch (Throwable $e) {

            Log::error(
                'Audit Create Failed',
                [
                    'module' => $moduleCode,
                    'message' => $e->getMessage(),
                ]
            );

            return false;

        }
    }

    /**
     * Log Update
     *
     * Only records an entry when at least one non-ignored field actually
     * changed (unless audit.ignore_empty_update is disabled).
     *
     * @param string $moduleCode
     * @param Model  $model
     * @param array  $oldData
     * @param array  $newData
     * @param string|null $remarks
     *
     * @return bool
     */
    public function logUpdate(
        string $moduleCode,
        Model $model,
        array $oldData,
        array $newData,
        ?string $remarks = null
    ): bool {

        if (!$this->enabled) {
            return true;
        }

        try {

            $oldFiltered = $this->filterIgnoredFields($oldData);
            $newFiltered = $this->filterIgnoredFields($newData);

            $changedData = $this->diffData($oldFiltered, $newFiltered);

            if (
                empty($changedData) &&
                config('audit.ignore_empty_update', true)
            ) {
                return true;
            }

            return $this->saveAudit(

                moduleCode: $moduleCode,

                moduleName: $this->getModuleName($moduleCode),

                tableName: $model->getTable(),

                recordId: (string) $model->getKey(),

                action: AuditLog::ACTION_UPDATE,

                oldData: $oldFiltered,

                newData: $newFiltered,

                changedData: $changedData,

                remarks: $remarks

            );

        } catch (Throwable $e) {

            Log::error(
                'Audit Update Failed',
                [
                    'module' => $moduleCode,
                    'message' => $e->getMessage(),
                ]
            );

            return false;

        }
    }

    /**
     * Log Delete
     *
     * @param string $moduleCode
     * @param Model  $model
     * @param array  $oldData
     * @param string|null $remarks
     *
     * @return bool
     */
    public function logDelete(
        string $moduleCode,
        Model $model,
        array $oldData,
        ?string $remarks = null
    ): bool {

        if (!$this->enabled) {
            return true;
        }

        try {

            return $this->saveAudit(

                moduleCode: $moduleCode,

                moduleName: $this->getModuleName($moduleCode),

                tableName: $model->getTable(),

                recordId: (string) $model->getKey(),

                action: AuditLog::ACTION_DELETE,

                oldData: $this->filterIgnoredFields($oldData),

                newData: null,

                changedData: null,

                remarks: $remarks

            );

        } catch (Throwable $e) {

            Log::error(
                'Audit Delete Failed',
                [
                    'module' => $moduleCode,
                    'message' => $e->getMessage(),
                ]
            );

            return false;

        }
    }

    /**
     * Log a custom action (PRINT, EXPORT_EXCEL, EXPORT_PDF, WHATSAPP, CUSTOM, ...)
     *
     * @param string $moduleCode
     * @param Model  $model
     * @param string $action
     * @param string|null $remarks
     *
     * @return bool
     */
    public function logAction(
        string $moduleCode,
        Model $model,
        string $action,
        ?string $remarks = null
    ): bool {

        if (!$this->enabled) {
            return true;
        }

        try {

            return $this->saveAudit(

                moduleCode: $moduleCode,

                moduleName: $this->getModuleName($moduleCode),

                tableName: $model->getTable(),

                recordId: (string) $model->getKey(),

                action: $action,

                oldData: null,

                newData: null,

                changedData: null,

                remarks: $remarks

            );

        } catch (Throwable $e) {

            Log::error(
                'Audit Action Failed',
                [
                    'module' => $moduleCode,
                    'action' => $action,
                    'message' => $e->getMessage(),
                ]
            );

            return false;

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Persist One Audit Row
     */
    private function saveAudit(
        string $moduleCode,
        string $moduleName,
        string $tableName,
        string $recordId,
        string $action,
        ?array $oldData,
        ?array $newData,
        ?array $changedData,
        ?string $remarks
    ): bool {

        AuditLog::create([

            'request_id' => config('audit.store_request_id', true)
                ? self::$requestId
                : null,

            'module_code' => $moduleCode,
            'module_name' => $moduleName,

            'table_name' => $tableName,
            'record_id' => $recordId,

            'action' => $action,

            'old_data' => $this->truncateValues($oldData),
            'new_data' => $this->truncateValues($newData),
            'changed_data' => $this->truncateValues($changedData),

            'remarks' => $remarks,

            'user_id' => $this->getUserId(),
            'user_name' => $this->getUserName(),

            'ip_address' => config('audit.store_ip', true)
                ? $this->getIPAddress()
                : null,

            'user_agent' => config('audit.store_user_agent', true)
                ? $this->getUserAgent()
                : null,

            'request_url' => config('audit.store_request_url', true)
                ? $this->getRequestUrl()
                : null,

            'created_at' => now(),

        ]);

        return true;
    }

    /**
     * Compute Changed Fields (new value differs from old, or key is new)
     */
    private function diffData(array $oldData, array $newData): array
    {
        $changed = [];

        foreach ($newData as $key => $value) {

            if (
                !array_key_exists($key, $oldData) ||
                $oldData[$key] != $value
            ) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    /**
     * Truncate Overly Long Values Before Storage
     */
    private function truncateValues(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $maxLength = config('audit.max_value_length', 5000);

        foreach ($data as $key => $value) {

            if (is_string($value) && strlen($value) > $maxLength) {
                $data[$key] = substr($value, 0, $maxLength) . '...(truncated)';
            }
        }

        return $data;
    }

    /**
     * Generate Request UUID
     *
     * One UUID is shared by the entire HTTP request.
     */
    private function generateRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = (string) Str::uuid();
        }

        return self::$requestId;
    }

    /**
     * Remove Ignored Fields
     */
    private function filterIgnoredFields(
        array $data
    ): array {

        foreach ($this->ignoredFields as $field) {

            unset($data[$field]);

        }

        return $data;

    }

    /**
     * Get Module Display Name
     */
    private function getModuleName(
        string $moduleCode
    ): string {

        return config(
            "audit.modules.$moduleCode",
            $moduleCode
        );

    }

    /**
     * Current User ID
     */
    private function getUserId()
    {
        return Auth::id();
    }

    /**
     * Current User Name
     */
    private function getUserName()
    {
        return Auth::user()?->name;
    }

    /**
     * Current IP
     */
    private function getIPAddress()
    {
        return request()->ip();
    }

    /**
     * Current Browser
     */
    private function getUserAgent()
    {
        return request()->userAgent();
    }

    /**
     * Current URL
     */
    private function getRequestUrl()
    {
        return request()->fullUrl();
    }
}
