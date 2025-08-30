import React from 'react';

export function ScrollArea({ children, className = '', ...props }) {
    return (
        <div
            className={`overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 ${className}`}
            {...props}
        >
            {children}
        </div>
    );
}
