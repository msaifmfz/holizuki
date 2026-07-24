<?php

use App\Domain\Assistant\Actions\PruneAssistantData;
use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\ClaudeTranscriptPruner;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-prune-'.uniqid();
    $this->assistantHome = sys_get_temp_dir().'/holizuki-assistant-home-'.uniqid();
    $this->externalRoots = [];
    config()->set('assistant.workspaces', $this->workspaceRoot);
    config()->set('assistant.home', $this->assistantHome);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
    File::deleteDirectory($this->assistantHome);

    foreach ($this->externalRoots as $externalRoot) {
        File::deleteDirectory($externalRoot);
    }
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

test('pruning preserves old turns with reviewable proposals', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    $session = AssistantSession::factory()->for($post, 'post')->create();
    $turn = AssistantTurn::factory()->for($session, 'session')->create([
        'created_at' => now()->subDays(60),
    ]);
    AssistantChange::factory()->for($turn, 'turn')->for($post, 'post')->create([
        'status' => AssistantChangeStatus::Proposed,
    ]);

    $this->artisan('assistant:prune')->assertSuccessful();

    expect($turn->fresh())->not->toBeNull();
});

test('pruning exposes removed session ids and workspace roots for transcript cleanup', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    $session = AssistantSession::factory()->for($post, 'post')->create([
        'updated_at' => now()->subDays(60),
    ]);
    File::ensureDirectoryExists($this->workspaceRoot.'/'.$post->id);

    $prune = resolve(PruneAssistantData::class);
    $prune->handle();

    expect($prune->prunedClaudeSessionIds())->toBe([$session->claude_session_id])
        ->and($prune->prunedWorkspaceRoots())->toBe([$this->workspaceRoot.'/'.$post->id]);
});

test('pruning removes inactive run directories under a live session', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    $activeRun = (string) Str::uuid();
    $staleRun = (string) Str::uuid();
    AssistantSession::factory()->running()->for($post, 'post')->create([
        'active_run_token' => $activeRun,
    ]);
    File::ensureDirectoryExists($this->workspaceRoot.'/'.$post->id.'/runs/'.$activeRun);
    File::ensureDirectoryExists($this->workspaceRoot.'/'.$post->id.'/runs/'.$staleRun);

    $this->artisan('assistant:prune')->assertSuccessful();

    expect(File::isDirectory($this->workspaceRoot.'/'.$post->id.'/runs/'.$activeRun))->toBeTrue()
        ->and(File::isDirectory($this->workspaceRoot.'/'.$post->id.'/runs/'.$staleRun))->toBeFalse();
});

test('the prune command deletes only transcripts for removed sessions', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    $session = AssistantSession::factory()->for($post, 'post')->create([
        'updated_at' => now()->subDays(60),
    ]);
    $otherSessionId = (string) Str::uuid();
    $projectDirectory = $this->assistantHome.'/.claude/projects/project';
    $sessionDirectory = $projectDirectory.'/'.$session->claude_session_id;
    $otherSessionDirectory = $projectDirectory.'/'.$otherSessionId;
    $fileHistoryRoot = $this->assistantHome.'/.claude/file-history';
    $sessionHistory = $fileHistoryRoot.'/'.$session->claude_session_id;
    $otherSessionHistory = $fileHistoryRoot.'/'.$otherSessionId;
    File::ensureDirectoryExists($projectDirectory);
    File::ensureDirectoryExists($sessionDirectory.'/tool-results');
    File::ensureDirectoryExists($otherSessionDirectory.'/tool-results');
    File::ensureDirectoryExists($sessionHistory);
    File::ensureDirectoryExists($otherSessionHistory);
    File::put($projectDirectory.'/'.$session->claude_session_id.'.jsonl', '{"type":"message"}');
    File::put($projectDirectory.'/'.$otherSessionId.'.jsonl', '{"type":"message"}');
    File::put($sessionDirectory.'/tool-results/result.txt', 'removed');
    File::put($otherSessionDirectory.'/tool-results/result.txt', 'retained');
    File::put($sessionHistory.'/snapshot.txt', 'removed');
    File::put($otherSessionHistory.'/snapshot.txt', 'retained');

    $this->artisan('assistant:prune')
        ->expectsOutputToContain('plus 1 Claude transcripts')
        ->assertSuccessful();

    expect(File::exists($projectDirectory.'/'.$session->claude_session_id.'.jsonl'))->toBeFalse()
        ->and(File::isDirectory($sessionDirectory))->toBeFalse()
        ->and(File::isDirectory($sessionHistory))->toBeFalse()
        ->and(File::exists($projectDirectory.'/'.$otherSessionId.'.jsonl'))->toBeTrue()
        ->and(File::exists($otherSessionDirectory.'/tool-results/result.txt'))->toBeTrue()
        ->and(File::exists($otherSessionHistory.'/snapshot.txt'))->toBeTrue();
});

