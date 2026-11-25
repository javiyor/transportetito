<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({ hoja: Object });

const formatFecha = (value) => {
    if (!value) return '-';
    const parts = String(value).slice(0, 10).split('-');
    return parts.length === 3 ? `${parts[2]}-${parts[1]}-${parts[0]}` : String(value);
};

const vehiculoLabel = () => {
    const vehiculo = props.hoja.vehiculo;
    if (!vehiculo) return '-';
    return `${vehiculo.patente}${vehiculo.marca ? ` · ${vehiculo.marca}` : ''}${vehiculo.modelo ? ` ${vehiculo.modelo}` : ''}`;
};

const numeroComprobante = (comprobante) => {
    if (!comprobante) return '-';
    if (comprobante.arca_punto_venta && comprobante.arca_numero) {
        return `${String(comprobante.arca_punto_venta).padStart(4, '0')}-${String(comprobante.arca_numero).padStart(8, '0')}`;
    }

    const numero = comprobante.numero_interno || comprobante.id;
    return comprobante.tipo === 'guia_envio' ? `Guía #${numero}` : `#${numero}`;
};

const remitos = (item) => (item.comprobante?.pedidos || [])
    .map((pedido) => String(pedido.remito_numero || '').trim())
    .filter(Boolean)
    .join(', ') || '-';

const formaPago = (item) => {
    const value = String(item.forma_pago || item.cobro_medio || '').toLowerCase();
    const labels = {
        cuenta_corriente: 'Cta. cte.',
        efectivo: 'Efectivo',
        cheque: 'Cheque',
        cheque_propio: 'Cheque',
        cheque_tercero: 'Cheque',
        transferencia: 'Transferencia',
        varias: 'Varias',
        varios: 'Varias',
    };

    return labels[value] || item.forma_pago || item.cobro_medio || '';
};
</script>

<template>
    <div class="driver-guide bg-white text-gray-900">
        <Head :title="`Guía del chofer #${hoja.id}`" />

        <main class="mx-auto max-w-4xl p-4">
            <header class="mb-3 border-b-2 border-gray-800 pb-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Guía del chofer</p>
                        <h1 class="text-xl font-bold">Hoja #{{ hoja.id }}</h1>
                    </div>
                    <div class="text-right text-xs">
                        <div><strong>Fecha:</strong> {{ formatFecha(hoja.fecha || hoja.created_at) }}</div>
                        <div><strong>Chofer:</strong> {{ hoja.chofer?.name || '-' }}</div>
                        <div><strong>Vehículo:</strong> {{ vehiculoLabel() }}</div>
                        <div v-if="hoja.zona"><strong>Zona:</strong> {{ hoja.zona.nombre }}</div>
                    </div>
                </div>
            </header>

            <table class="w-full table-fixed border-collapse text-[10px] leading-tight">
                <colgroup>
                    <col class="w-[5%]">
                    <col class="w-[43%]">
                    <col class="w-[18%]">
                    <col class="w-[16%]">
                    <col class="w-[18%]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-500 text-left text-[9px] uppercase tracking-wide">
                        <th class="px-1.5 py-1.5">Ord.</th>
                        <th class="px-1.5 py-1.5">Entrega / dirección / teléfono</th>
                        <th class="px-1.5 py-1.5">Factura / guía</th>
                        <th class="px-1.5 py-1.5">Remitos</th>
                        <th class="px-1.5 py-1.5">Forma de pago</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in hoja.items" :key="item.id" class="border-b border-gray-300 align-top">
                        <td class="px-1.5 py-2 text-center font-bold">{{ item.orden }}</td>
                        <td class="px-1.5 py-2">
                            <div class="font-semibold">{{ item.entrega_cuenta?.tercero?.razon_social || item.entrega_cuenta?.nombre_cuenta || '-' }}</div>
                            <div>{{ item.entrega_cuenta?.direccion_entrega || item.direccion || item.entrega_cuenta?.direccion || '' }}</div>
                            <div v-if="!item.entrega_cuenta?.direccion_entrega" class="text-gray-600">
                                {{ item.localidad || item.entrega_cuenta?.localidad || '' }}
                                <template v-if="item.cp || item.entrega_cuenta?.cp"> · CP {{ item.cp || item.entrega_cuenta?.cp }}</template>
                            </div>
                            <div v-if="item.telefono || item.entrega_cuenta?.telefono" class="text-gray-600">Tel. {{ item.telefono || item.entrega_cuenta?.telefono }}</div>
                        </td>
                        <td class="px-1.5 py-2 font-mono">{{ numeroComprobante(item.comprobante) }}</td>
                        <td class="px-1.5 py-2">{{ remitos(item) }}</td>
                        <td class="px-1.5 py-2">
                            <template v-if="formaPago(item)">{{ formaPago(item) }}</template>
                            <template v-else>
                                <div class="payment-write-line"></div>
                                <div class="mt-1 text-[8px] text-gray-500">Cta. cte. · Efe. · Cheque · Varias</div>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>

            <footer class="mt-2 text-right text-[8px] text-gray-400">Transporte Tito · Hoja #{{ hoja.id }}</footer>
        </main>
    </div>
</template>

<style>
.payment-write-line {
    height: 15px;
    border-bottom: 1px solid #9ca3af;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    .driver-guide main {
        max-width: none;
        padding: 0;
    }
}
</style>
