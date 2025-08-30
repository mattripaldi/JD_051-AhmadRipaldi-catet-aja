import MobileLayout from '@/layouts/mobile-layout';
import { formatRelativeTime, formatSimpleCurrency } from '@/utils/formatters';
import { ChatBubble } from '@/components/ai/ChatBubble';

import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Link } from '@inertiajs/react';
import { ModalLink } from '@inertiaui/modal-react';
import { Pagination, PaginationContent, PaginationEllipsis, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { IncomeHeader } from '@/components/income/IncomeHeader';
import {
    Check,
    Edit,
    MoreVertical,
    Search,
    Trash2,
    TrendingUp,
    X
} from 'lucide-react';
import React, { useRef, useState, useEffect } from 'react';

export default function IndexIncome({ transactions: initialTransactions, filters, stats, currencyBreakdown, currencies, availableCurrencies }) {
    const { auth } = usePage().props;

    // Server-side search state
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const inputRef = useRef(null);
    const searchTimeoutRef = useRef(null);

    // Local currency selection state for immediate UI updates
    const [selectedCurrencyId, setSelectedCurrencyId] = useState(filters.currency_id || null);

    // Use server-filtered transactions
    const filteredTransactions = initialTransactions.data || [];

    // Handle filter changes
    const handleYearChange = (year) => {
        router.get(window.location.pathname, {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            year: year,
            page: 1
        }, { 
            preserveState: true,
            replace: true 
        });
    };

    const handleMonthChange = (month) => {
        router.get(window.location.pathname, {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            month: month,
            page: 1
        }, { 
            preserveState: true,
            replace: true 
        });
    };

    const handleModeChange = (mode) => {
        router.get(window.location.pathname, {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            mode: mode,
            page: 1
        }, { 
            preserveState: true,
            replace: true 
        });
    };

    // Server-side search with debouncing
    const handleSearch = (e) => {
        const value = e.target.value;
        setSearchQuery(value);

        // Clear existing timeout
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        // Debounce search to avoid too many requests
        searchTimeoutRef.current = setTimeout(() => {
            const currentParams = new URLSearchParams(window.location.search);
            if (value.trim()) {
                currentParams.set('search', value.trim());
            } else {
                currentParams.delete('search');
            }
            currentParams.delete('page'); // Reset to first page on search

            router.get(window.location.pathname, Object.fromEntries(currentParams), {
                preserveState: true,
                replace: true,
            });
        }, 300);
    };

    const clearSearch = () => {
        setSearchQuery('');
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.delete('search');
        currentParams.delete('page');

        router.get(window.location.pathname, Object.fromEntries(currentParams), {
            preserveState: true,
            replace: true,
        });

        if (inputRef.current) {
            inputRef.current.focus();
        }
    };

    // Update local search state when filters change (e.g., from URL)
    useEffect(() => {
        setSearchQuery(filters.search || '');
    }, [filters.search]);

    // Update local currency state when filters change (e.g., from URL)
    useEffect(() => {
        setSelectedCurrencyId(filters.currency_id || null);
    }, [filters.currency_id]);

    const hasActiveSearch = searchQuery.length > 0;

    return (
        <MobileLayout>
            <Head title="Pemasukan" />

            <div>
                {/* Header */}
                <IncomeHeader
                    stats={stats}
                    filters={filters}
                    onModeChange={handleModeChange}
                    onYearChange={handleYearChange}
                    onMonthChange={handleMonthChange}
                    currencyBreakdown={currencyBreakdown}
                    availableCurrencies={availableCurrencies}
                    currencies={currencies}
                />

                {/* Search Section */}
                <div className="px-4 py-3">
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 -translate-y-1/2 transform text-gray-400" size={16} />
                        <Input
                            ref={inputRef}
                            type="text"
                            placeholder="Cari transaksi..."
                            value={searchQuery}
                            onChange={handleSearch}
                            className="h-9 rounded-lg border-gray-200 bg-white pr-9 pl-9 text-sm"
                        />
                        {hasActiveSearch && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={clearSearch}
                                className="absolute top-1/2 right-1 h-7 w-7 -translate-y-1/2 transform p-0 hover:bg-gray-100"
                            >
                                <X size={14} className="text-gray-400" />
                            </Button>
                        )}
                    </div>

                    {hasActiveSearch && (
                        <div className="mt-2 text-xs text-gray-500">
                            {initialTransactions.meta?.total === 0 ? 'Tidak ada hasil ditemukan' : `${initialTransactions.meta?.total} hasil`}
                        </div>
                    )}
                </div>

                {/* Transaction List */}
                <div className="relative pb-20">
                    {filteredTransactions.length > 0 ? (
                        <div className="overflow-hidden bg-white">
                            <div className="px-4 py-5 pb-4">
                                <div className="mb-1 flex items-center justify-between">
                                    <h3 className="text-lg font-bold text-gray-800">Riwayat Pemasukan</h3>
                                </div>
                                <p className="text-sm text-gray-500">Transaksi pemasukan Anda</p>
                            </div>

                            <div className="space-y-1">
                                {filteredTransactions.map((transaction) => {
                                    
                                    const selectedCurrency = currencies.find(c => c.id == selectedCurrencyId);
                                    const currencySymbol = selectedCurrency ? selectedCurrency.symbol : 'Rp';
                                    const currencyName = selectedCurrency ? selectedCurrency.name : 'IDR';

                                    return (
                                        <div key={transaction.id} className="px-4 py-4 transition-colors hover:bg-gray-50">
                                            <div className="flex items-center space-x-4">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 text-green-700">
                                                    <span className="text-sm font-semibold">
                                                        {currencyName}
                                                    </span>
                                                </div>

                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-gray-900">{transaction.description}</p>
                                                    <div className="mt-1 flex items-center space-x-2">
                                                        <span className={`rounded px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700`}>
                                                            {transaction.category ?? 'Pemasukan'}
                                                        </span>
                                                        <span className="text-xs text-gray-500">{formatRelativeTime(transaction.date)}</span>
                                                    </div>
                                                </div>

                                                <div className="flex items-center space-x-3 text-right">
                                                    <div>
                                                        <p className={`font-semibold text-green-600 text-sm`}>
                                                            {currencySymbol}{formatSimpleCurrency(transaction.amount)}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="!hover:text-gray-700 !hover:bg-gray-50 !focus:bg-gray-50 h-8 w-8 !bg-transparent !text-gray-500"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                >
                                                                    <MoreVertical size={16} />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end" className="w-48">
                                                                <ModalLink href={`/account/${auth.account.id}/income/${transaction.id}/edit`}>
                                                                    <DropdownMenuItem className="cursor-pointer">
                                                                        <Edit size={16} className="mr-2 text-blue-600" />
                                                                        Edit
                                                                    </DropdownMenuItem>
                                                                </ModalLink>
                                                                <ModalLink href={`/account/${auth.account.id}/income/${transaction.id}/delete`}>
                                                                    <DropdownMenuItem className="cursor-pointer text-red-600 hover:text-red-700 hover:bg-red-50">
                                                                        <Trash2 size={16} className="mr-2" />
                                                                        Hapus
                                                                    </DropdownMenuItem>
                                                                </ModalLink>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Pagination */}
                            {initialTransactions.last_page > 1 && (
                                <div className="px-4 py-6 border-t border-gray-100">
                                    <Pagination className="w-full">
                                        <PaginationContent className="flex items-center justify-center gap-2">
                                            <PaginationItem>
                                                <PaginationLink
                                                    href="#"
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        if (initialTransactions.current_page > 1) {
                                                            const currentParams = new URLSearchParams(window.location.search);
                                                            currentParams.set('page', initialTransactions.current_page - 1);
                                                            router.get(window.location.pathname, Object.fromEntries(currentParams), {
                                                                preserveState: true,
                                                                replace: true,
                                                            });
                                                        }
                                                    }}
                                                    className={`h-9 w-9 ${initialTransactions.current_page <= 1 ? 'pointer-events-none opacity-50' : 'cursor-pointer hover:bg-accent hover:text-accent-foreground'}`}
                                                    aria-label="Go to previous page"
                                                >
                                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                                    </svg>
                                                </PaginationLink>
                                            </PaginationItem>

                                            {/* Show first page if we're far from it */}
                                            {initialTransactions.current_page > 2 && (
                                                <>
                                                    <PaginationItem>
                                                        <PaginationLink
                                                            href="#"
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                const currentParams = new URLSearchParams(window.location.search);
                                                                currentParams.set('page', 1);
                                                                router.get(window.location.pathname, Object.fromEntries(currentParams), {
                                                                    preserveState: true,
                                                                    replace: true,
                                                                });
                                                            }}
                                                            className="cursor-pointer"
                                                        >
                                                            1
                                                        </PaginationLink>
                                                    </PaginationItem>
                                                    {initialTransactions.current_page > 3 && (
                                                        <PaginationItem>
                                                            <PaginationEllipsis />
                                                        </PaginationItem>
                                                    )}
                                                </>
                                            )}

                                            {/* Current page and surrounding pages */}
                                            {(() => {
                                                const current = initialTransactions.current_page;
                                                const total = initialTransactions.last_page;
                                                const pages = [];
                                                
                                                // Show previous page if exists
                                                if (current > 1) {
                                                    pages.push(current - 1);
                                                }
                                                
                                                // Show current page
                                                pages.push(current);
                                                
                                                // Show next page if exists
                                                if (current < total) {
                                                    pages.push(current + 1);
                                                }

                                                return pages.map((pageNum) => (
                                                    <PaginationItem key={pageNum}>
                                                        <PaginationLink
                                                            href="#"
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                const currentParams = new URLSearchParams(window.location.search);
                                                                currentParams.set('page', pageNum);
                                                                router.get(window.location.pathname, Object.fromEntries(currentParams), {
                                                                    preserveState: true,
                                                                    replace: true,
                                                                });
                                                            }}
                                                            isActive={pageNum === current}
                                                            className="cursor-pointer"
                                                        >
                                                            {pageNum}
                                                        </PaginationLink>
                                                    </PaginationItem>
                                                ));
                                            })()}

                                            {/* Show last page if we're far from it */}
                                            {initialTransactions.current_page < initialTransactions.last_page - 1 && (
                                                <>
                                                    {initialTransactions.current_page < initialTransactions.last_page - 2 && (
                                                        <PaginationItem>
                                                            <PaginationEllipsis />
                                                        </PaginationItem>
                                                    )}
                                                    <PaginationItem>
                                                        <PaginationLink
                                                            href="#"
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                const currentParams = new URLSearchParams(window.location.search);
                                                                currentParams.set('page', initialTransactions.last_page);
                                                                router.get(window.location.pathname, Object.fromEntries(currentParams), {
                                                                    preserveState: true,
                                                                    replace: true,
                                                                });
                                                            }}
                                                            className="cursor-pointer"
                                                        >
                                                            {initialTransactions.last_page}
                                                        </PaginationLink>
                                                    </PaginationItem>
                                                </>
                                            )}

                                            <PaginationItem>
                                                <PaginationLink
                                                    href="#"
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        if (initialTransactions.current_page < initialTransactions.last_page) {
                                                            const currentParams = new URLSearchParams(window.location.search);
                                                            currentParams.set('page', initialTransactions.current_page + 1);
                                                            router.get(window.location.pathname, Object.fromEntries(currentParams), {
                                                                preserveState: true,
                                                                replace: true,
                                                            });
                                                        }
                                                    }}
                                                    className={`h-9 w-9 ${initialTransactions.current_page >= initialTransactions.last_page ? 'pointer-events-none opacity-50' : 'cursor-pointer hover:bg-accent hover:text-accent-foreground'}`}
                                                    aria-label="Go to next page"
                                                >
                                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </PaginationLink>
                                            </PaginationItem>
                                        </PaginationContent>
                                    </Pagination>
                                </div>
                            )}


                        </div>
                    ) : (
                        <div className="relative bg-white p-8 text-center">
                            <div className="mb-4 text-gray-300">
                                <TrendingUp size={48} className="mx-auto" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold text-gray-900">Belum ada pemasukan</h3>
                            <p className="text-gray-500">Mulai catat pemasukan Anda untuk melacak keuangan dengan lebih baik</p>


                        </div>
                    )}
                </div>


            </div>
            
            <div className="fixed bottom-52 right-1/2 transform translate-x-1/2 max-w-[480px] w-full">
                <div className="absolute right-4">
                    <ChatBubble
                        context="income"
                        contextData={{
                            accountId: auth.account.id,
                            transactions: initialTransactions,
                            filters,
                            stats,
                            currencyBreakdown,
                            currencies,
                            availableCurrencies
                        }}
                    />
                </div>
            </div>
            
            <div className="fixed bottom-36 right-1/2 transform translate-x-1/2 max-w-[480px] w-full">
                <div className="absolute right-4">
                    <ModalLink
                        href={`/account/${auth.account.id}/income/create`}
                        className="flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg transition-all hover:bg-blue-700 hover:shadow-xl active:scale-95"
                    >
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                    </ModalLink>
                </div>
            </div>
        </MobileLayout>
    );
}