test('the prune command expires rotated Claude artifacts but retains known sessions', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    $session = AssistantSession::factory()->for($post, 'post')->create();
    $rotatedSessionId = (string) Str::uuid();
    $projectDirectory = $this->assistantHome.'/.claude/projects/project';
    $retainedTranscript = $projectDirectory.'/'.$session->claude_session_id.'.jsonl';
    $rotatedTranscript = $projectDirectory.'/'.$rotatedSessionId.'.jsonl';
    $retainedSidecars = $projectDirectory.'/'.$session->claude_session_id;
    $rotatedSidecars = $projectDirectory.'/'.$rotatedSessionId;
    $fileHistoryRoot = $this->assistantHome.'/.claude/file-history';
    $retainedHistory = $fileHistoryRoot.'/'.$session->claude_session_id;
    $rotatedHistory = $fileHistoryRoot.'/'.$rotatedSessionId;
    File::ensureDirectoryExists($retainedSidecars.'/subagents');
    File::ensureDirectoryExists($rotatedSidecars.'/subagents');
    File::ensureDirectoryExists($retainedHistory);
    File::ensureDirectoryExists($rotatedHistory);
    File::put($retainedTranscript, '{"type":"message"}');
    File::put($rotatedTranscript, '{"type":"message"}');
    File::put($retainedSidecars.'/subagents/agent.jsonl', '{"type":"message"}');
    File::put($rotatedSidecars.'/subagents/agent.jsonl', '{"type":"message"}');
    File::put($retainedHistory.'/snapshot.txt', 'retained');
    File::put($rotatedHistory.'/snapshot.txt', 'removed');
    $oldTimestamp = now()->subDays(60)->getTimestamp();
    $ageDirectory = static function (string $directory) use ($oldTimestamp): void {
        foreach (File::allFiles($directory) as $file) {
            touch($file->getPathname(), $oldTimestamp);
        }

        foreach (array_reverse(File::allDirectories($directory)) as $childDirectory) {
            touch($childDirectory, $oldTimestamp);
        }

        touch($directory, $oldTimestamp);
    };
    touch($retainedTranscript, $oldTimestamp);
    touch($rotatedTranscript, $oldTimestamp);
    $ageDirectory($retainedSidecars);
    $ageDirectory($rotatedSidecars);
    $ageDirectory($retainedHistory);
    $ageDirectory($rotatedHistory);

    $this->artisan('assistant:prune')->assertSuccessful();

    expect(File::exists($retainedTranscript))->toBeTrue()
        ->and(File::exists($retainedSidecars.'/subagents/agent.jsonl'))->toBeTrue()
        ->and(File::exists($retainedHistory.'/snapshot.txt'))->toBeTrue()
        ->and(File::exists($rotatedTranscript))->toBeFalse()
        ->and(File::isDirectory($rotatedSidecars))->toBeFalse()
        ->and(File::isDirectory($rotatedHistory))->toBeFalse();
});

