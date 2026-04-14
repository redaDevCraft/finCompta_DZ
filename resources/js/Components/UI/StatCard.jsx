const colorMap = {
    teal: 'bg-teal-50 text-teal-700',
    amber: 'bg-amber-50 text-amber-700',
    blue: 'bg-blue-50 text-blue-700',
    gray: 'bg-gray-50 text-gray-700',
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('fr-DZ', {
        style: 'currency',
        currency: 'DZD',
    }).format(Number(value ?? 0));

export default function StatCard({ label, value, icon: Icon, color = 'gray' }) {
    const tone = colorMap[color] ?? colorMap.gray;

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-gray-500">{label}</p>
                    <p className="mt-2 text-2xl font-bold text-gray-900">{formatCurrency(value)}</p>
                </div>

                <div className={`rounded-xl p-3 ${tone}`}>
                    {Icon ? <Icon className="h-5 w-5" /> : null}
                </div>
            </div>
        </div>
    );
}