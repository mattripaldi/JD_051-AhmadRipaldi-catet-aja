import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import rehypeSanitize from 'rehype-sanitize';

export function MarkdownRenderer({ content, className = "" }) {
    return (
        <div className={`markdown-content ${className}`}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={[rehypeSanitize]}
                components={{
                    // Custom components for better styling
                    h1: ({ children }) => (
                        <h1 className="text-base font-bold mb-2 text-inherit">{children}</h1>
                    ),
                    h2: ({ children }) => (
                        <h2 className="text-sm font-semibold mb-2 text-inherit">{children}</h2>
                    ),
                    h3: ({ children }) => (
                        <h3 className="text-sm font-semibold mb-1 text-inherit">{children}</h3>
                    ),
                    p: ({ children }) => (
                        <p className="mb-2 leading-relaxed last:mb-0 text-inherit">{children}</p>
                    ),
                    strong: ({ children }) => (
                        <strong className="font-semibold text-inherit">{children}</strong>
                    ),
                    em: ({ children }) => (
                        <em className="italic text-inherit opacity-90">{children}</em>
                    ),
                    ul: ({ children }) => (
                        <ul className="list-disc pl-4 mb-2 space-y-1">{children}</ul>
                    ),
                    ol: ({ children }) => (
                        <ol className="list-decimal pl-4 mb-2 space-y-1">{children}</ol>
                    ),
                    li: ({ children }) => (
                        <li className="leading-relaxed text-inherit">{children}</li>
                    ),
                    blockquote: ({ children }) => (
                        <blockquote className="border-l-2 border-current border-opacity-30 pl-3 italic opacity-80 mb-2">
                            {children}
                        </blockquote>
                    ),
                    code: ({ children, className, ...props }) => {
                        const isInline = !className || !className.includes('language-');
                        if (isInline) {
                            return (
                                <code className="bg-black bg-opacity-10 px-1 py-0.5 rounded text-xs font-mono">
                                    {children}
                                </code>
                            );
                        }
                        return (
                            <pre className="bg-black bg-opacity-10 p-2 rounded text-xs font-mono overflow-x-auto mb-2">
                                <code className={className} {...props}>{children}</code>
                            </pre>
                        );
                    },
                    a: ({ href, children }) => (
                        <a 
                            href={href} 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:text-blue-800 underline font-medium"
                        >
                            {children}
                        </a>
                    ),
                    table: ({ children }) => (
                        <div className="overflow-x-auto mb-2">
                            <table className="min-w-full border border-gray-200 text-xs">
                                {children}
                            </table>
                        </div>
                    ),
                    thead: ({ children }) => (
                        <thead className="bg-gray-50">{children}</thead>
                    ),
                    tbody: ({ children }) => (
                        <tbody className="divide-y divide-gray-200">{children}</tbody>
                    ),
                    tr: ({ children }) => (
                        <tr>{children}</tr>
                    ),
                    th: ({ children }) => (
                        <th className="px-2 py-1 text-left font-semibold text-gray-900 border-b">
                            {children}
                        </th>
                    ),
                    td: ({ children }) => (
                        <td className="px-2 py-1 text-gray-700 border-b">
                            {children}
                        </td>
                    ),
                    hr: () => (
                        <hr className="border-current border-opacity-30 my-3" />
                    ),
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
