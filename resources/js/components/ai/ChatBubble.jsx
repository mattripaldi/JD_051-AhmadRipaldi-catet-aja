import React, { useState, useRef, useEffect } from 'react';
import { MessageCircle, X, Send, Loader2, Bot, Sparkles } from 'lucide-react';
import { MarkdownRenderer } from './MarkdownRenderer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerTrigger } from '@/components/ui/drawer';
import { router } from '@inertiajs/react';

export function ChatBubble({ context, contextData = {} }) {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState([]);
    const [inputMessage, setInputMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [conversationStarters, setConversationStarters] = useState([]);
    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    useEffect(() => {
        if (isOpen) {
            fetchConversationStarters();
        }
    }, [isOpen, context]);

    useEffect(() => {
        if (isOpen) {
            // Focus on the input after a short delay to ensure the drawer is fully rendered
            const timer = setTimeout(() => {
                if (inputRef.current) {
                    inputRef.current.focus();
                }
            }, 300); // Slightly longer delay for drawer animation
            
            return () => clearTimeout(timer);
        }
    }, [isOpen]);

    // Auto-resize textarea based on content
    useEffect(() => {
        if (inputRef.current) {
            inputRef.current.style.height = 'auto';
            inputRef.current.style.height = `${Math.min(inputRef.current.scrollHeight, 128)}px`; // max-h-32 = 128px
        }
    }, [inputMessage]);

    const fetchConversationStarters = async () => {
        try {
            const response = await fetch(route('ai-chat.starters') + `?context=${context}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    setConversationStarters(data.starters);
                }
            }
        } catch (error) {
            console.error('Failed to fetch conversation starters:', error);
        }
    };

    const sendMessage = async (messageText) => {
        if (!messageText.trim() || isLoading) return;

        const userMessage = {
            id: Date.now().toString(),
            role: 'user',
            message: messageText.trim(),
            timestamp: new Date().toISOString()
        };

        setMessages(prev => [...prev, userMessage]);
        setInputMessage('');
        setIsLoading(true);

        // Reset textarea height after sending message
        if (inputRef.current) {
            inputRef.current.style.height = 'auto';
        }

        try {
            const response = await fetch(route('ai-chat.send'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    message: messageText.trim(),
                    context: context,
                    context_data: contextData,
                    conversation_history: messages.slice(-5).map(msg => ({
                        role: msg.role,
                        message: msg.message
                    }))
                })
            });

            if (response.ok) {
                const data = await response.json();
                
                const assistantMessage = {
                    id: (Date.now() + 1).toString(),
                    role: 'assistant',
                    message: data.message,
                    timestamp: data.timestamp || new Date().toISOString()
                };

                setMessages(prev => [...prev, assistantMessage]);
            } else {
                throw new Error('Failed to send message');
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            
            const errorMessage = {
                id: (Date.now() + 1).toString(),
                role: 'assistant',
                message: 'Maaf, terjadi kesalahan. Coba lagi ya!',
                timestamp: new Date().toISOString()
            };

            setMessages(prev => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        sendMessage(inputMessage);
    };

    const handleStarterClick = (starter) => {
        sendMessage(starter);
        // Refocus the input after sending the starter message
        setTimeout(() => {
            if (inputRef.current) {
                inputRef.current.focus();
                inputRef.current.style.height = 'auto'; // Reset height when focusing
            }
        }, 100);
    };

    const getContextLabel = () => {
        switch (context) {
            case 'dashboard': return 'Dashboard';
            case 'income': return 'Income';
            case 'outcome': return 'Outcome';
            case 'settings': return 'Settings';
            default: return 'Chat';
        }
    };

    return (
        <Drawer open={isOpen} onOpenChange={setIsOpen}>
            <DrawerTrigger asChild>
                <Button
                    size="lg"
                    className="h-14 w-14 rounded-full bg-purple-600 text-white shadow-lg transition-all hover:bg-purple-700 hover:shadow-xl active:scale-95"
                    title="Ask AI"
                >
                    <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </Button>
            </DrawerTrigger>
            <DrawerContent className="max-w-[480px] mx-auto h-[90vh] rounded-t-2xl">
                <DrawerHeader className="pb-3 px-4 pt-4">
                    <DrawerTitle className="flex items-center gap-2 text-lg font-semibold">
                        <MessageCircle className="h-5 w-5 text-blue-600" />
                        AI Assistant
                        <Badge variant="secondary" className="text-xs ml-auto">
                            {getContextLabel()}
                        </Badge>
                    </DrawerTitle>
                    <div className="w-12 h-1 bg-gray-300 rounded-full mx-auto mt-2"></div>
                </DrawerHeader>
                
                {/* Messages */}
                <ScrollArea className="flex-1 px-4">
                    <div className="space-y-4 pb-4">
                        {messages.length === 0 && conversationStarters.length === 0 && (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <div className="relative mb-4">
                                    <Bot className="h-16 w-16 text-gray-300 mx-auto" />
                                    <Sparkles className="h-6 w-6 text-blue-500 absolute -top-1 -right-1 animate-pulse" />
                                </div>
                                <h3 className="text-lg font-semibold text-gray-700 mb-2">
                                    AI Assistant Siap Membantu
                                </h3>
                                <p className="text-sm text-gray-500 max-w-xs leading-relaxed">
                                    Tanyakan apa saja tentang keuangan Anda. Saya akan memberikan analisis berdasarkan data transaksi Anda yang sebenarnya.
                                </p>
                            </div>
                        )}
                        
                        {messages.length === 0 && conversationStarters.length > 0 && (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground mb-4 text-center">
                                    Pilih pertanyaan atau ketik sendiri:
                                </p>
                                {conversationStarters.map((starter, index) => (
                                    <Button
                                        key={index}
                                        variant="outline"
                                        size="sm"
                                        className="w-full text-left h-auto p-4 justify-start whitespace-normal text-sm rounded-xl border-gray-200"
                                        onClick={() => handleStarterClick(starter)}
                                    >
                                        {starter}
                                    </Button>
                                ))}
                            </div>
                        )}

                        {messages.map((message) => (
                            <div
                                key={message.id}
                                className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[85%] p-3 rounded-2xl ${
                                        message.role === 'user'
                                            ? 'bg-blue-600 text-white rounded-br-md'
                                            : 'bg-gray-100 text-gray-900 rounded-bl-md'
                                    }`}
                                >
                                    {message.role === 'assistant' ? (
                                        <div className="text-sm leading-relaxed">
                                            <MarkdownRenderer content={message.message} />
                                        </div>
                                    ) : (
                                        <p className="text-sm whitespace-pre-wrap leading-relaxed">{message.message}</p>
                                    )}
                                </div>
                            </div>
                        ))}

                        {isLoading && (
                            <div className="flex justify-start">
                                <div className="bg-gray-100 p-3 rounded-2xl rounded-bl-md flex items-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                                    <span className="text-sm text-gray-600">Mengetik...</span>
                                </div>
                            </div>
                        )}
                    </div>
                    <div ref={messagesEndRef} />
                </ScrollArea>

                {/* Input */}
                <div className="p-4 border-t bg-white safe-area-pb">
                    <form onSubmit={handleSubmit} className="flex gap-3 items-end">
                        <textarea
                            ref={inputRef}
                            value={inputMessage}
                            onChange={(e) => setInputMessage(e.target.value)}
                            onKeyDown={(e) => {
                                // Submit form on Enter, but allow Shift+Enter for new line
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    handleSubmit(e);
                                }
                            }}
                            placeholder="Ketik pesan..."
                            disabled={isLoading}
                            className="flex-1 rounded-2xl border-gray-200 px-4 py-3 min-h-[44px] max-h-32 text-base resize-none overflow-y-auto leading-tight"
                            rows={1}
                        />
                        <Button
                            type="submit"
                            size="lg"
                            disabled={!inputMessage.trim() || isLoading}
                            className="rounded-full h-11 w-11 p-0 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300"
                        >
                            <Send className="h-5 w-5" />
                        </Button>
                    </form>
                </div>
            </DrawerContent>
        </Drawer>
    );
}
