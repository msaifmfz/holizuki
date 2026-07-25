import type { ReactNode } from 'react';

type Props = {
    title: string;
    description: string;
    action?: ReactNode;
};

/**
 * Nothing-here states.
 *
 * Left-aligned against a rule rather than centred in a dashed box: an empty
 * result set is a normal outcome of searching, not a broken panel, and it should
 * sit on the page the same way a result would. The new moon is the honest mark
 * for it — the one phase where there is nothing to see.
 */
export default function EmptyState({ title, description, action }: Props) {
    return (
        <div className="grid gap-3 border-t border-border py-14">
            <svg
                viewBox="0 0 100 100"
                width="28"
                height="28"
                aria-hidden
                className="text-brand-accent"
            >
                <circle
                    cx="50"
                    cy="50"
                    r="49"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1"
                    strokeOpacity="0.5"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>
            <p className="display-md">{title}</p>
            <p className="max-w-md font-body text-lg leading-relaxed text-muted-foreground">
                {description}
            </p>
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
