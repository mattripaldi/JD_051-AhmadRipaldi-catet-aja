import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import DrawerModal from '@/components/common/drawer-modal';
import { AlertTriangle, DollarSign } from 'lucide-react';
import { useToastContext } from '@/components/ui/toast-provider';

export default function CurrencyDelete({ currency }) {
    const { delete: destroy, processing } = useForm();
    const { success } = useToastContext();

    const handleDelete = (close) => {
        destroy(route('currency.destroy', [currency.account_id, currency.id]), {
            onSuccess: () => {
                success('Mata uang berhasil dihapus!');
                close();
            },
            onError: () => {
                // Handle errors if needed
            }
        });
    };

    const renderContent = ({ close }) => (
        <div className="h-full flex flex-col">
            <div className="flex-1 space-y-6">
                <div className="text-center space-y-4">
                    <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                        <AlertTriangle size={24} className="text-red-600" />
                    </div>
                    <div className="space-y-2">
                        <p className="text-gray-900 font-medium">
                            Hapus Mata Uang
                        </p>
                        <p className="text-gray-600">
                            Apakah Anda yakin ingin menghapus <strong>"{currency.name}" ({currency.symbol})</strong>?
                        </p>
                        <p className="text-sm text-gray-500">
                            Tindakan ini tidak dapat dibatalkan. Semua transaksi yang menggunakan mata uang ini mungkin akan terpengaruh.
                        </p>
                    </div>
                </div>
            </div>

            <div className="flex flex-col gap-3 pt-6 mt-6 border-t border-gray-100">
                <Button
                    onClick={() => handleDelete(close)}
                    disabled={processing}
                    className="w-full h-12 text-base font-medium bg-red-600 hover:bg-red-700 text-white"
                >
                    {processing ? 'Menghapus...' : 'Ya, Hapus Mata Uang'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="w-full h-12 text-base font-medium border-gray-200 text-gray-700 hover:bg-gray-50"
                    onClick={() => close()}
                >
                    Batal
                </Button>
            </div>
        </div>
    );

    return (
        <DrawerModal
            title="Konfirmasi Hapus"
        >
            {renderContent}
        </DrawerModal>
    );
}
