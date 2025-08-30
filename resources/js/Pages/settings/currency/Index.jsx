import MobileLayout from "@/layouts/mobile-layout";
import { Head, usePage, Link } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Plus, Trash2, MoreVertical, DollarSign, ArrowLeft } from 'lucide-react';
import { ModalLink } from '@inertiaui/modal-react';

export default function CurrencyIndex({ currencies }) {
    const { flash } = usePage().props;
    const { auth } = usePage().props;

    return (
        <MobileLayout>
            <Head title="Mata Uang" />

            {/* Header */}
            <div className="bg-gradient-to-b from-green-500 to-green-600 px-4 pt-12 pb-6">
                {/* Back Button */}
                <div className="absolute top-4 left-4 z-10">
                    <Link
                        href={route('profile.edit')}
                        className="flex items-center justify-center w-10 h-10 bg-white/20 rounded-full hover:bg-white/30 transition-colors"
                    >
                        <ArrowLeft size={20} className="text-white" />
                    </Link>
                </div>

                <div className="text-center text-white">
                    <div className="w-16 h-16 bg-white/20 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <DollarSign size={24} className="text-white" />
                    </div>
                    <h1 className="text-xl font-semibold">Mata Uang</h1>
                    <p className="text-green-100 text-sm">Kelola mata uang Anda</p>
                </div>
            </div>

            <div className="px-4 -mt-4 space-y-4">
                {/* Success Message */}
                {flash?.success && (
                    <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p className="text-green-800 text-sm">{flash.success}</p>
                    </div>
                )}

                {/* Error Message */}
                {flash?.error && (
                    <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p className="text-red-800 text-sm">{flash.error}</p>
                    </div>
                )}

                {/* Currencies List */}
                {currencies.length === 0 ? (
                    <Card className="p-6 bg-white rounded-xl shadow-sm border border-gray-100">
                        <div className="text-center py-8">
                            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <DollarSign className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Belum ada mata uang</h3>
                            <p className="text-gray-500 mb-6">Buat mata uang pertama Anda</p>
                            <ModalLink href={route('currency.create', auth.user.current_account_id)}>
                                <Button className="bg-green-600 hover:bg-green-700 text-white">
                                    <Plus size={16} className="mr-2" />
                                    Tambah Mata Uang
                                </Button>
                            </ModalLink>
                        </div>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {currencies.map((currency) => (
                            <Card key={currency.id} className="p-4 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                            <span className="text-green-600 font-semibold text-sm">
                                                {currency.symbol}
                                            </span>
                                        </div>
                                        <div>
                                            <h3 className="font-medium text-gray-900">{currency.name}</h3>
                                            <p className="text-sm text-gray-500">
                                                {currency.symbol} - Mata Uang {currency.name}
                                            </p>
                                        </div>
                                    </div>
                                    {currency.name !== 'IDR' && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-8 w-8 p-0 text-gray-400 hover:text-gray-600"
                                                >
                                                    <MoreVertical className="w-4 h-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-48">
                                                <ModalLink href={route('currency.confirm-delete', [auth.user.current_account_id, currency.id])}>
                                                    <DropdownMenuItem variant="destructive" className="cursor-pointer">
                                                        <Trash2 className="w-4 h-4 mr-2" />
                                                        Hapus
                                                    </DropdownMenuItem>
                                                </ModalLink>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Fixed Floating Action Button */}
            {currencies.length > 0 && (
                <div className="fixed bottom-36 right-1/2 transform translate-x-1/2 max-w-[480px] w-full">
                    <div className="absolute right-4">
                        <ModalLink href={route('currency.create', auth.user.current_account_id)}>
                            <button className="flex h-14 w-14 items-center justify-center rounded-full bg-green-600 text-white shadow-lg transition-all hover:bg-green-700 hover:shadow-xl active:scale-95">
                                <Plus className="h-6 w-6" />
                            </button>
                        </ModalLink>
                    </div>
                </div>
            )}
        </MobileLayout>
    );
}
