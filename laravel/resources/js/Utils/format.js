export function formatNum(n) {
    return (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function formatMoneda(moneda, n) {
    return `${moneda} ${formatNum(n)}`;
}

export function formatFecha(v) {
    if (!v) return '-';
    const d = new Date(String(v).slice(0, 10));
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}
