export function formatCurrency(value, compact = false, currency = 'DZD') {
    return new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency,
        notation: compact ? 'compact' : 'standard',
        maximumFractionDigits: compact ? 1 : 2,
    }).format(Number(value ?? 0));
}
