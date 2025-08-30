import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Check } from 'lucide-react';

// Generate year and month options
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - i);
const yearOptions = years.map((year) => ({ value: year, label: String(year) }));

const months = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

export function PeriodSelector({ filters, onModeChange, onYearChange, onMonthChange }) {
    return (
        <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
                {/* Mode Toggle */}
                <div className="flex rounded-xl bg-white/20 p-0.5">
                    <button
                        onClick={() => onModeChange('month')}
                        className={`rounded-lg px-1.5 py-1 text-xs font-medium transition-all ${
                            filters.mode === 'month' ? 'bg-white text-green-600' : 'text-white hover:bg-white/20'
                        }`}
                    >
                        Bulan
                    </button>
                    <button
                        onClick={() => onModeChange('year')}
                        className={`rounded-lg px-1.5 py-1 text-xs font-medium transition-all ${
                            filters.mode === 'year' ? 'bg-white text-green-600' : 'text-white hover:bg-white/20'
                        }`}
                    >
                        Tahun
                    </button>
                    <button
                        onClick={() => onModeChange('all')}
                        className={`rounded-lg px-1 py-1 text-[10px] font-medium transition-all sm:text-xs ${
                            filters.mode === 'all' ? 'bg-white text-green-600' : 'text-white hover:bg-white/20'
                        }`}
                    >
                        All Time
                    </button>
                </div>
            </div>

            {filters.mode !== 'all' && (
                <div className="flex items-center space-x-2">
                    {/* Year Dropdown */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="px-3 py-2 bg-white/20 hover:bg-white/30 text-white rounded-xl transition-colors duration-300 text-sm font-medium border-0"
                            >
                                {filters.year} ▼
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-24">
                            {yearOptions.map((option) => {
                                const isSelected = option.value === filters.year;
                                return (
                                    <DropdownMenuItem
                                        key={option.value}
                                        className="cursor-pointer flex items-center justify-between"
                                        onClick={() => onYearChange(option.value)}
                                    >
                                        <span>{option.label}</span>
                                        {isSelected && <Check size={16} className="text-blue-600" />}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {/* Month Dropdown - only show in month mode */}
                    {filters.mode === 'month' && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="px-3 py-2 bg-white/20 hover:bg-white/30 text-white rounded-xl transition-colors duration-300 text-sm font-medium border-0"
                                >
                                    {months.find(m => m.value === filters.month)?.label || 'Januari'} ▼
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-32">
                                {months.map((month) => {
                                    const isSelected = month.value === filters.month;
                                    return (
                                        <DropdownMenuItem
                                            key={month.value}
                                            className="cursor-pointer flex items-center justify-between"
                                            onClick={() => onMonthChange(month.value)}
                                        >
                                            <span>{month.label}</span>
                                            {isSelected && <Check size={16} className="text-blue-600" />}
                                        </DropdownMenuItem>
                                    );
                                })}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            )}
        </div>
    );
}
