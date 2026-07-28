import { Check, CircleHelp, Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AuthoringImageGuidance } from '@/types';

export function PhotoGuidance({
    guidance,
    onApplyMetadata,
}: {
    guidance: AuthoringImageGuidance;
    onApplyMetadata: () => void;
}) {
    return (
        <div className="grid gap-4 rounded-xl border border-primary/30 bg-primary/5 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2 text-sm font-semibold">
                    <Sparkles className="size-4 text-primary" />
                    AI story guidance
                </div>
                <div className="flex items-center gap-2">
                    <Badge
                        variant={
                            guidance.usage === 'include'
                                ? 'default'
                                : 'secondary'
                        }
                    >
                        {guidance.usage === 'include'
                            ? 'Worth including'
                            : 'Consider leaving out'}
                    </Badge>
                    {guidance.status === 'stale' && (
                        <Badge variant="destructive">Out of date</Badge>
                    )}
                </div>
            </div>

            <dl className="grid gap-3 text-sm">
                {guidance.section_heading && (
                    <div>
                        <dt className="font-medium">Best section</dt>
                        <dd className="text-muted-foreground">
                            {guidance.section_heading}
                        </dd>
                    </div>
                )}
                {guidance.narrative_role && (
                    <div>
                        <dt className="font-medium">Role in the story</dt>
                        <dd className="text-muted-foreground">
                            {guidance.narrative_role}
                        </dd>
                    </div>
                )}
                {guidance.writing_angle && (
                    <div>
                        <dt className="font-medium">What to write about</dt>
                        <dd className="text-muted-foreground">
                            {guidance.writing_angle}
                        </dd>
                    </div>
                )}
            </dl>

            {guidance.talking_points.length > 0 && (
                <div className="grid gap-2">
                    <p className="text-sm font-medium">Points to cover</p>
                    <ul className="grid gap-1.5 text-sm text-muted-foreground">
                        {guidance.talking_points.map((point) => (
                            <li
                                key={point}
                                className="grid grid-cols-[0.75rem_1fr] gap-2"
                            >
                                <span aria-hidden>•</span>
                                <span>{point}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {(guidance.transition_before || guidance.transition_after) && (
                <div className="grid gap-2 rounded-lg bg-background/80 p-3 text-sm">
                    {guidance.transition_before && (
                        <p>
                            <span className="font-medium">Lead in: </span>
                            <span className="text-muted-foreground">
                                {guidance.transition_before}
                            </span>
                        </p>
                    )}
                    {guidance.transition_after && (
                        <p>
                            <span className="font-medium">Lead out: </span>
                            <span className="text-muted-foreground">
                                {guidance.transition_after}
                            </span>
                        </p>
                    )}
                </div>
            )}

            {guidance.questions.length > 0 && (
                <div className="grid gap-2 rounded-lg border border-dashed p-3">
                    <p className="flex items-center gap-2 text-sm font-medium">
                        <CircleHelp className="size-4" />
                        Details only you can confirm
                    </p>
                    <ul className="grid gap-1 text-sm text-muted-foreground">
                        {guidance.questions.map((question) => (
                            <li key={question}>{question}</li>
                        ))}
                    </ul>
                </div>
            )}

            {guidance.rationale && (
                <p className="text-xs text-muted-foreground">
                    {guidance.rationale}
                </p>
            )}

            <Button
                type="button"
                size="sm"
                variant="outline"
                className="justify-self-start"
                disabled={guidance.status === 'stale'}
                onClick={onApplyMetadata}
            >
                <Check />
                Use suggested name, alt text, and caption
            </Button>
        </div>
    );
}
