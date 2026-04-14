export default function Spinner({ label = 'Chargement...' }) {
    return (
        <div className="flex flex-col items-center justify-center py-8">
            <div className="h-10 w-10 animate-spin rounded-full border-4 border-gray-200 border-t-indigo-600" />
            {label ? <p className="mt-3 text-sm text-gray-600">{label}</p> : null}
        </div>
    );
}