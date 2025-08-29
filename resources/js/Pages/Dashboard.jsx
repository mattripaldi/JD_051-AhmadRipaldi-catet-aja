import MobileLayout from "@/layouts/mobile-layout";
import { Head } from '@inertiajs/react';
import { ModalLink } from '@inertiaui/modal-react';
import { Button } from '@/components/ui/button';

export default function Dashboard() {
    return (
        <MobileLayout>
            <Head title="Dashboard" />
            <div className="flex items-center justify-center min-h-screen">
                <div className="text-center space-y-6">
                    <div>
                        <h1 className="text-4xl font-bold text-gray-800 mb-4">Hello World</h1>
                        <p className="text-lg text-gray-600">Welcome to your dashboard!</p>
                    </div>

                    <div className="space-y-4">
                        <ModalLink href="/modal/sample">
                            <Button className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                                Open Sample Modal
                            </Button>
                        </ModalLink>
                    </div>
                </div>
            </div>
        </MobileLayout>
    );
}
