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

                    <div>
                        <ModalLink href="/account/create">
                            <Button className="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg">
                                Tambah Akun
                            </Button>
                        </ModalLink>
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
                        <ModalLink href="/account/create">
                            <Button className="bg-green-600 hover:bg-green-700">
                                <Plus className="w-4 h-4 mr-2" />
                                Tambah Akun
                            </Button>
                        </ModalLink>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4">
                        {accounts.map((account) => (
                            <Card key={account.id} className="relative hover:shadow-md transition-shadow cursor-pointer" onClick={() => handleAccountSelect(account.id)}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">{account.name}</CardTitle>
                                            {account.description && (
                                                <p className="text-sm text-gray-600 mt-1">{account.description}</p>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-8 w-8 p-0 text-gray-600 hover:text-gray-700 hover:bg-gray-50 z-10 relative"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <MoreVertical className="w-4 h-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-10">
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
        </AccountLayout>
    );
}
