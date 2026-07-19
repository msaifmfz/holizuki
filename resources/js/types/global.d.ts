import type { Auth } from '@/types/auth';
import type { FooterCategory } from '@/types/public';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            footerCategories: FooterCategory[];
            flash: {
                success: string | null;
                commentSubmitted: number | null;
            };
            community: {
                consentVersion: string;
                sharingMethods: string[];
            };
            analytics: {
                collectionEnabled: boolean;
                measurementId: string | null;
                consentVersion: string;
                consentDays: number;
            };
            [key: string]: unknown;
        };
    }
}
