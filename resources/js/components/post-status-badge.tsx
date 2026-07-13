import { Badge } from '@/components/ui/badge';
import type { PostSummary } from '@/types';

export default function PostStatusBadge({
    status,
}: {
    status: PostSummary['status'];
}) {
    const variant =
        status === 'published'
            ? 'default'
            : status === 'scheduled'
              ? 'secondary'
              : status === 'trashed'
                ? 'destructive'
                : 'outline';

    return (
        <Badge variant={variant}>
            {status[0].toUpperCase() + status.slice(1)}
        </Badge>
    );
}
