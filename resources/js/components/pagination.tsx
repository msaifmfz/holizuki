import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

type Props = {
    paginator: Pick<Paginated<unknown>, 'last_page' | 'links'>;
    label: string;
};

export default function Pagination({ paginator, label }: Props) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav className="flex flex-wrap justify-center gap-1" aria-label={label}>
            {paginator.links.map(
                (link) =>
                    link.url && (
                        <Button
                            key={link.label}
                            asChild
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                        >
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        </Button>
                    ),
            )}
        </nav>
    );
}
