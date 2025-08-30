import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import DrawerModal from '@/components/common/drawer-modal';
import { useToastContext } from '@/components/ui/toast-provider';

export default function IncomeDelete({ transaction }) {
    const { delete: destroy, processing } = useForm();
    const { success } = useToastContext();

    const handleDelete = (close) => {
        destroy(route('income.destroy', [transaction.account_id, transaction.id]), {
            onSuccess: () => {
                success('Transaksi pemasukan berhasil dihapus!');
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
                <div className="text-center space-y-2">
                    <p className="text-gray-600">
                        Apakah Anda yakin ingin menghapus transaksi <strong>"{transaction.description}"</strong>?
                    </p>
                    <p className="text-sm text-gray-500">
                        Tindakan ini tidak dapat dibatalkan. Data transaksi akan dihapus secara permanen.
                    </p>
                </div>
            </div>

            <div className="flex flex-col gap-3 pt-6 mt-6 border-t border-gray-100">
                <Button
                    onClick={() => handleDelete(close)}
                    disabled={processing}
                    className="w-full h-12 text-base font-medium bg-red-600 hover:bg-red-700 text-white"
                >
                    {processing ? 'Menghapus...' : 'Hapus Transaksi'}
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
            title="Hapus Transaksi Pemasukan"
        >
            {renderContent}
        </DrawerModal>
    );
}
