import React from 'react';

export default function ChatMessage({ role, content, error }) {
    const isUser = role === 'user';

    const baseClasses =
        'max-w-[80%] px-3 py-2 rounded-lg text-sm leading-relaxed whitespace-pre-wrap';

    let classes;
    if (isUser) {
        classes = `${baseClasses} bg-primary text-white rounded-br-none`;
    } else if (error) {
        classes = `${baseClasses} bg-error-highlight text-error rounded-bl-none`;
    } else {
        classes = `${baseClasses} bg-surface-offset text-text rounded-bl-none`;
    }

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div className={classes}>
                {content}
            </div>
        </div>
    );
}