test('expired orphaned sidecars are pruned without a main transcript', function (): void {
    $sessionId = (string) Str::uuid();
    $sidecars = $this->assistantHome.'/.claude/projects/project/'.$sessionId;
    $fileHistory = $this->assistantHome.'/.claude/file-history/'.$sessionId;
    $oldTimestamp = now()->subDays(60)->getTimestamp();
    File::ensureDirectoryExists($sidecars.'/tool-results');
    File::ensureDirectoryExists($fileHistory);
    File::put($sidecars.'/tool-results/result.txt', 'removed');
    File::put($fileHistory.'/snapshot.txt', 'removed');

    foreach ([$sidecars.'/tool-results/result.txt', $fileHistory.'/snapshot.txt'] as $file) {
        touch($file, $oldTimestamp);
    }

    foreach ([$sidecars.'/tool-results', $sidecars, $fileHistory] as $directory) {
        touch($directory, $oldTimestamp);
    }

    $deleted = resolve(ClaudeTranscriptPruner::class)->deleteExpiredExcept(
        [],
        now()->subDays(30),
    );

    expect($deleted)->toBe(1)
        ->and(File::isDirectory($sidecars))->toBeFalse()
        ->and(File::isDirectory($fileHistory))->toBeFalse();
});

test('transcript cleanup does not follow a Claude data root symlink outside assistant home', function (): void {
    $sessionId = (string) Str::uuid();
    $externalRoot = sys_get_temp_dir().'/holizuki-external-claude-'.uniqid();
    $this->externalRoots[] = $externalRoot;
    $externalTranscript = $externalRoot.'/projects/project/'.$sessionId.'.jsonl';
    $externalHistory = $externalRoot.'/file-history/'.$sessionId.'/snapshot.txt';
    File::ensureDirectoryExists(dirname($externalTranscript));
    File::ensureDirectoryExists(dirname($externalHistory));
    File::put($externalTranscript, '{"type":"message"}');
    File::put($externalHistory, 'retained');
    File::ensureDirectoryExists($this->assistantHome);
    symlink($externalRoot, $this->assistantHome.'/.claude');

    $deleted = resolve(ClaudeTranscriptPruner::class)->deleteSessions([$sessionId]);

    expect($deleted)->toBe(0)
        ->and(File::exists($externalTranscript))->toBeTrue()
        ->and(File::exists($externalHistory))->toBeTrue();
});

test('transcript cleanup skips linked session artifacts without touching their targets', function (): void {
    $sessionId = (string) Str::uuid();
    $projectDirectory = $this->assistantHome.'/.claude/projects/project';
    $fileHistoryRoot = $this->assistantHome.'/.claude/file-history';
    $externalRoot = sys_get_temp_dir().'/holizuki-external-session-'.uniqid();
    $this->externalRoots[] = $externalRoot;
    $externalSidecars = $externalRoot.'/sidecars';
    $externalHistory = $externalRoot.'/history';
    File::ensureDirectoryExists($projectDirectory);
    File::ensureDirectoryExists($fileHistoryRoot);
    File::ensureDirectoryExists($externalSidecars);
    File::ensureDirectoryExists($externalHistory);
    File::put($projectDirectory.'/'.$sessionId.'.jsonl', '{"type":"message"}');
    File::put($externalSidecars.'/result.txt', 'retained');
    File::put($externalHistory.'/snapshot.txt', 'retained');
    symlink($externalSidecars, $projectDirectory.'/'.$sessionId);
    symlink($externalHistory, $fileHistoryRoot.'/'.$sessionId);

    $deleted = resolve(ClaudeTranscriptPruner::class)->deleteSessions([$sessionId]);

    expect($deleted)->toBe(1)
        ->and(File::exists($projectDirectory.'/'.$sessionId.'.jsonl'))->toBeFalse()
        ->and(is_link($projectDirectory.'/'.$sessionId))->toBeTrue()
        ->and(is_link($fileHistoryRoot.'/'.$sessionId))->toBeTrue()
        ->and(File::exists($externalSidecars.'/result.txt'))->toBeTrue()
        ->and(File::exists($externalHistory.'/snapshot.txt'))->toBeTrue();
});
