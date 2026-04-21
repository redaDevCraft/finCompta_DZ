import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import Modal from '@/Components/Modal';
import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

const NotificationContext = createContext(null);

const tone = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    error: 'border-rose-200 bg-rose-50 text-rose-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    info: 'border-slate-200 bg-slate-50 text-slate-800',
};

const IconByType = {
    success: CircleCheck,
    error: CircleAlert,
    warning: TriangleAlert,
    info: Info,
};

export function NotificationProvider({ children, initialFlash = null }) {
    const [items, setItems] = useState([]);
    const [confirmState, setConfirmState] = useState(null);
    const [promptState, setPromptState] = useState(null);
    const lastFlashRef = useRef('');

    const notify = useCallback((type, message, timeoutMs = 4000) => {
        const id = `${Date.now()}-${Math.random()}`;
        setItems((prev) => [...prev, { id, type, message }]);

        if (timeoutMs > 0) {
            window.setTimeout(() => {
                setItems((prev) => prev.filter((item) => item.id !== id));
            }, timeoutMs);
        }
    }, []);

    const api = useMemo(
        () => ({
            notify,
            success: (message, timeoutMs) => notify('success', message, timeoutMs),
            error: (message, timeoutMs) => notify('error', message, timeoutMs),
            warning: (message, timeoutMs) => notify('warning', message, timeoutMs),
            info: (message, timeoutMs) => notify('info', message, timeoutMs),
            confirm: ({ title = 'Confirmation', message, confirmLabel = 'Confirmer', cancelLabel = 'Annuler' }) =>
                new Promise((resolve) => {
                    setConfirmState({
                        title,
                        message,
                        confirmLabel,
                        cancelLabel,
                        resolve,
                    });
                }),
            prompt: ({
                title = 'Saisie requise',
                message,
                placeholder = '',
                defaultValue = '',
                confirmLabel = 'Valider',
                cancelLabel = 'Annuler',
            }) =>
                new Promise((resolve) => {
                    setPromptState({
                        title,
                        message,
                        placeholder,
                        value: defaultValue,
                        confirmLabel,
                        cancelLabel,
                        resolve,
                    });
                }),
        }),
        [notify]
    );

    const processFlash = useCallback(
        (flashPayload) => {
            const flash = flashPayload ?? {};
            const current = JSON.stringify({
                success: flash.success ?? null,
                warning: flash.warning ?? null,
                error: flash.error ?? null,
            });
            if (current === lastFlashRef.current) return;
            lastFlashRef.current = current;

            if (flash.success) notify('success', flash.success);
            if (flash.warning) notify('warning', flash.warning);
            if (flash.error) notify('error', flash.error);
        },
        [notify]
    );

    useEffect(() => {
        processFlash(initialFlash);
    }, [initialFlash, processFlash]);

    useEffect(() => {
        const removeListener = router.on('success', (event) => {
            processFlash(event?.detail?.page?.props?.flash);
        });

        return () => {
            removeListener?.();
        };
    }, [processFlash]);

    return (
        <NotificationContext.Provider value={api}>
            {children}
            <div className="pointer-events-none fixed right-4 top-4 z-[90] flex w-full max-w-sm flex-col gap-2">
                {items.map((item) => {
                    const Icon = IconByType[item.type] ?? Info;
                    return (
                        <div
                            key={item.id}
                            className={`pointer-events-auto flex items-start gap-2 rounded-lg border px-3 py-2 text-sm shadow ${tone[item.type] ?? tone.info}`}
                        >
                            <Icon className="mt-0.5 h-4 w-4 shrink-0" />
                            <p className="flex-1">{item.message}</p>
                            <button
                                type="button"
                                onClick={() => setItems((prev) => prev.filter((x) => x.id !== item.id))}
                                className="rounded p-0.5 hover:bg-black/5"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    );
                })}
            </div>
            <Modal
                show={!!confirmState}
                maxWidth="md"
                onClose={() => {
                    if (confirmState?.resolve) confirmState.resolve(false);
                    setConfirmState(null);
                }}
            >
                {confirmState && (
                    <div className="p-5">
                        <h3 className="text-base font-semibold text-slate-900">{confirmState.title}</h3>
                        <p className="mt-2 text-sm text-slate-600">{confirmState.message}</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    confirmState.resolve(false);
                                    setConfirmState(null);
                                }}
                                className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                {confirmState.cancelLabel}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    confirmState.resolve(true);
                                    setConfirmState(null);
                                }}
                                className="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                {confirmState.confirmLabel}
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
            <Modal
                show={!!promptState}
                maxWidth="md"
                onClose={() => {
                    if (promptState?.resolve) promptState.resolve(null);
                    setPromptState(null);
                }}
            >
                {promptState && (
                    <div className="p-5">
                        <h3 className="text-base font-semibold text-slate-900">{promptState.title}</h3>
                        <p className="mt-2 text-sm text-slate-600">{promptState.message}</p>
                        <input
                            type="text"
                            autoFocus
                            value={promptState.value}
                            placeholder={promptState.placeholder}
                            onChange={(e) => setPromptState((prev) => ({ ...prev, value: e.target.value }))}
                            className="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        />
                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    promptState.resolve(null);
                                    setPromptState(null);
                                }}
                                className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                {promptState.cancelLabel}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    promptState.resolve(promptState.value);
                                    setPromptState(null);
                                }}
                                className="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                {promptState.confirmLabel}
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </NotificationContext.Provider>
    );
}

export function useNotification() {
    const ctx = useContext(NotificationContext);
    if (!ctx) {
        throw new Error('useNotification must be used within NotificationProvider');
    }
    return ctx;
}

