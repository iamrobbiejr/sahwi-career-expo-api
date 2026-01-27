<?php

namespace Tests\Feature\Api\Communications;

use App\Models\Message;
use App\Models\Thread;
use App\Models\ThreadMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_attachment_from_own_thread()
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'student']);

        $thread = Thread::create([
            'title' => 'Test Thread',
            'thread_type' => 'group',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        ThreadMember::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'status' => 'active',
            'role' => 'member'
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $path = $file->store('message-attachments', 'private');

        $message = Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $user->id,
            'content' => 'Test message with attachment',
            'attachments' => [
                [
                    'path' => $path,
                    'name' => 'document.pdf',
                    'size' => 100 * 1024,
                    'mime_type' => 'application/pdf',
                ]
            ],
            'status' => 'sent',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/message-attachments/{$message->id}/0");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=document.pdf');
    }

    public function test_user_cannot_download_attachment_from_other_thread()
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'student']);
        $otherUser = User::factory()->create(['role' => 'student']);

        $thread = Thread::create([
            'title' => 'Secret Thread',
            'thread_type' => 'group',
            'created_by' => $otherUser->id,
            'is_active' => true,
        ]);

        // Only otherUser is in thread
        ThreadMember::create([
            'thread_id' => $thread->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
            'role' => 'member'
        ]);

        $file = UploadedFile::fake()->create('secret.pdf', 100);
        $path = $file->store('message-attachments', 'private');

        $message = Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $otherUser->id,
            'content' => 'Secret message',
            'attachments' => [
                [
                    'path' => $path,
                    'name' => 'secret.pdf',
                    'size' => 100 * 1024,
                    'mime_type' => 'application/pdf',
                ]
            ],
            'status' => 'sent',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/message-attachments/{$message->id}/0");

        $response->assertStatus(403);
    }

    public function test_returns_404_if_attachment_index_invalid()
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'student']);

        $thread = Thread::create([
            'title' => 'Test Thread',
            'thread_type' => 'group',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        ThreadMember::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'status' => 'active',
            'role' => 'member'
        ]);

        $message = Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $user->id,
            'content' => 'No attachments here',
            'attachments' => [],
            'status' => 'sent',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/message-attachments/{$message->id}/0");

        $response->assertStatus(404);
    }

    public function test_returns_404_if_file_missing_on_server()
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'student']);

        $thread = Thread::create([
            'title' => 'Test Thread',
            'thread_type' => 'group',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        ThreadMember::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'status' => 'active',
            'role' => 'member'
        ]);

        $message = Message::create([
            'thread_id' => $thread->id,
            'sender_id' => $user->id,
            'content' => 'Test message with missing file',
            'attachments' => [
                [
                    'path' => 'non/existent/file.pdf',
                    'name' => 'file.pdf',
                ]
            ],
            'status' => 'sent',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/message-attachments/{$message->id}/0");

        $response->assertStatus(404);
        $response->assertJson(['error' => 'File not found on server']);
    }
}
