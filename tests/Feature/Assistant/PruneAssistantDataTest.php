<?php

use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-prune-'.uniqid();
    config()->set('assistant.workspaces', $this->workspaceRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

test('pruning removes old turns and orphaned workspaces but keeps active ones', function (): void {
    $user = User::factory()->create();
    $activePost = Post::factory()->for($user, 'author')->create();
    $session = AssistantSession::factory()->for($activePost, 'post')->create();

    $oldTurn = AssistantTurn::factory()->for($session, 'session')->create();
    $oldTurn->forceFill(['created_at' => now()->subDays(60)])->save();
    $freshTurn = AssistantTurn::factory()->for($session, 'session')->create();

    File::ensureDirectoryExists($this->workspaceRoot.'/'.$activePost->id);
    File::ensureDirectoryExists($this->workspaceRoot.'/999999');
    File::ensureDirectoryExists($this->workspaceRoot.'/not-a-post-id');

    $this->artisan('assistant:prune')
        ->expectsOutputToContain('Pruned')
        ->assertSuccessful();

    expect(AssistantTurn::query()->pluck('id')->all())->toBe([$freshTurn->id])
        ->and(File::isDirectory($this->workspaceRoot.'/'.$activePost->id))->toBeTrue()
        ->and(File::isDirectory($this->workspaceRoot.'/999999'))->toBeFalse()
        ->and(File::isDirectory($this->workspaceRoot.'/not-a-post-id'))->toBeTrue();
});

test('pruning drops stale sessions that have no turns left', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    $session = AssistantSession::factory()->for($post, 'post')->create();
    $session->forceFill(['updated_at' => now()->subDays(60)])->save();

    $this->artisan('assistant:prune')->assertSuccessful();

    expect(AssistantSession::query()->count())->toBe(0);
});
