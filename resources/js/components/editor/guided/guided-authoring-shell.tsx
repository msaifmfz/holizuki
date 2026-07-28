import { ArrowLeft, ArrowRight, LayoutDashboard } from 'lucide-react';
import { useEffect, useRef } from 'react';
import AuthoringStepper, {
    AUTHORING_STEPS,
    STEP_LABELS,
} from '@/components/editor/guided/authoring-stepper';
import { Button } from '@/components/ui/button';
import type { AuthoringJourneyData, AuthoringStep } from '@/types';

export default function GuidedAuthoringShell({
    journey,
    saveLabel,
    canContinue,
    children,
    onStepChange,
    onOpenAdvanced,
}: {
    journey: AuthoringJourneyData;
    saveLabel: string;
    canContinue: boolean;
    children: React.ReactNode;
    onStepChange: (step: AuthoringStep) => void;
    onOpenAdvanced: () => void;
}) {
    const headingRef = useRef<HTMLHeadingElement>(null);
    const currentIndex = AUTHORING_STEPS.indexOf(journey.current_step);
    const previousStep =
        currentIndex > 0 ? AUTHORING_STEPS[currentIndex - 1] : null;
    const nextStep =
        currentIndex < AUTHORING_STEPS.length - 1
            ? AUTHORING_STEPS[currentIndex + 1]
            : null;

    useEffect(() => {
        headingRef.current?.focus();
    }, [journey.current_step]);

    return (
        <div className="mx-auto grid w-full max-w-6xl gap-5 p-4 pb-24 lg:p-6 lg:pb-28">
            <header className="grid gap-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="grid gap-1">
                        <p className="text-sm font-medium text-primary">
                            {journey.kind === 'creation'
                                ? 'Guided article'
                                : 'Guided improvement'}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Write one clear step at a time
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Article fields save automatically. Photo-flow and
                            passage choices show a Save button when they need
                            your approval.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onOpenAdvanced}
                    >
                        <LayoutDashboard />
                        Open full editor
                    </Button>
                </div>
                <AuthoringStepper
                    steps={AUTHORING_STEPS}
                    currentStep={journey.current_step}
                    completedSteps={journey.completed_steps}
                    onSelect={onStepChange}
                />
            </header>

            <main className="grid gap-5">
                <div className="sr-only" aria-live="polite">
                    {STEP_LABELS[journey.current_step]} step. {saveLabel}
                </div>
                <h2
                    ref={headingRef}
                    tabIndex={-1}
                    className="sr-only focus:outline-none"
                >
                    {STEP_LABELS[journey.current_step]}
                </h2>
                {children}
            </main>

            <footer className="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 p-3 shadow-[0_-8px_24px_-16px_rgba(0,0,0,0.35)] backdrop-blur supports-[backdrop-filter]:bg-background/80">
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={previousStep === null}
                        onClick={() => {
                            if (previousStep) {
                                onStepChange(previousStep);
                            }
                        }}
                    >
                        <ArrowLeft />
                        Back
                    </Button>
                    <p className="hidden text-sm text-muted-foreground sm:block">
                        {saveLabel}
                    </p>
                    {nextStep ? (
                        <Button
                            type="button"
                            disabled={!canContinue}
                            onClick={() => onStepChange(nextStep)}
                        >
                            Continue
                            <ArrowRight />
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onOpenAdvanced}
                        >
                            Review in full editor
                            <ArrowRight />
                        </Button>
                    )}
                </div>
            </footer>
        </div>
    );
}
