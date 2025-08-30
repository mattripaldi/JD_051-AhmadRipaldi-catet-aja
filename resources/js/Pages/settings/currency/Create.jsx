import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DrawerModal from '@/components/common/drawer-modal';
import { useToastContext } from '@/components/ui/toast-provider';
import { usePage } from '@inertiajs/react';

export default function CurrencyCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        symbol: '',
    });

    const { auth } = usePage().props;

    const { success } = useToastContext();

    const handleSubmit = (e, close) => {
        e.preventDefault();
        post(route('currency.store', { account: auth.user.current_account_id }), {
            onSuccess: () => {
                success('Mata uang berhasil dibuat!');
                close();
            },
            onError: () => {
                // Handle errors if needed
            }
        });
    };

    const renderContent = ({ close }) => (
        <form onSubmit={(e) => handleSubmit(e, close)} className="h-full flex flex-col">
            <div className="flex-1 space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="name" className="text-sm font-medium text-gray-700">
                        Kode Mata Uang
                    </Label>
                    <Input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value.toUpperCase())}
                        placeholder="contoh: USD"
                        className="border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                        autoFocus
                        required
                        maxLength="3"
                    />
                    {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="symbol" className="text-sm font-medium text-gray-700">
                        Simbol
                    </Label>
                    <Input
                        id="symbol"
                        type="text"
                        value={data.symbol}
                        onChange={(e) => setData('symbol', e.target.value)}
                        placeholder="contoh: $"
                        className="border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                        required
                        maxLength="10"
                    />
                    {errors.symbol && <p className="text-sm text-red-600 mt-1">{errors.symbol}</p>}
                </div>

                <div className="space-y-2">
                    <p className="text-xs text-gray-500">
                        Nilai tukar akan otomatis diambil dari API berdasarkan kode mata uang yang dimasukkan
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 pt-6 mt-6 border-t border-gray-100">
                <Button
                    type="submit"
                    disabled={processing || !data.name.trim() || !data.symbol.trim()}
                    className="bg-green-600 hover:bg-green-700 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {processing ? 'Menyimpan...' : 'Simpan'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={processing}
                    className="border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    onClick={() => close()}
                >
                    Batal
                </Button>
            </div>
        </form>
    );

    return (
        <DrawerModal
            title="Tambah Mata Uang"
            description="Tambahkan mata uang baru untuk transaksi Anda"
        >
            {renderContent}
        </DrawerModal>
    );
}
