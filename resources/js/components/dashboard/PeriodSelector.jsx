import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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
                    <Select value={filters.year.toString()} onValueChange={(value) => onYearChange(parseInt(value))}>
                        <SelectTrigger className="h-auto rounded-xl px-3 py-2 text-white bg-white/20 hover:bg-white/30 border-0 focus:ring-0 focus:ring-offset-0">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="bg-white border-0 shadow-lg">
                            {yearOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value.toString()} className="focus:bg-blue-50">
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Month Dropdown - only show in month mode */}
                    {filters.mode === 'month' && (
                        <Select value={filters.month.toString()} onValueChange={(value) => onMonthChange(parseInt(value))}>
                            <SelectTrigger className="h-auto rounded-xl px-3 py-2 text-white bg-white/20 hover:bg-white/30 border-0 focus:ring-0 focus:ring-offset-0">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="bg-white border-0 shadow-lg">
                                {months.map((month) => (
                                    <SelectItem key={month.value} value={month.value.toString()} className="focus:bg-blue-50">
                                        {month.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>
            )}
        </div>
    );
}
