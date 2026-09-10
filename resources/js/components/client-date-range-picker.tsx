import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Range = { start: string; end: string };

const presets = [
    ['Today', 0],
    ['Last 7 days', 6],
    ['Last 30 days', 29],
    ['Last 3 months', 89],
    ['Year to date', 'ytd'],
    ['Last year', 364],
] as const;

function iso(date: Date): string {
    return date.toISOString().slice(0, 10);
}

export default function ClientDateRangePicker({ value, onApply }: { value: Range; onApply: (range: Range) => void }) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(value);
    const label = `${new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(`${value.start}T00:00:00`))} – ${new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(`${value.end}T00:00:00`))}`;

    function choosePreset(value: (typeof presets)[number]) {
        const today = new Date();
        const end = iso(today);
        if (value[1] === 'ytd') {
            setDraft({ start: `${today.getFullYear()}-01-01`, end });
            return;
        }
        setDraft({ start: iso(new Date(today.getTime() - Number(value[1]) * 86400000)), end });
    }

    return <>
        <Button type="button" variant="outline" className="font-normal" onClick={() => { setDraft(value); setOpen(true); }}>
            <CalendarDays /> {label}
        </Button>
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-w-2xl p-0">
                <DialogHeader className="bg-[#1e5608] px-6 py-5 text-white"><DialogTitle>Select range</DialogTitle></DialogHeader>
                <div className="grid gap-6 p-6 md:grid-cols-[1fr_190px]">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="space-y-2 text-sm font-medium">Start date<input type="date" value={draft.start} onChange={(event) => setDraft({ ...draft, start: event.target.value })} className="border-input bg-background mt-1 h-10 w-full rounded-md border px-3" /></label>
                        <label className="space-y-2 text-sm font-medium">End date<input type="date" value={draft.end} onChange={(event) => setDraft({ ...draft, end: event.target.value })} className="border-input bg-background mt-1 h-10 w-full rounded-md border px-3" /></label>
                    </div>
                    <div className="space-y-1 border-l pl-4">{presets.map((preset) => <button key={preset[0]} type="button" onClick={() => choosePreset(preset)} className="block w-full rounded px-3 py-2 text-left text-sm hover:bg-blue-50 hover:text-blue-700">{preset[0]}</button>)}</div>
                </div>
                <div className="flex justify-end gap-2 border-t p-4"><Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button><Button type="button" onClick={() => { onApply(draft); setOpen(false); }}>Apply</Button></div>
            </DialogContent>
        </Dialog>
    </>;
}
