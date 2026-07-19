import { Form, Head } from '@inertiajs/react';
import {
    edit,
    update,
} from '@/actions/App/Http/Admin/Controllers/AnalyticsSettingsController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    environment: {
        collectionEnabled: boolean;
        dashboardEnabled: boolean;
        measurementId: string | null;
        propertyId: string | null;
        streamId: string | null;
        credentialsConfigured: boolean;
        timezone: string;
    };
    settings: {
        materialGapPoints: number;
        showExploratoryInsights: boolean;
    };
    manualCorrections: string[];
    syncHealth: {
        lastSuccessfulRefresh: string | null;
        lastError: string | null;
    };
    runs: Array<{
        id: string;
        command: string;
        status: string;
        rows: number;
        error: string | null;
        completedAt: string | null;
    }>;
};

export default function AnalyticsSettings({
    environment,
    settings,
    manualCorrections,
    syncHealth,
    runs,
}: Props) {
    return (
        <>
            <Head title="Analytics settings" />
            <div className="mx-auto grid w-full max-w-5xl gap-6 p-4 lg:p-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Analytics settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Google identifiers and credentials are environment-only
                        and masked here.
                    </p>
                </header>
                <Card>
                    <CardHeader>
                        <CardTitle>Environment configuration</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                        {Object.entries(environment).map(([key, value]) => (
                            <div
                                key={key}
                                className="flex justify-between gap-3 border-b py-2"
                            >
                                <span className="text-muted-foreground capitalize">
                                    {key.replaceAll(/([A-Z])/g, ' $1')}
                                </span>
                                <span className="font-mono text-xs">
                                    {typeof value === 'boolean'
                                        ? value
                                            ? 'Yes'
                                            : 'No'
                                        : (value ?? 'Not configured')}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Evidence thresholds</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...update.form()}
                            className="grid max-w-xl gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="material-gap">
                                            Material gap (percentage points)
                                        </Label>
                                        <Input
                                            id="material-gap"
                                            name="material_gap_points"
                                            type="number"
                                            min={5}
                                            max={50}
                                            defaultValue={
                                                settings.materialGapPoints
                                            }
                                        />
                                        <InputError
                                            message={errors.material_gap_points}
                                        />
                                    </div>
                                    <Label className="flex items-center gap-2">
                                        <input
                                            type="hidden"
                                            name="show_exploratory_insights"
                                            value="0"
                                        />
                                        <Checkbox
                                            name="show_exploratory_insights"
                                            value="1"
                                            defaultChecked={
                                                settings.showExploratoryInsights
                                            }
                                        />
                                        Show exploratory positive advice
                                    </Label>
                                    <Button
                                        className="w-fit"
                                        disabled={processing}
                                    >
                                        Save settings
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Manual privacy checks</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                            {manualCorrections.map((correction) => (
                                <li key={correction}>{correction}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Recent synchronization</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y p-0">
                        <div className="grid gap-1 px-6 py-4 text-sm">
                            <p>
                                Last successful refresh:{' '}
                                {syncHealth.lastSuccessfulRefresh
                                    ? new Date(
                                          syncHealth.lastSuccessfulRefresh,
                                      ).toLocaleString()
                                    : 'Not yet available'}
                            </p>
                            {syncHealth.lastError && (
                                <p className="text-destructive">
                                    Latest sanitized error:{' '}
                                    {syncHealth.lastError}
                                </p>
                            )}
                        </div>
                        {runs.map((run) => (
                            <div
                                key={run.id}
                                className="grid gap-2 px-6 py-3 text-sm sm:grid-cols-[1fr_auto_auto]"
                            >
                                <span>{run.command}</span>
                                <span>{run.rows} rows</span>
                                <Badge variant="outline">{run.status}</Badge>
                                {run.error && (
                                    <p className="text-destructive sm:col-span-3">
                                        {run.error}
                                    </p>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AnalyticsSettings.layout = {
    breadcrumbs: [{ title: 'Analytics settings', href: edit() }],
};
