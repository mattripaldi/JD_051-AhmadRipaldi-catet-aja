import { HeadlessModal } from '@inertiaui/modal-react';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerHeader,
    DrawerTitle,
    DrawerDescription,
    DrawerFooter,
} from '@/components/ui/drawer';
import { X } from 'lucide-react';
import { useRef } from 'react';

export default function DrawerModal({
    title,
    description,
    children,
    footer,
    onClose,
    showCloseButton = true,
    className = '',
    maxWidth = 'max-w-[480px]',
    height = 'min-h-[60vh] max-h-[85vh]',
    direction = 'bottom',
}) {
    const modalRef = useRef(null);

    const handleClose = (close) => {
        if (onClose) {
            onClose(close);
        } else {
            close();
        }
    };

    return (
        <HeadlessModal ref={modalRef}>
            {({ isOpen, setOpen, close, config }) => (
                <Drawer 
                    open={isOpen} 
                    onOpenChange={(open) => {
                        if (!open) {
                            handleClose(close);
                        }
                    }}
                    direction={direction}
                >
                    <DrawerContent className={`focus:outline-none bg-white mx-auto ${maxWidth} rounded-t-[20px] border-t border-gray-200 ${height} shadow-2xl ${className}`}>
                        {(title || description || showCloseButton) && (
                            <DrawerHeader className="relative bg-white">
                                {showCloseButton && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="absolute right-4 top-4 z-10 h-8 w-8 text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                                        onClick={() => handleClose(close)}
                                    >
                                        <X className="h-4 w-4" />
                                        <span className="sr-only">Close</span>
                                    </Button>
                                )}
                                {title && (
                                    <DrawerTitle className="text-lg font-semibold text-gray-900 pr-12">
                                        {title}
                                    </DrawerTitle>
                                )}
                                {description && (
                                    <DrawerDescription className="text-sm text-gray-600 mt-1">
                                        {description}
                                    </DrawerDescription>
                                )}
                            </DrawerHeader>
                        )}

                        <div className="flex-1 overflow-y-auto bg-white px-4 pb-4">
                            {typeof children === 'function' ? children({ close }) : children}
                        </div>

                        {footer && (
                            <DrawerFooter className="gap-3 px-4 py-4 bg-white border-t border-gray-100">
                                {typeof footer === 'function' ? footer({ close }) : footer}
                            </DrawerFooter>
                        )}
                    </DrawerContent>
                </Drawer>
            )}
        </HeadlessModal>
    );
}
