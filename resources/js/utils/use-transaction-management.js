import { formatCompactNumber } from '@/utils/formatters';
import { getMonthsArray, getYearsArray } from '@/utils/transaction-helpers';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export const useTransactionManagement = ({ initialTransactions, filters, transactionType }) => {
    // State management
    const [transactions, setTransactions] = useState(initialTransactions);
    const [currentPage, setCurrentPage] = useState(initialTransactions.current_page);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [searchQuery, setSearchQuery] = useState(filters?.search ?? '');
    const [currencyDrawerOpen, setCurrencyDrawerOpen] = useState(false);
    const searchTimeoutRef = useRef(null);

    // Constants
    const years = getYearsArray();
    const months = getMonthsArray();
    const baseUrl = transactionType === 'income' ? '/income' : '/outcome';

    // Format currency based on selected currency using compact format
    const formatCurrencyWithSymbol = (amount) => {
        return formatCompactNumber(amount, filters.currency === 'SGD' ? 'SGD' : 'IDR');
    };

    // Handle filter changes
    const handleCurrencyChange = (currency) => {
        setCurrencyDrawerOpen(false);
        router.get(`${baseUrl}?year=${filters.year}&month=${filters.month}&mode=${filters.mode}&currency=${currency}`);
    };

    const handleSearch = (e) => {
        const search = e.target.value;
        setSearchQuery(search);

        // Debounce search to avoid too many requests
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }
        searchTimeoutRef.current = setTimeout(() => {
            const params = new URLSearchParams();
            params.set('year', filters.year.toString());
            params.set('month', filters.month.toString());
            params.set('mode', filters.mode);
            params.set('currency', filters.currency);
            if (search) params.set('search', search);
            if (filters.category) params.set('category', filters.category);

            router.get(`${baseUrl}?${params.toString()}`);
        }, 500);
    };



    // Listen for transaction events to update local state
    useEffect(() => {
        const handleTransactionAdded = (event) => {
            if (event.detail.type === transactionType && event.detail.transaction) {
                setTransactions((prev) => ({
                    ...prev,
                    data: [event.detail.transaction, ...prev.data],
                }));
            }
        };

        const handleTransactionUpdated = (event) => {
            if (event.detail.type === transactionType && event.detail.transaction) {
                setTransactions((prev) => ({
                    ...prev,
                    data: prev.data.map((t) => (t.id === event.detail.transaction.id ? event.detail.transaction : t)),
                }));
            }
        };

        window.addEventListener('transactionAdded', handleTransactionAdded);
        window.addEventListener('transactionUpdated', handleTransactionUpdated);

        return () => {
            window.removeEventListener('transactionAdded', handleTransactionAdded);
            window.removeEventListener('transactionUpdated', handleTransactionUpdated);
        };
    }, [transactionType]);

    // Update search query when filters change
    useEffect(() => {
        setSearchQuery(filters?.search ?? '');
    }, [filters?.search]);

    // Update transactions when initialTransactions prop changes
    useEffect(() => {
        if (initialTransactions.current_page === 1) {
            setTransactions(initialTransactions);
            setIsLoadingMore(false); // Reset loading state on new data
        } else {
            setTransactions((prev) => {
                const existingIds = new Set(prev.data.map((t) => t.id));
                const newTransactions = initialTransactions.data.filter((t) => !existingIds.has(t.id));
                return {
                    ...initialTransactions,
                    data: [...prev.data, ...newTransactions],
                };
            });
        }
        setCurrentPage(initialTransactions.current_page);
    }, [initialTransactions]);

    return {
        // State
        transactions,
        currentPage,
        isLoadingMore,
        searchQuery,
        currencyDrawerOpen,
        setCurrencyDrawerOpen,

        // Constants
        years,
        months,

        // Functions
        formatCurrencyWithSymbol,
        handleCurrencyChange,
        handleSearch,

        // Computed
        filteredTransactions: transactions.data,
    };
};
