import {
    CalendarClock,
    CheckCircle2,
    Circle,
    Eye,
    LoaderCircle,
    Send,
} from 'lucide-react';
import type { ReactNode } from 'react';
import PostStatusBadge from '@/components/post-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ReviewCheck = {
    id: string;
    label: string;
    help: string;
    complete: boolean;
};

export default function ReviewStep({
    status,
    checks,
    publishing,
    scheduleAt,
    publishingErrors,
    organization,
    image,
    onScheduleAtChange,
    onPreview,
    onPublish,
    onSchedule,
}: {
    status: 'draft' | 'scheduled' | 'published' | 'trashed';
    checks: ReviewCheck[];
    publishing: boolean;
    scheduleAt: string;
    publishingErrors: string[];
    organization: ReactNode;
    image: ReactNode;
    onScheduleAtChange: (value: string) => void;
    onPreview: () => Promise<void> | void;
    onPublish: () => Promise<void> | void;
    onSchedule: () => Promise<void> | void;
}) {
    const incompleteCount = checks.filter((check) => !check.complete).length;
    const ready = incompleteCount === 0;

    return (
        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
            <div className="grid content-start gap-5">
                <Card>
                    <CardHeader className="sm:flex-row sm:items-start sm:justify-between">
                        <div className="grid gap-1.5">
                            <CardTitle>Final check</CardTitle>
                            <CardDescription>
                                Fix only what is missing. The full editor
                                remains available for optional SEO and advanced
                                settings.
                            </CardDescription>
                        </div>
                        <PostStatusBadge status={status} />
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {checks.map((check) => (
                            <div
                                key={check.id}
                                className="grid grid-cols-[1.25rem_1fr] gap-3 rounded-lg border p-3"
                            >
                                {check.complete ? (
                                    <CheckCircle2 className="mt-0.5 size-5 text-primary" />
                                ) : (
                                    <Circle className="mt-0.5 size-5 text-muted-foreground" />
                                )}
                                <div>
                                    <p className="text-sm font-medium">
                                        {check.label}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {check.help}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-5 xl:grid-cols-2">
                    {organization}
                    {image}
                </div>
            </div>

            <div className="grid content-start gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Preview and publish
                        </CardTitle>
                        <CardDescription>
                            Publishing copies this private working draft to the
                            public article.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {publishingErrors.length > 0 && (
                            <Alert variant="destructive">
                                <AlertTitle>Cannot publish yet</AlertTitle>
                                <AlertDescription>
                                    <ul className="list-disc pl-4">
                                        {publishingErrors.map((error) => (
                                            <li key={error}>{error}</li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}

                        {!ready && (
                            <p className="text-sm text-muted-foreground">
                                {incompleteCount}{' '}
                                {incompleteCount === 1
                                    ? 'item is'
                                    : 'items are'}{' '}
                                still required.
                            </p>
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            disabled={publishing}
                            onClick={() => void onPreview()}
                        >
                            <Eye />
                            Preview working draft
                        </Button>

                        <Button
                            type="button"
                            disabled={!ready || publishing}
                            onClick={() => void onPublish()}
                        >
                            {publishing ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <Send />
                            )}
                            Publish now
                        </Button>

                        <div className="grid gap-2 border-t pt-4">
                            <Label htmlFor="guided-schedule-at">
                                Or schedule for later
                            </Label>
                            <Input
                                id="guided-schedule-at"
                                type="datetime-local"
                                value={scheduleAt}
                                onChange={(event) =>
                                    onScheduleAtChange(event.target.value)
                                }
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={!ready || !scheduleAt || publishing}
                                onClick={() => void onSchedule()}
                            >
                                <CalendarClock />
                                Schedule this version
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                Later edits will not change the scheduled
                                version unless you schedule again.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
