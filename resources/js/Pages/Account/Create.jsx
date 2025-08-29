import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import DrawerModal from '@/components/common/drawer-modal';
import { useToastContext } from '@/components/ui/toast-provider';

export default function AccountCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    const { success } = useToastContext();

    const handleSubmit = (e, close) => {
        e.preventDefault();
        post(route('account.store'), {
            onSuccess: () => {
                success('Akun berhasil dibuat!');
                close();
            },
            onError: () => {
                // Handle errors if needed
            }
        });
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const form = e.target.closest('form');
            form.requestSubmit();
        }
    };

    const handleTextareaKeyDown = (e) => {
        if (e.key === 'Enter' && e.shiftKey) {
            // Allow Shift+Enter for new line
            return;
        }
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const form = e.target.closest('form');
            form.requestSubmit();
        }
    };

    const renderContent = ({ close }) => (
        <form onSubmit={(e) => handleSubmit(e, close)} onKeyDown={handleKeyDown} className="h-full flex flex-col">
            <div className="flex-1 space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="name" className="text-sm font-medium text-gray-700">
                        Nama Akun
                    </Label>
                    <Input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="contoh: Tabungan Utama"
                        className="h-12 text-base border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                        autoFocus
                        required
                    />
                    {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="description" className="text-sm font-medium text-gray-700">
                        Deskripsi (Opsional)
                    </Label>
                    <Textarea
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        onKeyDown={handleTextareaKeyDown}
                        placeholder="Deskripsi akun..."
                        rows={3}
                        className="text-base border-gray-200 focus:border-gray-400 focus:ring-gray-400 resize-none"
                    />
                    {errors.description && <p className="text-sm text-red-600 mt-1">{errors.description}</p>}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 pt-6 mt-6 border-t border-gray-100">
                <Button
                    type="submit"
                    disabled={processing}
                    className="h-12 text-base font-medium bg-green-600 hover:bg-green-700 text-white"
                >
                    {processing ? 'Mengirim...' : 'Kirim'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="h-12 text-base font-medium border-gray-200 text-gray-700 hover:bg-gray-50"
                    onClick={() => close()}
                >
                    Batal
                </Button>
            </div>
        </form>
    );

    return (
        <DrawerModal
            title="Buat Akun Baru"
            description="Tambahkan akun keuangan baru untuk melacak uang Anda"
        >
            {renderContent}
        </DrawerModal>
    );
}