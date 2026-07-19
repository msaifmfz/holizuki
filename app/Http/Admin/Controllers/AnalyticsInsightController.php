<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Analytics\Actions\DismissInsight;
use App\Domain\Analytics\Actions\TrackAuthorProductEvent;
use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Identity\Models\User;
use App\Http\Admin\Requests\DismissInsightRequest;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;

class AnalyticsInsightController extends Controller
{
    public function update(
        DismissInsightRequest $request,
        AnalyticsInsight $insight,
        DismissInsight $dismiss,
        TrackAuthorProductEvent $track,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);
        abort_unless($insight->user_id === $user->id, 403);
        $reason = $request->string('reason')->toString();
        $dismiss->handle($insight, $reason);

        $track->handle($user, 'insight_action', (string) $insight->id, [
            'rule_id' => $insight->rule_id,
            'reason' => $reason,
        ]);

        return back()->with('success', 'Recommendation updated.');
    }
}
