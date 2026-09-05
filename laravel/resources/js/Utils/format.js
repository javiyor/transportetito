export function formatNum(n) {
    return (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function formatMoneda(moneda, n) {
    return `${moneda} ${formatNum(n)}`;
}

export function formatFecha(v) {
    if (!v) return '-';
    const s = String(v).slice(0, 10);
    const d = new Date(s + 'T12:00:00');
    if (isNaN(d.getTime())) return s.split('-').reverse().join('-');
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}-${mm}-${yyyy}`;
}
