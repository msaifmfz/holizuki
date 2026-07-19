import { Form, Head, router } from '@inertiajs/react';
import { Download, MailCheck, UserMinus } from 'lucide-react';
import { useState } from 'react';
import {
    exportMethod,
    index,
    resend,
    unsubscribe,
} from '@/actions/App/Http/Admin/Controllers/CommunitySubscriberController';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types';

type Subscriber = {
    id: number;
    email: string | null;
    status: string;
    source_method: string;
    source_location: string;
    source_content_key: string | null;
    confirmation_sent_at: string | null;
    confirmed_at: string | null;
};

export default function CommunitySubscribers({
    subscribers,
    filters,
}: {
    subscribers: Paginated<Subscriber>;
    filters: { email: string; status: string; source: string };
}) {
    const [email, setEmail] = useState(filters.email);
    const [status, setStatus] = useState(filters.status || 'all');

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            index.url({
                query: {
                    email: email || undefined,
                    status: status === 'all' ? undefined : status,
                },
            }),
            {},
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Subscribers" />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 lg:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Newsletter subscribers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Search uses an exact normalized email HMAC. CSV
                            exports stream confirmed active addresses without
                            persistence.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href={exportMethod.url()}>
                            <Download />
                            Export active CSV
                        </a>
                    </Button>
                </header>
                <form onSubmit={search} className="flex flex-wrap gap-2">
                    <Input
                        type="email"
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        placeholder="Exact email address"
                        aria-label="Subscriber email"
                        className="max-w-sm"
                    />
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger
                            className="w-44"
                            aria-label="Subscriber status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="confirmed">Confirmed</SelectItem>
                            <SelectItem value="unsubscribed">
                                Unsubscribed
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit">Filter</Button>
                </form>
                <Card>
                    <CardContent className="p-0">
                        {subscribers.data.length > 0 ? (
                            <div className="divide-y">
                                {subscribers.data.map((subscriber) => (
                                    <div
                                        key={subscriber.id}
                                        className="grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto] sm:items-center"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {subscriber.email ??
                                                    'Suppressed address'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {subscriber.source_location} ·{' '}
                                                {subscriber.source_content_key ??
                                                    'site'}
                                            </p>
                                        </div>
                                        <Badge variant="outline">
                                            {subscriber.status}
                                        </Badge>
                                        <div className="flex gap-2">
                                            {subscriber.status ===
                                                'pending' && (
                                                <Form
                                                    {...resend.form(
                                                        subscriber.id,
                                                    )}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <MailCheck />
                                                        Resend
                                                    </Button>
                                                </Form>
                                            )}
                                            {subscriber.status !==
                                                'unsubscribed' && (
                                                <Form
                                                    {...unsubscribe.form(
                                                        subscriber.id,
                                                    )}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                    >
                                                        <UserMinus />
                                                        Unsubscribe
                                                    </Button>
                                                </Form>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="p-10 text-center text-sm text-muted-foreground">
                                No subscribers match this exact filter.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Pagination paginator={subscribers} label="Subscriber pages" />
            </div>
        </>
    );
}

CommunitySubscribers.layout = {
    breadcrumbs: [{ title: 'Subscribers', href: index() }],
};
