// Currency symbol mapping
const CURRENCY_SYMBOLS = {
    'IDR': 'Rp',
    'Rp': 'Rp',
    'SGD': 'S$',
    'USD': '$',
    'EUR': '€',
    'GBP': '£',
    'JPY': '¥',
    'AUD': 'A$',
    'CAD': 'C$',
    'CHF': 'CHF',
    'CNY': '¥',
    'HKD': 'HK$',
    'KRW': '₩',
    'THB': '฿',
    'MYR': 'RM',
    'PHP': '₱',
    'VND': '₫'
};

const getCurrencySymbol = (currency) => {
    return CURRENCY_SYMBOLS[currency?.toUpperCase()] || currency || 'Rp';
};

export const formatCurrency = (amount, currency = 'IDR', rate = null, showConversion = true) => {
    const absAmount = Math.abs(amount);
    const isNegative = amount < 0;
    const symbol = getCurrencySymbol(currency);
    
    // If currency is not IDR/Rp and we have a rate, show the IDR equivalent
    if (currency !== 'IDR' && currency !== 'Rp' && rate && showConversion) {
        const idrValue = absAmount * rate;
        const originalFormatted = formatCompactNumber(absAmount, symbol);
        const idrFormatted = formatCompactNumber(idrValue, 'Rp');
        return `${isNegative ? '-' : ''}${originalFormatted} (≈ ${isNegative ? '-' : ''}${idrFormatted})`;
    }
    
    // Standard formatting using the compact formatter
    return formatCompactNumber(amount, symbol);
};

export const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

export const formatPercentage = (value) => {
    return `${value.toFixed(1)}%`;
};

// Format date to "MMM D, YYYY"
export const formatDateToInput = (date) => {
    if (!date) {
        return '';
    }
    // Extract date part by splitting on space or 'T'
    let dateStr = date.split(/\s|T/)[0].trim();
    
    // Try YYYY-MM-DD or YYYY/MM/DD
    let parts = dateStr.match(/^(\d{4})[-/](\d{2})[-/](\d{2})$/);
    if (parts) {
        const [, year, month, day] = parts;
        if (parseInt(month) >= 1 && parseInt(month) <= 12 && parseInt(day) >= 1 && parseInt(day) <= 31) {
            return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
        }
        return '';
    }
    
    // Try DD/MM/YYYY or DD-MM-YYYY
    parts = dateStr.match(/^(\d{2})[-/](\d{2})[-/](\d{4})$/);
    if (parts) {
        const [, day, month, year] = parts;
        if (parseInt(month) >= 1 && parseInt(month) <= 12 && parseInt(day) >= 1 && parseInt(day) <= 31) {
            return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
        }
        return '';
    }
    
    return '';
};

export const formatDate = (date) => {
    if (!date) {
        return 'No date';
    }
    
    try {
        // Handle different date formats that might come from the backend
        let dateObj;
        
        // If it's already a valid date format, use it directly
        if (date.includes('T') || date.includes('-')) {
            dateObj = new Date(date);
        } else {
            // Try to parse other formats
            dateObj = new Date(date);
        }
        
        // Check if the date is valid
        if (isNaN(dateObj.getTime())) {
            return 'Invalid date';
        }
        
        return dateObj.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    } catch {
        return 'Invalid date';
    }
};

// Helper function to format relative time
export const formatRelativeTime = (date) => {
    if (!date) {
        return 'No date';
    }
    
    try {
        const now = new Date();
        const inputDate = new Date(date);
        
        // Check if the date is valid
        if (isNaN(inputDate.getTime())) {
            return 'Invalid date';
        }

        const diffInMs = now.getTime() - inputDate.getTime();
        const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
        
        if (diffInDays === 0) {
            return 'Hari ini';
        } else if (diffInDays === 1) {
            return 'Kemarin';
        } else if (diffInDays <= 7) {
            return `${diffInDays} hari lalu`;
        } else if (diffInDays <= 30) {
            const weeks = Math.floor(diffInDays / 7);
            return weeks === 1 ? '1 minggu lalu' : `${weeks} minggu lalu`;
        } else {
            return formatDate(date);
        }
    } catch {
        return 'Invalid date';
    }
};

// Simple currency formatting for basic use cases
export const formatSimpleCurrency = (amount, currency = 'IDR') => {
    const value = Math.abs(amount);
    const isNegative = amount < 0;
    const symbol = getCurrencySymbol(currency);
    const formattedValue = value.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    const needsSpace = ['Rp', 'CHF', 'RM'].includes(symbol);
    const space = needsSpace ? ' ' : '';
    return `${isNegative ? '-' : ''}${symbol}${space}${formattedValue}`;
};

// Format amount as currency for input fields
export const formatCurrencyInput = (value) => {
    // Remove all non-digit characters except decimal point
    const numericValue = value.replace(/[^\d.]/g, '');
    
    // Ensure only one decimal point
    const parts = numericValue.split('.');
    
    // Limit to 13 digits
    const limitedValue = parts[0].slice(0, 13);
    
    // Format with commas for thousands
    if (limitedValue) {
        const num = parseFloat(limitedValue);
        if (!isNaN(num)) {
            return num.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }
    }
    return limitedValue;
};

// Dynamic compact number formatter for any currency
export const formatCompactNumber = (amount, currencySymbol = 'Rp') => {
    const absAmount = Math.abs(amount);
    const isNegative = amount < 0;
    const prefix = isNegative ? '-' : '';
    
    // Determine if we should use space after symbol (for Rp and some others)
    const needsSpace = ['Rp', 'CHF', 'RM'].includes(currencySymbol);
    const space = needsSpace ? ' ' : '';
    
    // Format based on amount size with Indonesian abbreviations
    if (absAmount >= 1000000000000) {
        return `${prefix}${currencySymbol}${space}${(absAmount / 1000000000000).toFixed(1)}T`;
    } else if (absAmount >= 1000000000) {
        return `${prefix}${currencySymbol}${space}${(absAmount / 1000000000).toFixed(1)}M`;
    } else if (absAmount >= 1000000) {
        return `${prefix}${currencySymbol}${space}${(absAmount / 1000000).toFixed(1)}Jt`;
    } else if (absAmount >= 100000) {
        // For 100k-999k, show with 1 decimal place to avoid rounding
        return `${prefix}${currencySymbol}${space}${(absAmount / 1000).toFixed(1)}Rb`;
    } else if (absAmount >= 10000) {
        // For 10k-99k, show with 1 decimal place to be more precise
        return `${prefix}${currencySymbol}${space}${(absAmount / 1000).toFixed(1)}Rb`;
    } else if (absAmount >= 1000) {
        // For 1k-9.9k, show with 1 decimal place (e.g., 1750 = 1.8Rb)
        return `${prefix}${currencySymbol}${space}${(absAmount / 1000).toFixed(1)}Rb`;
    }
    return `${prefix}${currencySymbol}${space}${absAmount.toFixed(0)}`;
};

// Helper function to format currency with optional conversion to IDR
export const formatCurrencyWithConversion = (amount, currency, exchangeRates = {}) => {
    const rate = exchangeRates[currency];
    return formatCurrency(amount, currency, rate, true);
};

// Helper function to get all supported currencies
export const getSupportedCurrencies = () => {
    return Object.keys(CURRENCY_SYMBOLS);
};
