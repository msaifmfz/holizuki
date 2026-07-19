<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Analytics\Actions\PauseGoalPeriod;
use App\Domain\Analytics\Actions\ResumeGoalPeriod;
use App\Domain\Analytics\Actions\SetPublishingGoal;
use App\Domain\Analytics\Enums\GoalCadence;
use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Identity\Models\User;
use App\Http\Admin\Requests\PauseGoalPeriodRequest;
use App\Http\Admin\Requests\StorePublishingGoalRequest;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublishingGoalController extends Controller
{
    public function store(StorePublishingGoalRequest $request, SetPublishingGoal $setGoal): RedirectResponse
    {
        $user = $this->administrator($request->user());
        $setGoal->handle(
            $user,
            GoalCadence::from($request->string('cadence')->toString()),
            $request->integer('target'),
        );

        return back()->with('success', 'Your new goal will begin at the next period boundary.');
    }

    public function destroy(Request $request, SetPublishingGoal $setGoal): RedirectResponse
    {
        $setGoal->disable($this->administrator($request->user()));

        return back()->with('success', 'Your goal will turn off at the next period boundary.');
    }

    public function pause(
        PauseGoalPeriodRequest $request,
        AuthorGoalPeriod $period,
        PauseGoalPeriod $pause,
    ): RedirectResponse {
        $reason = $request->validated('reason');
        $paused = $pause->handle(
            $this->administrator($request->user()),
            $period,
            is_string($reason) ? $reason : null,
        );

        return back()->with('success', $paused->status === GoalPeriodStatus::Met
            ? 'This period already met its target, so it was finalized as met instead of paused.'
            : 'Goal period paused.');
    }

    public function resume(
        Request $request,
        AuthorGoalPeriod $period,
        ResumeGoalPeriod $resume,
    ): RedirectResponse {
        $user = $this->administrator($request->user());
        abort_unless($period->user_id === $user->id, 403);
        $resume->handle($user, $period);

        return back()->with('success', 'Goal period resumed.');
    }

    private function administrator(mixed $user): User
    {
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        return $user;
    }
}
