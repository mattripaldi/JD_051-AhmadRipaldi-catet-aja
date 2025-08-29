import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

import InputError from '@/components/common/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import HeadingSmall from '@/components/common/heading-small';

import { Drawer, DrawerClose, DrawerContent, DrawerDescription, DrawerFooter, DrawerHeader, DrawerTitle, DrawerTrigger } from '@/components/ui/drawer';
import { X } from 'lucide-react';

export default function DeleteUser() {
    const passwordInput = useRef(null);
    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        clearErrors();
        reset();
    };

    return (
        <div className="space-y-6">
            <HeadingSmall title="Delete account" description="Delete your account and all of its resources" />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4">
                <div className="relative space-y-0.5 text-red-600">
                    <p className="font-medium">Warning</p>
                    <p className="text-sm">Please proceed with caution, this cannot be undone.</p>
                </div>

                <Drawer direction="bottom">
                    <DrawerTrigger asChild>
                        <Button variant="destructive">Delete account</Button>
                    </DrawerTrigger>
                    <DrawerContent className="focus:outline-none bg-white mx-auto max-w-[480px] rounded-t-[20px] border-t border-gray-200 max-h-[50vh]">
                        <DrawerHeader className="relative bg-white">
                            <DrawerClose asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="absolute right-4 top-4 z-10 h-8 w-8 text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                                    onClick={closeModal}
                                >
                                    <X className="h-4 w-4" />
                                    <span className="sr-only">Close</span>
                                </Button>
                            </DrawerClose>
                            <DrawerTitle className="text-lg font-semibold text-gray-900 pr-12">
                                Are you sure you want to delete your account?
                            </DrawerTitle>
                            <DrawerDescription className="text-sm text-gray-600 mt-1">
                                Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password
                                to confirm you would like to permanently delete your account.
                            </DrawerDescription>
                        </DrawerHeader>
                        <div className="flex-1 overflow-y-auto bg-white px-4 pb-4">
                            <form className="space-y-6" onSubmit={deleteUser}>
                                <div className="grid gap-2">
                                    <Label htmlFor="password" className="sr-only">
                                        Password
                                    </Label>

                                    <Input
                                        id="password"
                                        type="password"
                                        name="password"
                                        ref={passwordInput}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Password"
                                        autoComplete="current-password"
                                    />

                                    <InputError message={errors.password} />
                                </div>

                                <DrawerFooter className="gap-2 px-0">
                                    <DrawerClose asChild>
                                        <Button variant="secondary" onClick={closeModal}>
                                            Cancel
                                        </Button>
                                    </DrawerClose>

                                    <Button variant="destructive" disabled={processing} asChild>
                                        <button type="submit">Delete account</button>
                                    </Button>
                                </DrawerFooter>
                            </form>
                        </div>
                    </DrawerContent>
                </Drawer>
            </div>
        </div>
    );
}
