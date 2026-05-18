const TZ = 'Europe/Istanbul';

export function fmtIstanbul(
    isoString: string | null | undefined,
    mode: 'datetime' | 'date' | 'time' = 'datetime',
): string | null {
    if (!isoString) {
return null;
}

    const d = new Date(isoString);
    const opts: Intl.DateTimeFormatOptions = { timeZone: TZ };

    if (mode === 'date') {
        return d.toLocaleDateString('en-GB', { ...opts, day: '2-digit', month: 'short' });
    }

    if (mode === 'time') {
        return d.toLocaleTimeString('en-GB', { ...opts, hour: '2-digit', minute: '2-digit', hour12: false });
    }

    const date = d.toLocaleDateString('en-GB', { ...opts, day: '2-digit', month: 'short' });
    const time = d.toLocaleTimeString('en-GB', { ...opts, hour: '2-digit', minute: '2-digit', hour12: false });

    return `${date}, ${time}`;
}

export function fmtDate(dateStr: string | null | undefined): string | null {
    if (!dateStr) {
return null;
}

    const [y, m, day] = dateStr.split('-').map(Number);

    return new Date(y, m - 1, day).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

export function truncate(str: string | null | undefined, n: number): string {
    if (!str) {
return '';
}

    return str.length > n ? str.slice(0, n) + '…' : str;
}
