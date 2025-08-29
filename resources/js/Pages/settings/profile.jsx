import MobileLayout from "@/layouts/mobile-layout";
import React, { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    User,
    Mail,
    Shield,
    Trash2,
    Edit,
    Check,
    X,
    ChevronRight,
    LogOut
} from 'lucide-react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from "@/components/ui/drawer";
import InputError from '@/components/common/input-error';

const ProfileHeader = ({ auth }) => {
    return (
        <div className="bg-gradient-to-b from-green-500 to-green-600 px-4 pt-12 pb-6">
            <div className="text-center text-white">
                <div className="w-20 h-20 bg-white/20 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <User size={32} className="text-white" />
                </div>
                <h1 className="text-xl font-semibold">{auth.user.name}</h1>
                <p className="text-green-100 text-sm">{auth.user.email}</p>
            </div>
        </div>
    );
};

export default function NewProfile({ mustVerifyEmail, status }) {
    const { auth } = usePage().props;
    const [editingProfile, setEditingProfile] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const { data: passwordData, setData: setPasswordData, put: putPassword, errors: passwordErrors, processing: passwordProcessing, recentlySuccessful: passwordRecentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const { data: deleteData, setData: setDeleteData, delete: deleteUser, processing: deleteProcessing, errors: deleteErrors, reset: resetDelete, clearErrors: clearDeleteErrors } = useForm({
        password: '',
    });

    const handleProfileUpdate = (e) => {
        e.preventDefault();
        patch(route('profile.update'), {
            preserveScroll: true,
            onSuccess: () => setEditingProfile(false)
        });
    };

    const handlePasswordUpdate = (e) => {
        e.preventDefault();
        putPassword(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                setPasswordData({
                    current_password: '',
                    password: '',
                    password_confirmation: '',
                });
            }
        });
    };

    const handleDeleteAccount = (e) => {
        e.preventDefault();
        deleteUser(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDeleteDialog(false);
                resetDelete();
            },
            onError: () => {
                // Keep dialog open on error
            },
            onFinish: () => {
                // Don't reset here if there are errors
                if (!deleteErrors.password) {
                    resetDelete();
                }
            }
        });
    };

    const closeDeleteModal = () => {
        clearDeleteErrors();
        resetDelete();
        setShowDeleteDialog(false);
    };

    return (
        <MobileLayout header={<ProfileHeader auth={auth} />}>
            <Head title="Profil" />

            <div className="px-4 -mt-4 space-y-4">
                {/* Profile Information Card */}
                <Card className="p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-semibold text-gray-900">Informasi Profil</h2>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditingProfile(!editingProfile)}
                            className="text-green-600 hover:text-green-700"
                        >
                            {editingProfile ? <X size={16} /> : <Edit size={16} />}
                        </Button>
                    </div>

                    {editingProfile ? (
                        <form onSubmit={handleProfileUpdate} className="space-y-4">
                            <div>
                                <Label htmlFor="name" className="text-sm font-medium text-gray-700">Nama</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 h-11 border-gray-200 focus:border-green-500"
                                    placeholder="Nama lengkap"
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="email" className="text-sm font-medium text-gray-700">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="mt-1 h-11 border-gray-200 focus:border-green-500"
                                    placeholder="Alamat email"
                                />
                                <InputError message={errors.email} className="mt-1" />
                            </div>

                            {mustVerifyEmail && auth.user.email_verified_at === null && (
                                <div className="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p className="text-sm text-yellow-800">
                                        Email Anda belum diverifikasi.{' '}
                                        <Link
                                            href={route('verification.send')}
                                            method="post"
                                            as="button"
                                            className="text-yellow-900 underline hover:no-underline"
                                        >
                                            Klik di sini untuk mengirim ulang email verifikasi.
                                        </Link>
                                    </p>
                                    {status === 'verification-link-sent' && (
                                        <div className="mt-2 text-sm font-medium text-green-600">
                                            Link verifikasi baru telah dikirim ke email Anda.
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="flex gap-2">
                                <Button 
                                    type="submit" 
                                    disabled={processing}
                                    className="flex-1 bg-green-600 hover:bg-green-700 text-white"
                                >
                                    {processing ? (
                                        <>
                                            <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2" />
                                            Menyimpan...
                                        </>
                                    ) : (
                                        <>
                                            <Check size={16} className="mr-2" />
                                            Simpan
                                        </>
                                    )}
                                </Button>
                                <Button 
                                    type="button" 
                                    onClick={() => setEditingProfile(false)}
                                    className="px-4"
                                >
                                    <X size={16} />
                                </Button>
                            </div>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-green-600 flex items-center">
                                    <Check size={16} className="mr-1" />
                                    Berhasil disimpan
                                </p>
                            </Transition>
                        </form>
                    ) : (
                        <div className="space-y-3">
                            <div className="flex items-center">
                                <User size={16} className="text-gray-400 mr-3" />
                                <span className="text-gray-900">{auth.user.name}</span>
                            </div>
                            <div className="flex items-center">
                                <Mail size={16} className="text-gray-400 mr-3" />
                                <span className="text-gray-900">{auth.user.email}</span>
                            </div>
                        </div>
                    )}
                </Card>

                {/* Security Settings */}
                <Card className="p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                    <h2 className="font-semibold text-gray-900 mb-4">Keamanan</h2>
                    
                    <Drawer>
                        <DrawerTrigger asChild>
                            <Button variant="ghost" className="w-full justify-between h-12 px-4 hover:bg-gray-50 transition-colors">
                                <div className="flex items-center">
                                    <Shield size={16} className="text-gray-400 mr-3" />
                                    <span className="text-gray-900">Ubah Password</span>
                                </div>
                                <ChevronRight size={16} className="text-gray-400" />
                            </Button>
                        </DrawerTrigger>
                        <DrawerContent className="!bg-white !text-gray-900 border-0 max-w-[480px] mx-auto rounded-t-2xl shadow-xl">
                            <div className="bg-gray-300 w-12 h-1 rounded-full mx-auto mt-3 mb-4"></div>
                            <DrawerHeader className="text-center border-b border-gray-100 pb-4">
                                <DrawerTitle className="text-lg font-semibold text-gray-900">Ubah Password</DrawerTitle>
                                <DrawerDescription className="sr-only">Form untuk mengubah password akun Anda</DrawerDescription>
                            </DrawerHeader>
                            <div className="px-4 py-6">
                                <form onSubmit={handlePasswordUpdate} className="space-y-4">
                                    <div>
                                        <Label htmlFor="current_password" className="text-sm font-medium text-gray-700">
                                            Password Saat Ini
                                        </Label>
                                        <Input
                                            id="current_password"
                                            type="password"
                                            value={passwordData.current_password}
                                            onChange={(e) => setPasswordData('current_password', e.target.value)}
                                            className="mt-1 h-11 border-gray-200 focus:border-green-500"
                                            placeholder="Masukkan password saat ini"
                                        />
                                        <InputError message={passwordErrors.current_password} className="mt-1" />
                                    </div>

                                    <div>
                                        <Label htmlFor="password" className="text-sm font-medium text-gray-700">
                                            Password Baru
                                        </Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={passwordData.password}
                                            onChange={(e) => setPasswordData('password', e.target.value)}
                                            className="mt-1 h-11 border-gray-200 focus:border-green-500"
                                            placeholder="Masukkan password baru"
                                        />
                                        <InputError message={passwordErrors.password} className="mt-1" />
                                    </div>

                                    <div>
                                        <Label htmlFor="password_confirmation" className="text-sm font-medium text-gray-700">
                                            Konfirmasi Password Baru
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={passwordData.password_confirmation}
                                            onChange={(e) => setPasswordData('password_confirmation', e.target.value)}
                                            className="mt-1 h-11 border-gray-200 focus:border-green-500"
                                            placeholder="Konfirmasi password baru"
                                        />
                                        <InputError message={passwordErrors.password_confirmation} className="mt-1" />
                                    </div>

                                    <div className="flex gap-2 pt-4">
                                        <Button 
                                            type="submit" 
                                            disabled={passwordProcessing}
                                            className="flex-1 bg-green-600 hover:bg-green-700 text-white"
                                        >
                                            {passwordProcessing ? (
                                                <>
                                                    <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2" />
                                                    Mengubah...
                                                </>
                                            ) : (
                                                'Ubah Password'
                                            )}
                                        </Button>
                                        <DrawerClose asChild>
                                            <Button className="flex-1">
                                                Batal
                                            </Button>
                                        </DrawerClose>
                                    </div>

                                    <Transition
                                        show={passwordRecentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-green-600 flex items-center">
                                            <Check size={16} className="mr-1" />
                                            Password berhasil diubah
                                        </p>
                                    </Transition>
                                </form>
                            </div>
                        </DrawerContent>
                    </Drawer>
                </Card>

                {/* Danger Zone */}
                <Card className="p-4 bg-white rounded-xl shadow-sm border border-red-200">
                    <h2 className="font-semibold text-red-600 mb-4">Zona Bahaya</h2>
                    
                    <div className="space-y-3">
                        <Drawer open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                            <DrawerTrigger asChild>
                                <Button variant="ghost" className="w-full justify-between h-12 px-4 hover:bg-red-50 text-red-600 transition-colors">
                                    <div className="flex items-center">
                                        <Trash2 size={16} className="text-red-500 mr-3" />
                                        <span>Hapus Akun</span>
                                    </div>
                                    <ChevronRight size={16} className="text-red-400" />
                                </Button>
                            </DrawerTrigger>
                            <DrawerContent className="!bg-white !text-gray-900 border-0 max-w-[480px] mx-auto rounded-t-2xl shadow-xl">
                                <div className="bg-gray-300 w-12 h-1 rounded-full mx-auto mt-3 mb-4"></div>
                                <DrawerHeader className="text-center border-b border-gray-100 pb-4">
                                    <DrawerTitle className="text-lg font-semibold text-red-600">Hapus Akun</DrawerTitle>
                                    <DrawerDescription className="sr-only">Konfirmasi penghapusan akun permanen</DrawerDescription>
                                </DrawerHeader>
                                <div className="px-4 py-6">
                                    <div className="text-center mb-6">
                                        <div className="w-16 h-16 bg-red-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                                            <Trash2 size={24} className="text-red-600" />
                                        </div>
                                        <p className="text-gray-900 font-medium mb-2">Apakah Anda yakin?</p>
                                        <p className="text-sm text-gray-600 mb-4">
                                            Tindakan ini tidak dapat dibatalkan. Semua data Anda akan dihapus secara permanen.
                                        </p>
                                        <p className="text-sm text-gray-600">
                                            Masukkan password Anda untuk mengkonfirmasi penghapusan akun.
                                        </p>
                                    </div>
                                    
                                    <form onSubmit={handleDeleteAccount} className="space-y-4">
                                        <div>
                                            <Label htmlFor="delete_password" className="text-sm font-medium text-gray-700">
                                                Password
                                            </Label>
                                            <Input
                                                id="delete_password"
                                                type="password"
                                                value={deleteData.password}
                                                onChange={(e) => setDeleteData('password', e.target.value)}
                                                className="mt-1 h-11 border-gray-200 focus:border-red-500"
                                                placeholder="Masukkan password Anda"
                                                autoComplete="current-password"
                                            />
                                            <InputError message={deleteErrors.password} className="mt-1" />
                                        </div>
                                        
                                        <div className="flex gap-2 pt-2">
                                            <Button 
                                                type="button"
                                                onClick={closeDeleteModal}
                                                className="flex-1"
                                            >
                                                Batal
                                            </Button>
                                            <Button 
                                                type="submit"
                                                disabled={deleteProcessing}
                                                className="flex-1 bg-red-600 hover:bg-red-700 text-white"
                                            >
                                                {deleteProcessing ? (
                                                    <>
                                                        <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2" />
                                                        Menghapus...
                                                    </>
                                                ) : (
                                                    'Ya, Hapus Akun'
                                                )}
                                            </Button>
                                        </div>
                                    </form>
                                </div>
                            </DrawerContent>
                        </Drawer>

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="w-full flex items-center justify-between h-12 px-4 hover:bg-gray-50 rounded-lg text-gray-600 transition-colors"
                        >
                            <div className="flex items-center">
                                <LogOut size={16} className="text-gray-400 mr-3" />
                                <span>Keluar</span>
                            </div>
                            <ChevronRight size={16} className="text-gray-400" />
                        </Link>
                    </div>
                </Card>
            </div>
        </MobileLayout>
    );
}
