import { Download, Paperclip } from 'lucide-react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type TicketAttachment = {
    name: string;
    size: number;
    mime_type: string;
    url: string;
};

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function TicketAttachmentInput({
    id,
    files,
    onChange,
    error,
    progress,
}: {
    id: string;
    files: File[];
    onChange: (files: File[]) => void;
    error?: string;
    progress?: number;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>Attachments (optional)</Label>
            <Input
                id={id}
                type="file"
                multiple
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.log,.zip,.doc,.docx,.xls,.xlsx"
                onChange={(event) =>
                    onChange(Array.from(event.target.files ?? []))
                }
            />
            <p className="text-muted-foreground text-xs">
                Up to 5 files, 10 MB each. Images, PDF, text, ZIP, Word, and
                Excel files are supported.
            </p>
            {files.length > 0 && (
                <ul className="space-y-1 text-sm">
                    {files.map((file) => (
                        <li
                            key={`${file.name}-${file.lastModified}`}
                            className="flex items-center gap-2"
                        >
                            <Paperclip className="h-3.5 w-3.5" />
                            <span className="truncate">{file.name}</span>
                            <span className="text-muted-foreground text-xs">
                                ({formatFileSize(file.size)})
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {progress !== undefined && (
                <div className="space-y-1">
                    <progress
                        className="h-2 w-full"
                        value={progress}
                        max="100"
                    />
                    <p className="text-muted-foreground text-xs">
                        Uploading {progress}%
                    </p>
                </div>
            )}
            <InputError message={error} />
        </div>
    );
}

export function TicketAttachmentList({
    attachments,
}: {
    attachments: TicketAttachment[];
}) {
    if (attachments.length === 0) {
        return null;
    }

    return (
        <ul className="mt-4 flex flex-wrap gap-2 border-t pt-4">
            {attachments.map((attachment) => (
                <li key={attachment.url}>
                    <a
                        href={attachment.url}
                        className="bg-muted hover:bg-muted/70 inline-flex items-center gap-2 rounded-md px-3 py-2 text-xs font-medium"
                    >
                        <Download className="h-3.5 w-3.5" />
                        <span>{attachment.name}</span>
                        <span className="text-muted-foreground">
                            {formatFileSize(attachment.size)}
                        </span>
                    </a>
                </li>
            ))}
        </ul>
    );
}
