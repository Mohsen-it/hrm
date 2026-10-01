<?php

namespace Modules\Backups\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create-backups') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // External file from USB/flash drive: plain .sql (HeidiSQL etc.),
            // gzipped .sql.gz / .gz, or our encrypted .sql.gz.enc / .enc.
            // 2GB max (server php.ini post_max_size/upload_max_filesize still apply).
            'backup_file' => ['required', 'file', 'max:2097152', 'extensions:sql,gz,enc'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'backup_file.required' => __('backups.upload_required'),
            'backup_file.max' => __('backups.upload_too_large'),
            'backup_file.extensions' => __('backups.upload_invalid_type'),
        ];
    }
}
