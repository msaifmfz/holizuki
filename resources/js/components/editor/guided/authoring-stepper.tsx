import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AuthoringStep } from '@/types';

const STEP_LABELS: Record<AuthoringStep, string> = {
    idea: 'Idea',
    sources: 'Sources',
    plans: 'Plans',
    images: 'Story photos',
    draft: 'Draft',
    review: 'Review',
};

const AUTHORING_STEPS: AuthoringStep[] = [
    'idea',
    'sources',
    'plans',
    'images',
    'draft',
    'review',
];

export default function AuthoringStepper({
    steps,
    currentStep,
    completedSteps,
    onSelect,
}: {
    steps: AuthoringStep[];
    currentStep: AuthoringStep;
    completedSteps: AuthoringStep[];
    onSelect: (step: AuthoringStep) => void;
}) {
    return (
        <nav aria-label="Article progress">
            <ol className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                {steps.map((step, index) => {
                    const active = step === currentStep;
                    const completed = completedSteps.includes(step);

                    return (
                        <li key={step} className="min-w-0">
                            <button
                                type="button"
                                aria-current={active ? 'step' : undefined}
                                className={cn(
                                    'group grid w-full gap-1 rounded-lg border px-2 py-2.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    active
                                        ? 'border-primary bg-primary/10 text-foreground'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                )}
                                onClick={() => onSelect(step)}
                            >
                                <span className="flex items-center gap-1.5 text-xs font-medium">
                                    <span
                                        className={cn(
                                            'flex size-5 shrink-0 items-center justify-center rounded-full border text-[0.65rem]',
                                            active &&
                                                'border-primary bg-primary text-primary-foreground',
                                            completed &&
                                                !active &&
                                                'border-primary/40 bg-primary/10 text-primary',
                                        )}
                                        aria-hidden
                                    >
                                        {completed && !active ? (
                                            <Check className="size-3" />
                                        ) : (
                                            index + 1
                                        )}
                                    </span>
                                    <span className="truncate">
                                        {STEP_LABELS[step]}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'h-1 rounded-full bg-muted',
                                        (active || completed) && 'bg-primary',
                                    )}
                                    aria-hidden
                                />
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

export { AUTHORING_STEPS, STEP_LABELS };
