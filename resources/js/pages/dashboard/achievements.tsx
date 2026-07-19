import { Deferred, Head } from '@inertiajs/react';
import { Award } from 'lucide-react';
import { achievements } from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type Milestone = {
    code: string;
    scopeKey: string;
    evidence: Record<string, unknown> | null;
    achievedAt: string;
};

export default function DashboardAchievements({
    milestones,
}: {
    milestones?: Milestone[];
}) {
    return (
        <>
            <Head title="Achievements" />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 lg:p-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Achievements
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Insert-once milestones preserve when progress first
                        happened.
                    </p>
                </header>
                <Deferred
                    data="milestones"
                    fallback={
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Skeleton className="h-36" />
                            <Skeleton className="h-36" />
                            <Skeleton className="h-36" />
                        </div>
                    }
                >
                    {milestones && milestones.length > 0 ? (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {milestones.map((milestone) => (
                                <Card
                                    key={`${milestone.code}:${milestone.scopeKey}`}
                                >
                                    <CardContent className="grid gap-3 pt-6">
                                        <Award
                                            className="size-8 text-amber-500"
                                            aria-hidden
                                        />
                                        <h2 className="font-semibold capitalize">
                                            {milestone.code.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Achieved{' '}
                                            {new Date(
                                                milestone.achievedAt,
                                            ).toLocaleDateString()}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <Card>
                            <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                Your first publishing, audience, and community
                                milestones will appear here.
                            </CardContent>
                        </Card>
                    )}
                </Deferred>
            </div>
        </>
    );
}

DashboardAchievements.layout = {
    breadcrumbs: [{ title: 'Achievements', href: achievements() }],
};
