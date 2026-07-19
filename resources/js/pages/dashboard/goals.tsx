import { Form, Head } from '@inertiajs/react';
import { CalendarCheck, PauseCircle, PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { goals } from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import {
    destroy,
    pause,
    resume,
    store,
} from '@/actions/App/Http/Admin/Controllers/PublishingGoalController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Goal = {
    id: number;
    cadence: 'weekly' | 'monthly';
    target: number;
    effectiveFrom: string;
    effectiveUntil: string | null;
    periods: Array<{
        id: number;
        startsOn: string;
        endsOn: string;
        target: number;
        published: number;
        status: string;
    }>;
};

export default function DashboardGoals({ goals: history }: { goals: Goal[] }) {
    const [cadence, setCadence] = useState<'weekly' | 'monthly'>('weekly');
    const current = history.find((goal) => goal.effectiveUntil === null);

    return (
        <>
            <Head title="Publishing goals" />
            <div className="mx-auto grid w-full max-w-5xl gap-6 p-4 lg:p-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Publishing goals
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Goal changes begin at the next app-timezone boundary.
                        Completed periods remain immutable.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarCheck className="size-5" aria-hidden />
                            Set your next goal
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            className="grid gap-4 sm:grid-cols-[12rem_1fr_auto] sm:items-end"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="goal-cadence">
                                            Cadence
                                        </Label>
                                        <Select
                                            name="cadence"
                                            value={cadence}
                                            onValueChange={(value) =>
                                                setCadence(
                                                    value as
                                                        'weekly' | 'monthly',
                                                )
                                            }
                                        >
                                            <SelectTrigger id="goal-cadence">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="weekly">
                                                    Weekly
                                                </SelectItem>
                                                <SelectItem value="monthly">
                                                    Monthly
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="goal-target">
                                            Posts per{' '}
                                            {cadence === 'weekly'
                                                ? 'week'
                                                : 'month'}
                                        </Label>
                                        <Input
                                            id="goal-target"
                                            name="target"
                                            type="number"
                                            min={1}
                                            max={cadence === 'weekly' ? 7 : 31}
                                            defaultValue={
                                                cadence === 'weekly' ? 1 : 4
                                            }
                                            required
                                        />
                                        <InputError message={errors.target} />
                                    </div>
                                    <Button disabled={processing}>
                                        Save next goal
                                    </Button>
                                </>
                            )}
                        </Form>
                        {current && (
                            <Form {...destroy.form()} className="mt-4">
                                <Button type="submit" variant="ghost" size="sm">
                                    Choose no goal next period
                                </Button>
                            </Form>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4">
                    {history.length === 0 ? (
                        <Card>
                            <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                No goal history yet. Weekly presets are 1, 2, or
                                3 posts; any weekly target up to 7 is supported.
                            </CardContent>
                        </Card>
                    ) : (
                        history.map((goal) => (
                            <Card key={goal.id}>
                                <CardHeader>
                                    <CardTitle className="flex flex-wrap items-center justify-between gap-3">
                                        <span className="capitalize">
                                            {goal.target} posts {goal.cadence}
                                        </span>
                                        <Badge variant="outline">
                                            Effective {goal.effectiveFrom}
                                        </Badge>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-2">
                                    {goal.periods.map((period) => (
                                        <div
                                            key={period.id}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm"
                                        >
                                            <span>
                                                {period.startsOn} –{' '}
                                                {period.endsOn}
                                            </span>
                                            <span>
                                                {period.published}/
                                                {period.target} published
                                            </span>
                                            <Badge>{period.status}</Badge>
                                            {['active', 'scheduled'].includes(
                                                period.status,
                                            ) && (
                                                <Form
                                                    {...pause.form(period.id)}
                                                >
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <PauseCircle />
                                                        Pause period
                                                    </Button>
                                                </Form>
                                            )}
                                            {period.status === 'paused' && (
                                                <Form
                                                    {...resume.form(period.id)}
                                                >
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <PlayCircle />
                                                        Resume period
                                                    </Button>
                                                </Form>
                                            )}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

DashboardGoals.layout = {
    breadcrumbs: [{ title: 'Publishing goals', href: goals() }],
};
