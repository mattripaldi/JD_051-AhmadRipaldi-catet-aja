import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export function CurrencySwitcher({ filters, onCurrencyChange, availableCurrencies = [] }) {
    // Generate currency options from availableCurrencies
    const currencyOptions = availableCurrencies.map(currency => ({
        value: currency,
        label: currency,
    }));
    return (
        <Select value={filters.currency || 'IDR'} onValueChange={onCurrencyChange}>
            <SelectTrigger className="h-auto rounded-xl px-3 py-2 text-white bg-white/20 hover:bg-white/30 border-0 focus:ring-0 focus:ring-offset-0">
                <SelectValue />
            </SelectTrigger>
            <SelectContent className="bg-white border-0 shadow-lg">
                {currencyOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value} className="focus:bg-blue-50">
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
