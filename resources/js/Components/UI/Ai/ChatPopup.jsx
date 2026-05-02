import React, { useEffect, useRef, useState, useMemo } from 'react';
import ChatMessage from './ChatMessage';
import ChatSuggestions from './ChatSuggestions';

export default function ChatPopup() {
    const [open, setOpen]                 = useState(false);
    const [panelVisible, setPanelVisible] = useState(false);
    const [messages, setMessages]         = useState([]);
    const [input, setInput]               = useState('');
    const [loading, setLoading]           = useState(false);
    const [conversationId, setConvId]     = useState(null);
    const bottomRef                       = useRef(null);

    const csrf = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const hasMessages = messages.length > 0;

    const lastAssistantMessage = useMemo(
        () => [...messages].reverse().find((m) => m.role === 'assistant'),
        [messages]
    );

    useEffect(() => {
        if (open) {
            const id = setTimeout(() => setPanelVisible(true), 10);
            return () => clearTimeout(id);
        } else {
            setPanelVisible(false);
        }
    }, [open]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, loading]);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') {
                setOpen(false);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const handleToggle = () => {
        setOpen((o) => !o);
    };

    const handleBackdropClick = () => {
        if (!loading) {
            setOpen(false);
        }
    };

    const send = async (text) => {
        const trimmed = text.trim();
        if (!trimmed || loading) return;

        const userMsg = { role: 'user', content: trimmed, _ts: Date.now() };
        setMessages((prev) => [...prev, userMsg]);
        setInput('');
        setLoading(true);

        try {
            const res = await fetch(route('ai.chat'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    message: trimmed,
                    conversation_id: conversationId,
                }),
            });

            if (res.status === 429) {
                setMessages((prev) => [
                    ...prev,
                    {
                        role: 'assistant',
                        content:
                            'Limite atteinte. Réessayez dans quelques minutes.',
                        error: true,
                        _ts: Date.now(),
                    },
                ]);
                return;
            }

            const data = await res.json();

            if (!res.ok) {
                setMessages((prev) => [
                    ...prev,
                    {
                        role: 'assistant',
                        content: data.reply || 'Erreur de traitement.',
                        error: true,
                        _ts: Date.now(),
                    },
                ]);
                return;
            }

            setConvId(data.conversation_id);

            setTimeout(() => {
                setMessages((prev) => [
                    ...prev,
                    {
                        role: 'assistant',
                        content: data.reply,
                        error: false,
                        _ts: Date.now(),
                    },
                ]);
            }, 130);
        } catch (e) {
            setMessages((prev) => [
                ...prev,
                {
                    role: 'assistant',
                    content: 'Erreur de connexion. Réessayez.',
                    error: true,
                    _ts: Date.now(),
                },
            ]);
        } finally {
            setLoading(false);
        }
    };

    const onSubmit = (e) => {
        e.preventDefault();
        send(input);
    };

    const handleSuggestion = (s) => {
        if (!open) setOpen(true);
        send(s);
    };

    const FollowUps = () => {
        if (!lastAssistantMessage || loading) return null;

        return (
            <div className="mt-2 flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() =>
                        send('Peux-tu résumer cette réponse en quelques points d’action ?')
                    }
                    className="text-[11px] bg-surface-offset px-2.5 py-1.5 rounded-full border border-border hover:bg-primary hover:text-white transition-colors"
                >
                    Résumer cette réponse
                </button>
                <button
                    type="button"
                    onClick={() =>
                        send('Propose-moi les étapes suivantes à partir de ce diagnostic.')
                    }
                    className="text-[11px] bg-surface-offset px-2.5 py-1.5 rounded-full border border-border hover:bg-primary hover:text-white transition-colors"
                >
                    Étapes suivantes
                </button>
                <button
                    type="button"
                    onClick={() =>
                        send('Y a-t-il des points de vigilance ou des risques à surveiller ?')
                    }
                    className="text-[11px] bg-surface-offset px-2.5 py-1.5 rounded-full border border-border hover:bg-primary hover:text-white transition-colors"
                >
                    Points de vigilance
                </button>
            </div>
        );
    };

    return (
        <>
            {/* Floating button */}
            <button
                type="button"
                onClick={handleToggle}
                className="fixed bottom-6 right-6 z-40 w-14 h-14 rounded-full bg-primary text-white shadow-[0_10px_25px_rgba(0,0,0,0.25)] flex items-center justify-center hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 transition-transform duration-150 ease-out hover:scale-105"
                aria-label="Assistant comptable"
            >
                {open ? '✕' : '💬'}
            </button>

            {/* Minimal overlay: transparent so app stays fully visible */}
            {open && (
                <div
                    className="fixed inset-0 z-30 flex justify-end items-end md:items-end pointer-events-none"
                    onClick={handleBackdropClick}
                >
                    {/* Card container */}
                    <div
                        onClick={(e) => e.stopPropagation()}
                        className={[
                            'pointer-events-auto',
                            'w-full max-w-md mx-4 mb-24 md:mb-6',
                            'bg-white rounded-2xl shadow-2xl border border-border',
                            'flex flex-col overflow-hidden',
                            'max-h-[80vh] md:max-h-[70vh]', // fixed height + internal scroll
                            'transform transition-all duration-200 ease-out',
                            panelVisible
                                ? 'opacity-100 translate-y-0 scale-100'
                                : 'opacity-0 translate-y-3 scale-95',
                        ].join(' ')}
                    >
                        {/* Header */}
                        <div className="px-4 py-3 bg-white border-b border-border flex items-center gap-2">
                            <div className="flex items-center gap-2">
                                <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary text-white text-sm shadow-sm">
                                    🤖
                                </span>
                                <div className="flex flex-col">
                                    <span className="text-sm font-semibold text-text">
                                        Assistant finCompta
                                    </span>
                                    <span className="text-[11px] text-text-muted">
                                        Conseiller comptable pour votre société
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="ml-auto text-text-muted hover:text-text flex items-center justify-center w-6 h-6 rounded-full hover:bg-surface-offset transition-colors"
                                aria-label="Fermer l'assistant"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Thin “thinking” bar */}
                        {loading && (
                            <div className="px-4 py-2 bg-surface-offset border-b border-border text-[11px] text-text-muted flex items-center gap-2">
                                <span className="w-2 h-2 rounded-full bg-primary animate-ping" />
                                <span>
                                    L’IA prépare une réponse adaptée à votre comptabilité…
                                </span>
                            </div>
                        )}

                        {/* Messages, scrollable */}
                        <div className="flex-1 overflow-y-auto p-4 space-y-3 bg-surface">
                            {!hasMessages && !loading && (
                                <>
                                    <div className="mb-4">
                                        <ChatMessage
                                            role="assistant"
                                            content={
                                                "Bonjour 👋\nJe peux vous aider à analyser votre chiffre d’affaires, vos factures impayées, vos dépenses ou votre trésorerie.\nPosez-moi une question pour commencer."
                                            }
                                            error={false}
                                        />
                                    </div>
                                    <div className="text-text-muted text-xs text-center mb-4">
                                        Exemples de questions
                                    </div>
                                    <ChatSuggestions onSelect={handleSuggestion} />
                                </>
                            )}

                            {messages.map((msg) => (
                                <div key={msg._ts} className="chat-message-animate">
                                    <ChatMessage
                                        role={msg.role}
                                        content={msg.content}
                                        error={msg.error}
                                    />
                                </div>
                            ))}

                            {lastAssistantMessage && (
                                <div className="flex justify-start">
                                    <div className="max-w-[80%]">
                                        <FollowUps />
                                    </div>
                                </div>
                            )}

                            {loading && (
                                <div className="flex justify-start">
                                    <div className="bg-surface-offset px-4 py-2 rounded-lg rounded-bl-none shadow-sm border border-border/60">
                                        <span className="inline-flex gap-1">
                                            <span className="w-2 h-2 bg-text-muted rounded-full animate-bounce [animation-delay:0ms]" />
                                            <span className="w-2 h-2 bg-text-muted rounded-full animate-bounce [animation-delay:150ms]" />
                                            <span className="w-2 h-2 bg-text-muted rounded-full animate-bounce [animation-delay:300ms]" />
                                        </span>
                                    </div>
                                </div>
                            )}

                            <div ref={bottomRef} />
                        </div>

                        {/* Input bar: always visible, card height fixed */}
                        <form
                            onSubmit={onSubmit}
                            className="p-3 border-t border-border bg-white flex gap-2"
                        >
                            <input
                                type="text"
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                placeholder="Votre question (ex: Résume ma trésorerie ce mois-ci)..."
                                maxLength={500}
                                disabled={loading}
                                className="flex-1 text-sm bg-surface-offset rounded-lg px-3 py-2 border border-border focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-50"
                            />
                            <button
                                type="submit"
                                disabled={loading || !input.trim()}
                                className="bg-primary text-white rounded-lg px-4 py-2 text-sm hover:bg-primary-hover disabled:opacity-40 transition-colors shadow-sm"
                            >
                                {loading ? '...' : 'Envoyer'}
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}