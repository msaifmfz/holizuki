import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { saveAnalyticsConsent } from '@/analytics/consent';
import {
    configureAnalytics,
    resetAnalyticsForTests,
} from '@/analytics/tracker';
import ShareTools from '@/components/public/share-tools';

describe('share tools analytics', () => {
    beforeEach(() => {
        window.localStorage.clear();
        document.head.innerHTML = '';
        window.dataLayer = undefined;
        window.gtag = undefined;
        resetAnalyticsForTests();
        configureAnalytics({
            collectionEnabled: true,
            measurementId: 'G-TEST123',
            consentVersion: 'v1',
            eligible: true,
        });
        saveAnalyticsConsent('v1', 'accepted', 180);
    });

    it('emits share only after clipboard success', async () => {
        const user = userEvent.setup();
        const writeText = vi.spyOn(navigator.clipboard, 'writeText');
        render(
            <ShareTools title="Measured post" methods={['copy']} postId={42} />,
        );

        writeText.mockResolvedValueOnce();
        await user.click(screen.getByRole('button', { name: 'Copy link' }));
        expect(window.dataLayer).toContainEqual([
            'event',
            'share',
            {
                method: 'copy',
                content_type: 'article',
                item_id: 'post:42',
                content_key: 'post:42',
            },
        ]);

        window.dataLayer = [];
        writeText.mockRejectedValueOnce(new Error('Clipboard denied'));
        await user.click(screen.getByRole('button', { name: 'Copy link' }));
        expect(window.dataLayer).not.toContainEqual([
            'event',
            'share',
            expect.anything(),
        ]);
    });
});
