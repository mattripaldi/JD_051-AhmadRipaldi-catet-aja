import AccountLayout from "@/layouts/account-layout";
import { Head, usePage, Link, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Plus, Edit2, Trash2, MoreVertical } from 'lucide-react';
import { ModalLink } from '@inertiaui/modal-react';

export default function AccountIndex({ accounts }) {
    const { flash } = usePage().props;
    const { post } = useForm();

    const handleAccountSelect = (accountId) => {
        post(route('account.select', accountId));
    };

    // Array of elegant, muted gradient combinations
    const cardStyles = [
        'bg-gradient-to-br from-blue-400 via-blue-500 to-blue-600 hover:from-blue-500 hover:via-blue-600 hover:to-blue-700 text-white shadow-lg hover:shadow-xl',
        'bg-gradient-to-br from-emerald-400 via-emerald-500 to-emerald-600 hover:from-emerald-500 hover:via-emerald-600 hover:to-emerald-700 text-white shadow-lg hover:shadow-xl',
        'bg-gradient-to-br from-purple-400 via-purple-500 to-purple-600 hover:from-purple-500 hover:via-purple-600 hover:to-purple-700 text-white shadow-lg hover:shadow-xl',
        'bg-gradient-to-br from-rose-400 via-rose-500 to-rose-600 hover:from-rose-500 hover:via-rose-600 hover:to-rose-700 text-white shadow-lg hover:shadow-xl',
        'bg-gradient-to-br from-indigo-400 via-indigo-500 to-indigo-600 hover:from-indigo-500 hover:via-indigo-600 hover:to-indigo-700 text-white shadow-lg hover:shadow-xl',
        'bg-gradient-to-br from-teal-400 via-teal-500 to-teal-600 hover:from-teal-500 hover:via-teal-600 hover:to-teal-700 text-white shadow-lg hover:shadow-xl',
        'bg-gradient-to-br from-orange-400 via-orange-500 to-orange-600 hover:from-orange-500 hover:via-orange-600 hover:to-orange-700 text-white shadow-lg hover:shadow-xl',
        'bg-gradient-to-br from-slate-400 via-slate-500 to-slate-600 hover:from-slate-500 hover:via-slate-600 hover:to-slate-700 text-white shadow-lg hover:shadow-xl',
    ];

    const getCardStyle = (index) => {
        return cardStyles[index % cardStyles.length];
    };

    return (
        <AccountLayout>
            <Head title="Akun" />

            {/* Header */}
            <div className="p-6 pb-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Akun</h1>
                        <p className="text-gray-600 mt-1">Kelola akun keuangan Anda</p>
                    </div>




                </div>
            </div>

            {/* Success Message */}
            {flash?.success && (
                <div className="mx-6 mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p className="text-green-800 text-sm">{flash.success}</p>
                </div>
            )}

            {/* Error Message */}
            {flash?.error && (
                <div className="mx-6 mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p className="text-red-800 text-sm">{flash.error}</p>
                </div>
            )}

            {/* Accounts Grid */}
            <div className="px-6 pb-24">
                {accounts.length === 0 ? (
                    <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <Plus className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Belum ada akun</h3>
                        <p className="text-gray-500 mb-4">Buat akun pertama Anda untuk memulai</p>

                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6">
                        {accounts.map((account, index) => (
                            <Card key={account.id} className={`relative transition-all duration-300 cursor-pointer border-0 rounded-xl overflow-hidden hover:scale-[1.02] hover:-translate-y-1 ${getCardStyle(index)}`} onClick={() => handleAccountSelect(account.id)}>
                                <CardHeader className="pb-3 relative">
                                    <div className="absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -translate-y-6 translate-x-6"></div>
                                    <div className="absolute bottom-0 left-0 w-16 h-16 bg-white/10 rounded-full translate-y-4 -translate-x-4"></div>
                                    <div className="absolute inset-0 bg-gradient-to-br from-white/15 via-transparent to-white/10 pointer-events-none"></div>
                                    <div className="relative z-10 flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg font-semibold text-white drop-shadow-sm">{account.name}</CardTitle>
                                            {account.description && (
                                                <p className="text-sm text-white/90 mt-1 drop-shadow-sm">{account.description}</p>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-8 w-8 p-0 text-white/80 hover:text-white hover:bg-white/20 z-20 relative backdrop-blur-sm"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <MoreVertical className="w-4 h-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-48 bg-white/95 backdrop-blur-md border-white/20 shadow-xl">
                                                <ModalLink href={`/account/${account.id}/edit`}>
                                                    <DropdownMenuItem className="cursor-pointer">
                                                        <Edit2 className="w-4 h-4 mr-2 text-blue-600" />
                                                        Edit
                                                    </DropdownMenuItem>
                                                </ModalLink>
                                                <ModalLink href={`/account/${account.id}/delete`}>
                                                    <DropdownMenuItem variant="destructive" className="cursor-pointer">
                                                        <Trash2 className="w-4 h-4 mr-2" />
                                                        Hapus
                                                    </DropdownMenuItem>
                                                </ModalLink>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
            
            {/* Fixed Floating Action Button */}
            <div className="fixed bottom-36 right-1/2 transform translate-x-1/2 max-w-[480px] w-full">
                <div className="absolute right-4">
                    <ModalLink href="/account/create">
                        <button className="flex h-14 w-14 items-center justify-center rounded-full bg-green-600 text-white shadow-lg transition-all hover:bg-green-700 hover:shadow-xl active:scale-95">
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </ModalLink>
                </div>
            </div>
        </AccountLayout>
    );
}
