import { LucideIcon } from 'lucide-react';

interface MetricCardProps {
  title: string;
  value: string | number;
  change?: string;
  icon: LucideIcon;
  trend?: 'up' | 'down';
}

export default function MetricCard({ title, value, change, icon: Icon, trend }: MetricCardProps) {
  return (
    <div className="bg-white rounded-xl border shadow-sm p-6 flex flex-col space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
        <Icon className="h-8 w-8 text-blue-500" />
      </div>
      <div className="text-3xl font-bold text-gray-900">{value?.toLocaleString()}</div>
      {change && (
        <p className={`text-sm ${trend === 'up' ? 'text-green-600' : 'text-red-600'}`}>
          {change} vs last period
        </p>
      )}
    </div>
  );
}

