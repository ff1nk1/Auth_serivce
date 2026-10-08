<?php

namespace Tests\Feature;

use Tests\TestCase;

class LivewireAdminUploadRoutesTest extends TestCase
{
    public function test_livewire_upload_and_preview_resolve_under_admin_path(): void
    {
        $upload = route('livewire.upload-file', absolute: false);
        $preview = route('livewire.preview-file', ['filename' => 'example.jpg'], absolute: false);
        $update = app('livewire')->getUpdateUri();

        $this->assertSame('/admin/livewire/upload-file', $upload);
        $this->assertSame('/admin/livewire/preview-file/example.jpg', $preview);
        $this->assertSame('/admin/livewire/update', $update);
    }

    public function test_absolute_upload_url_stays_under_admin(): void
    {
        $upload = route('livewire.upload-file');

        $this->assertStringContainsString('/admin/livewire/upload-file', $upload);
        $this->assertStringNotContainsString('/livewire-', parse_url($upload, PHP_URL_PATH) ?? '');
    }
}
