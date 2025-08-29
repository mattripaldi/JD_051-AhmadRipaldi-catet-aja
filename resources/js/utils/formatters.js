export const formatCurrency = (amount, currency = 'Rp', rate) => {
    const absAmount = Math.abs(amount);
    const isNegative = amount < 0;
    
    // If currency is SGD and we have a rate, show the IDR equivalent
    if (currency === 'SGD' && rate) {
        const idrValue = absAmount * rate;
        
        if (absAmount >= 1000000000) {
            return `${isNegative ? '-' : ''}S$${(absAmount / 1000000000).toFixed(1)}M (≈ ${isNegative ? '-' : ''}Rp ${(idrValue / 1000000000).toFixed(1)}M)`;
        } else if (absAmount >= 1000000) {
            return `${isNegative ? '-' : ''}S$${(absAmount / 1000000).toFixed(1)}Jt (≈ ${isNegative ? '-' : ''}Rp ${(idrValue / 1000000).toFixed(1)}Jt)`;
        } else if (absAmount >= 1000) {
            return `${isNegative ? '-' : ''}S$${(absAmount / 1000).toFixed(0)}Rb (≈ ${isNegative ? '-' : ''}Rp ${(idrValue / 1000).toFixed(0)}Rb)`;
        }
        return `${isNegative ? '-' : ''}S$${absAmount.toFixed(0)} (≈ ${isNegative ? '-' : ''}Rp ${idrValue.toFixed(0)})`;
    }
    
    // Enhanced formatting for IDR with Indonesian abbreviations
    if (absAmount >= 1000000000000) {
        return `${isNegative ? '-' : ''}Rp ${(absAmount / 1000000000000).toFixed(1)}T`;
    } else if (absAmount >= 1000000000) {
        return `${isNegative ? '-' : ''}Rp ${(absAmount / 1000000000).toFixed(1)}M`;
    } else if (absAmount >= 1000000) {
        return `${isNegative ? '-' : ''}Rp ${(absAmount / 1000000).toFixed(1)}Jt`;
    } else if (absAmount >= 1000) {
        return `${isNegative ? '-' : ''}Rp ${(absAmount / 1000).toFixed(0)}Rb`;
    }
    return `${isNegative ? '-' : ''}Rp ${absAmount.toFixed(0)}`;
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
export const formatSimpleCurrency = (amount, currency = 'Rp') => {
    const value = Math.abs(amount);
    const formattedValue = value.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return `${currency} ${formattedValue}`;
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

// New compact number formatter for Indonesian abbreviations
export const formatCompactNumber = (amount, currency = 'Rp') => {
    const absAmount = Math.abs(amount);
    const isNegative = amount < 0;
    const prefix = isNegative ? '-' : '';
    
    if (currency === 'SGD') {
        if (absAmount >= 1000000000000) {
            return `${prefix}S$${(absAmount / 1000000000000).toFixed(1)}T`;
        } else if (absAmount >= 1000000000) {
            return `${prefix}S$${(absAmount / 1000000000).toFixed(1)}M`;
        } else if (absAmount >= 1000000) {
            return `${prefix}S$${(absAmount / 1000000).toFixed(1)}Jt`;
        } else if (absAmount >= 100000) {
            // For 100k-999k, show with 1 decimal place to avoid rounding
            return `${prefix}S$${(absAmount / 1000).toFixed(1)}Rb`;
        } else if (absAmount >= 10000) {
            // For 10k-99k, show with 1 decimal place to be more precise
            return `${prefix}S$${(absAmount / 1000).toFixed(1)}Rb`;
        } else if (absAmount >= 1000) {
            // For 1k-9.9k, show with 1 decimal place
            return `${prefix}S$${(absAmount / 1000).toFixed(1)}Rb`;
        }
        return `${prefix}S$${absAmount.toFixed(0)}`;
    }
    
    // IDR formatting
    if (absAmount >= 1000000000000) {
        return `${prefix}Rp ${(absAmount / 1000000000000).toFixed(1)}T`;
    } else if (absAmount >= 1000000000) {
        return `${prefix}Rp ${(absAmount / 1000000000).toFixed(1)}M`;
    } else if (absAmount >= 1000000) {
        return `${prefix}Rp ${(absAmount / 1000000).toFixed(1)}Jt`;
    } else if (absAmount >= 100000) {
        // For 100k-999k, show with 1 decimal place to avoid rounding
        return `${prefix}Rp ${(absAmount / 1000).toFixed(1)}Rb`;
    } else if (absAmount >= 10000) {
        // For 10k-99k, show with 1 decimal place to be more precise
        return `${prefix}Rp ${(absAmount / 1000).toFixed(1)}Rb`;
    } else if (absAmount >= 1000) {
        // For 1k-9.9k, show with 1 decimal place (e.g., 1750 = 1.8Rb)
        return `${prefix}Rp ${(absAmount / 1000).toFixed(1)}Rb`;
    }
    return `${prefix}Rp ${absAmount.toFixed(0)}`;
};
