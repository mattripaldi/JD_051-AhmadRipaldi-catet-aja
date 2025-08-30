import { usePage, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DrawerModal from '@/components/common/drawer-modal';
import { useToastContext } from '@/components/ui/toast-provider';
import { formatDateToInput } from '@/utils/formatters';

export default function EditIncome({ transaction, filters, currencies }) {
    const { auth } = usePage().props;

    const { data, setData, put, processing, errors } = useForm({
        description: transaction.description,
        amount: transaction.amount.toString(),
        date: formatDateToInput(transaction.transaction_date || transaction.date),
        currency: transaction.currency || 'IDR',
    });

    const { success } = useToastContext();

    const handleSubmit = (e, close) => {
        e.preventDefault();

        const submitData = {
            ...data,
            year: filters?.year,
            month: filters?.month,
        };

        put(`/account/${auth.account.id}/income/${transaction.id}`, {
            data: submitData,
            onSuccess: () => {
                success('Pemasukan berhasil diperbarui!');
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
                    <Label htmlFor="description" className="text-sm font-medium text-gray-700">
                        Deskripsi *
                    </Label>
                    <Textarea
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        onKeyDown={handleTextareaKeyDown}
                        placeholder="Masukkan deskripsi pemasukan..."
                        rows={3}
                        className="border-gray-200 focus:border-gray-400 focus:ring-gray-400 resize-none"
                        autoFocus
                        required
                    />
                    {errors.description && <p className="text-sm text-red-600 mt-1">{errors.description}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="amount" className="text-sm font-medium text-gray-700">
                        Jumlah *
                    </Label>
                    <div className="flex">
                        <Select value={data.currency} onValueChange={(value) => setData('currency', value)}>
                            <SelectTrigger className="w-24 rounded-r-none border-gray-200 focus:border-gray-400 focus:ring-gray-400">
                                <SelectValue>
                                    {currencies?.find(c => c.name === data.currency)?.symbol || 'Rp'}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {currencies?.map((currency) => (
                                    <SelectItem key={currency.name} value={currency.name}>
                                        {currency.display}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            placeholder="0.00"
                            className="flex-1 rounded-l-none border-l-0 border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                            required
                        />
                    </div>
                    {errors.amount && <p className="text-sm text-red-600 mt-1">{errors.amount}</p>}
                    {errors.currency && <p className="text-sm text-red-600 mt-1">{errors.currency}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="date" className="text-sm font-medium text-gray-700">
                        Tanggal *
                    </Label>
                    <Input
                        id="date"
                        type="date"
                        value={data.date}
                        onChange={(e) => setData('date', e.target.value)}
                        className="border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                        required
                    />
                    {errors.date && <p className="text-sm text-red-600 mt-1">{errors.date}</p>}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 pt-6 mt-6 border-t border-gray-100">
                <Button
                    type="submit"
                    disabled={processing}
                    className="bg-blue-600 hover:bg-blue-700 text-white"
                >
                    {processing ? 'Memperbarui...' : 'Perbarui'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="border-gray-200 text-gray-700 hover:bg-gray-50"
                    onClick={() => close()}
                >
                    Batal
                </Button>
            </div>
        </form>
    );

    return (
        <DrawerModal
            title="Edit Pemasukan"
            description="Ubah detail pemasukan"
        >
            {renderContent}
        </DrawerModal>
    );
}
