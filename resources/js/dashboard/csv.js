/**
 * Excel writes semicolon-separated files under several locales, so the
 * delimiter is taken from whichever character dominates the header line.
 */
function detectDelimiter(text) {
    const header = text.split(/\r?\n/, 1)[0] ?? '';
    const outsideQuotes = header.split('"').filter((_, index) => index % 2 === 0).join('');

    const commas = (outsideQuotes.match(/,/g) ?? []).length;
    const semicolons = (outsideQuotes.match(/;/g) ?? []).length;

    return semicolons > commas ? ';' : ',';
}

/**
 * Minimal RFC 4180 parser: handles quoted fields, escaped quotes ("") and
 * CRLF/LF line endings. Returns a matrix of raw string cells.
 */
export function parseCsv(text) {
    const clean = text.replace(/^﻿/, '');
    const delimiter = detectDelimiter(clean);
    const rows = [];
    let row = [];
    let field = '';
    let quoted = false;

    for (let i = 0; i < clean.length; i++) {
        const char = clean[i];

        if (quoted) {
            if (char === '"') {
                if (clean[i + 1] === '"') {
                    field += '"';
                    i++;
                } else {
                    quoted = false;
                }
            } else {
                field += char;
            }

            continue;
        }

        if (char === '"') {
            quoted = true;
        } else if (char === delimiter) {
            row.push(field);
            field = '';
        } else if (char === '\n') {
            row.push(field);
            rows.push(row);
            row = [];
            field = '';
        } else if (char !== '\r') {
            field += char;
        }
    }

    if (field !== '' || row.length) {
        row.push(field);
        rows.push(row);
    }

    return rows.filter((cells) => cells.some((cell) => cell.trim() !== ''));
}

function escapeCell(value) {
    const text = value === null || value === undefined ? '' : String(value);

    return /[",;\n\r]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
}

/**
 * Serialize rows to CSV. The BOM keeps Arabic readable when Excel opens the file.
 */
export function toCsv(headers, rows) {
    const lines = [headers, ...rows].map((cells) => cells.map(escapeCell).join(','));

    return '﻿' + lines.join('\r\n') + '\r\n';
}

export function downloadCsv(filename, content) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}
