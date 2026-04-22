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

const titleByType = {
    success: 'Succes',
    error: 'Erreur',
    warning: 'Avertissement',
    info: 'Information',
};

export function NotificationProvider({ children, initialFlash = null, initialErrors = null }) {
    const [items, setItems] = useState([]);
    const [confirmState, setConfirmState] = useState(null);
    const [promptState, setPromptState] = useState(null);
    const lastFlashRef = useRef('');
    const lastErrorsRef = useRef('');

    const notify = useCallback((type, message, optionsOrTimeout = undefined) => {
        const id = `${Date.now()}-${Math.random()}`;
        const options = typeof optionsOrTimeout === 'object' && optionsOrTimeout !== null
            ? optionsOrTimeout
            : {};

        setItems((prev) => [
            ...prev,
            {
                id,
                type,
                title: options.title ?? titleByType[type] ?? 'Notification',
                message,
                actions: Array.isArray(options.actions) ? options.actions : null,
            },
        ]);
    }, []);

    const dismissNotification = useCallback((id) => {
        setItems((prev) => prev.filter((item) => item.id !== id));
    }, []);

    const activeNotification = items[0] ?? null;

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
                warnings: flash.warnings ?? null,
                error: flash.error ?? null,
            });
            if (current === lastFlashRef.current) return;
            lastFlashRef.current = current;

            if (flash.success) notify('success', flash.success);
            if (flash.warning) notify('warning', flash.warning);
            if (Array.isArray(flash.warnings)) {
                flash.warnings
                    .map((message) => String(message ?? '').trim())
                    .filter(Boolean)
                    .forEach((message) => notify('warning', message));
            }
            if (flash.error) notify('error', flash.error);
        },
        [notify]
    );

    const processErrors = useCallback(
        (errorsPayload) => {
            const errors = errorsPayload && typeof errorsPayload === 'object' ? errorsPayload : {};
            const messages = Object.values(errors)
                .flatMap((value) => {
                    if (Array.isArray(value)) return value;
                    return [value];
                })
                .map((value) => String(value ?? '').trim())
                .filter(Boolean);

            if (messages.length === 0) {
                lastErrorsRef.current = '';
                return;
            }

            const serialized = JSON.stringify(messages);
            if (serialized === lastErrorsRef.current) return;
            lastErrorsRef.current = serialized;

            messages.forEach((message) => notify('error', message));
        },
        [notify]
    );

    useEffect(() => {
        processFlash(initialFlash);
    }, [initialFlash, processFlash]);

    useEffect(() => {
        processErrors(initialErrors);
    }, [initialErrors, processErrors]);

    useEffect(() => {
        const removeListener = router.on('success', (event) => {
            processFlash(event?.detail?.page?.props?.flash);
            processErrors(event?.detail?.page?.props?.errors);
        });

        return () => {
            removeListener?.();
        };
    }, [processFlash, processErrors]);

    return (
        <NotificationContext.Provider value={api}>
            {children}
            <Modal
                show={!!activeNotification}
                maxWidth="md"
                onClose={() => {
                    if (activeNotification) dismissNotification(activeNotification.id);
                }}
            >
                {activeNotification && (
                    <div className="p-5">
                        <div className={`rounded-lg border p-4 ${tone[activeNotification.type] ?? tone.info}`}>
                            <div className="flex items-start gap-3">
                                {(() => {
                                    const Icon = IconByType[activeNotification.type] ?? Info;
                                    return <Icon className="mt-0.5 h-5 w-5 shrink-0" />;
                                })()}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <h3 className="text-base font-semibold text-slate-900">
                                            {activeNotification.title}
                                        </h3>
                                        <button
                                            type="button"
                                            onClick={() => dismissNotification(activeNotification.id)}
                                            className="rounded p-1 text-slate-500 hover:bg-black/5 hover:text-slate-700"
                                            aria-label="Fermer la notification"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                    <p className="mt-2 text-sm text-slate-700">{activeNotification.message}</p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-5 flex justify-end gap-2">
                            {(activeNotification.actions ?? []).map((action, index) => {
                                const variant = action?.variant ?? 'secondary';
                                const isPrimary = variant === 'primary';
                                return (
                                    <button
                                        key={`${activeNotification.id}-action-${index}`}
                                        type="button"
                                        onClick={() => {
                                            if (typeof action?.onClick === 'function') {
                                                action.onClick();
                                            }
                                            if (action?.href) {
                                                router.visit(action.href);
                                            }
                                            if (action?.dismiss !== false) {
                                                dismissNotification(activeNotification.id);
                                            }
                                        }}
                                        className={isPrimary
                                            ? 'rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700'
                                            : 'rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50'}
                                    >
                                        {action?.label ?? 'Action'}
                                    </button>
                                );
                            })}
                            <button
                                type="button"
                                onClick={() => dismissNotification(activeNotification.id)}
                                className="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                OK
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
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

