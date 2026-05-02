import React from 'react';

const SUGGESTIONS = [
    'Quel est mon CA ce mois-ci ?',
    'Combien de factures impayées ?',
    'Quelle est ma trésorerie actuelle ?',
    'Résume mes dépenses du mois',
];

export default function ChatSuggestions({ onSelect }) {
    return (
        <div className="flex flex-wrap gap-2 justify-center">
            {SUGGESTIONS.map((s) => (
                <button
                    key={s}
                    type="button"
                    onClick={() => onSelect(s)}
                    className="text-xs bg-surface-offset px-3 py-1.5 rounded-full hover:bg-primary hover:text-white transition-colors border border-border"
                >
                    {s}
                </button>
            ))}
        </div>
    );
}