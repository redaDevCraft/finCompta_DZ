import { useCallback } from 'react';
import { router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function StickyBackButton() {
    const handleGoBack = useCallback(() => {
        if (typeof window === 'undefined') return;

        // Use browser history when available, otherwise keep users inside the app.
        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        router.visit('/dashboard');
    }, []);

    return (
        <button
            type="button"
            onClick={handleGoBack}
            className="sticky-back-button"
            aria-label="Revenir en arrière"
            title="Revenir en arrière"
        >
            <ArrowLeft className="h-5 w-5" aria-hidden="true" />
        </button>
    );
}
