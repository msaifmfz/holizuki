import {
    Check,
    FileText,
    LoaderCircle,
    MoveRight,
    RefreshCw,
    Sparkles,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { AuthoringPlanOption } from '@/types';

export default function PlansStep({
    plans,
    generating,
    assistantAvailable,
    error,
    onGenerate,
    onSelect,
}: {
    plans: AuthoringPlanOption[];
    generating: boolean;
    assistantAvailable: boolean;
    error: string | null;
    onGenerate: () => Promise<void> | void;
    onSelect: (plan: AuthoringPlanOption) => Promise<void> | void;
}) {
    return (
        <div className="grid gap-5">
            <Card>
                <CardHeader className="sm:flex-row sm:items-start sm:justify-between">
                    <div className="grid gap-1.5">
                        <CardTitle>Choose an article plan</CardTitle>
                        <CardDescription>
                            The assistant offers different angles. Nothing is
                            added to your draft until you choose.
                        </CardDescription>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={generating || !assistantAvailable}
                        onClick={() => void onGenerate()}
                    >
                        {generating ? (
                            <LoaderCircle className="animate-spin" />
                        ) : plans.length > 0 ? (
                            <RefreshCw />
                        ) : (
                            <Sparkles />
                        )}
                        {generating
                            ? 'Preparing plans…'
                            : plans.length > 0
                              ? 'Create three new plans'
                              : 'Create three plans'}
                    </Button>
                </CardHeader>
                {error && (
                    <CardContent>
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    </CardContent>
                )}
            </Card>

            {plans.length === 0 ? (
                <div className="flex min-h-52 flex-col items-center justify-center gap-3 rounded-lg border bg-muted/30 p-6 text-center">
                    {generating ? (
                        <LoaderCircle className="size-8 animate-spin text-primary" />
                    ) : (
                        <FileText className="size-8 text-muted-foreground" />
                    )}
                    <div>
                        <p className="font-medium">
                            {generating
                                ? 'The assistant is preparing three approaches'
                                : 'No plans yet'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            You can go back and add more detail, or create plans
                            from what you have.
                        </p>
                    </div>
                </div>
            ) : (
                <div className="grid items-start gap-5 xl:grid-cols-3">
                    {plans.map((plan, index) => (
                        <Card
                            key={plan.id}
                            className={cn(
                                'h-full transition-colors',
                                plan.selected &&
                                    'border-primary ring-1 ring-primary',
                            )}
                        >
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <Badge variant="outline">
                                        Option {index + 1}
                                    </Badge>
                                    {plan.selected && (
                                        <Badge>
                                            <Check />
                                            Selected
                                        </Badge>
                                    )}
                                    {plan.status === 'stale' && (
                                        <Badge variant="destructive">
                                            Out of date
                                        </Badge>
                                    )}
                                </div>
                                <CardTitle className="pt-2 text-lg">
                                    {plan.title}
                                </CardTitle>
                                <CardDescription>{plan.angle}</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <ol className="grid gap-3">
                                    {plan.sections.map(
                                        (section, sectionIndex) => (
                                            <li
                                                key={`${section.heading}-${sectionIndex}`}
                                                className="grid grid-cols-[1.5rem_1fr] gap-2"
                                            >
                                                <span className="flex size-6 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                                    {sectionIndex + 1}
                                                </span>
                                                <span>
                                                    <span className="block text-sm font-medium">
                                                        {section.heading}
                                                    </span>
                                                    {section.summary !== '' && (
                                                        <span className="mt-0.5 block text-xs text-muted-foreground">
                                                            {section.summary}
                                                        </span>
                                                    )}
                                                </span>
                                            </li>
                                        ),
                                    )}
                                </ol>
                                <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <Badge variant="secondary">
                                        {plan.estimated_length}
                                    </Badge>
                                    {plan.source_ids.length > 0 && (
                                        <Badge variant="outline">
                                            {plan.source_ids.length}{' '}
                                            {plan.source_ids.length === 1
                                                ? 'source'
                                                : 'sources'}
                                        </Badge>
                                    )}
                                </div>
                            </CardContent>
                            <CardFooter className="mt-auto">
                                <Button
                                    type="button"
                                    className="w-full"
                                    disabled={plan.status === 'stale'}
                                    onClick={() => void onSelect(plan)}
                                >
                                    {plan.selected ? (
                                        <>
                                            Continue with this plan
                                            <MoveRight />
                                        </>
                                    ) : (
                                        <>
                                            Choose plan and add photos
                                            <MoveRight />
                                        </>
                                    )}
                                </Button>
                            </CardFooter>
                        </Card>
                    ))}
                </div>
            )}

            {!assistantAvailable && (
                <p className="text-sm text-muted-foreground">
                    The assistant is unavailable. You can continue in the full
                    editor and write manually.
                </p>
            )}
        </div>
    );
}
