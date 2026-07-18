import type { ReactNode } from 'react';

type Props = {
    title: string;
    description: string;
    action?: ReactNode;
};

export default function EmptyState({ title, description, action }: Props) {
    return (
        <div className="grid min-h-56 place-items-center rounded-xl border border-dashed p-8 text-center">
            <div className="grid justify-items-center gap-2">
                <span className="moon-dot mb-1 opacity-50" aria-hidden />
                <p className="font-display text-lg font-semibold">{title}</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
                {action && <div className="mt-2">{action}</div>}
            </div>
        </div>
    );
}
