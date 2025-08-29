import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DrawerModal from '@/components/common/drawer-modal';

export default function SampleModal({ message = "This is a sample modal!" }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        message: '',
    });

    const handleSubmit = (e, close) => {
        e.preventDefault();
        post('/modal/sample', {
            onSuccess: () => {
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
                        Name
                    </Label>
                    <Input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Enter your name"
                        className="h-12 text-base border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                    />
                    {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email" className="text-sm font-medium text-gray-700">
                        Email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="Enter your email"
                        className="h-12 text-base border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                        autoComplete="email"
                    />
                    {errors.email && <p className="text-sm text-red-600 mt-1">{errors.email}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="message" className="text-sm font-medium text-gray-700">
                        Message
                    </Label>
                    <Input
                        id="message"
                        type="text"
                        value={data.message}
                        onChange={(e) => setData('message', e.target.value)}
                        placeholder="Enter a message"
                        className="h-12 text-base border-gray-200 focus:border-gray-400 focus:ring-gray-400"
                    />
                    {errors.message && <p className="text-sm text-red-600 mt-1">{errors.message}</p>}
                </div>
            </div>

            <div className="flex flex-col gap-3 pt-6 mt-6 border-t border-gray-100">
                <Button
                    type="submit"
                    disabled={processing}
                    className="w-full h-12 text-base font-medium bg-gray-900 hover:bg-gray-800 text-white"
                >
                    {processing ? 'Submitting...' : 'Submit'}
                </Button>
                <Button 
                    type="button"
                    variant="outline" 
                    className="w-full h-12 text-base font-medium border-gray-200 text-gray-700 hover:bg-gray-50"
                    onClick={() => close()}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );

    return (
        <DrawerModal
            title="Sample Modal"
            description={message}
        >
            {renderContent}
        </DrawerModal>
    );
}