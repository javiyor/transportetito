<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    balance: Array,
    totales: Object,
});

const expanded = ref(new Set());

const toggle = (id) => {
    const s = new Set(expanded.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expanded.value = s;
};

const expandAll = () => {
    const all = new Set();
    const walk = (nodes) => { nodes.forEach(n => { if (n.children?.length) { all.add(n.id); walk(n.children); } }); };
    walk(props.balance);
    expanded.value = all;
};

const collapseAll = () => { expanded.value = new Set(); };

const bgForLevel = (nivel) => ({
    capitulo: 'bg-gray-100 font-bold',
    rubro: 'bg-gray-50 font-semibold',
    cuenta_madre: 'font-medium',
    cuenta: '',
    subcuenta: 'text-gray-600 text-sm',
}[nivel] || '');

const indent = (depth) => `${12 + depth * 20}px`;
</script>

<template>
    <AppLayout title="Balance">
        <Head title="Balance" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Balance de Sumas y Saldos</h2>
                <a :href="route('finanzas.balance.export')" class="text-sm text-indigo-600 hover:text-indigo-800">Exportar CSV</a>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Total Debe</div>
                    <div class="text-lg font-bold text-green-700">$ {{ totales.debe.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Total Haber</div>
                    <div class="text-lg font-bold text-red-700">$ {{ totales.haber.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Diferencia</div>
                    <div class="text-lg font-bold" :class="totales.diferencia === 0 ? 'text-green-700' : 'text-red-700'">
                        $ {{ Math.abs(totales.diferencia).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}
                        <span v-if="totales.diferencia !== 0" class="text-xs font-normal">(desc cuadrado)</span>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Cuentas</h3>
                    <div class="flex gap-2 text-sm">
                        <button type="button" class="text-indigo-600 hover:text-indigo-800" @click="expandAll">Expandir todo</button>
                        <span class="text-gray-300">|</span>
                        <button type="button" class="text-indigo-600 hover:text-indigo-800" @click="collapseAll">Colapsar todo</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuenta</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debe</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Haber</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Deudor</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Acreedor</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="node in balance" :key="node.id">
                                <TreeNode
                                    :node="node"
                                    :depth="0"
                                    :expanded="expanded"
                                    :bgFn="bgForLevel"
                                    :indentFn="indent"
                                    @toggle="toggle"
                                />
                            </template>
                            <tr v-if="!balance.length">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Sin datos de balance.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { h } from 'vue';

const TreeNode = {
    name: 'TreeNode',
    props: ['node', 'depth', 'expanded', 'bgFn', 'indentFn'],
    emits: ['toggle'],
    setup(props, { emit }) {
        const hasChildren = props.node.children?.length > 0;
        const isExpanded = props.expanded.has(props.node.id);

        return () => {
            const children = hasChildren && isExpanded ? props.node.children.map(child =>
                h(TreeNode, {
                    node: child,
                    depth: props.depth + 1,
                    expanded: props.expanded,
                    bgFn: props.bgFn,
                    indentFn: props.indentFn,
                    'onToggle': (id) => emit('toggle', id),
                })
            ) : [];

            const toggleIcon = hasChildren
                ? h('button', {
                    class: 'text-gray-400 hover:text-gray-700 focus:outline-none text-xs mr-1',
                    onClick: () => emit('toggle', props.node.id),
                    innerHTML: isExpanded ? '▼' : '▶',
                  })
                : h('span', { class: 'mr-3' });

            const row = h('tr', { class: [props.bgFn(props.node.nivel), 'hover:bg-indigo-50 transition'] }, [
                h('td', { class: 'px-4 py-2 text-sm', style: { paddingLeft: props.indentFn(props.depth) } }, [
                    toggleIcon,
                    h('span', `${props.node.codigo} - ${props.node.nombre}`),
                ]),
                h('td', { class: 'px-4 py-2 text-sm text-right font-mono text-green-700' },
                    props.node.debe > 0 ? `$ ${props.node.debe.toLocaleString('es-AR', { minimumFractionDigits: 2 })}` : ''
                ),
                h('td', { class: 'px-4 py-2 text-sm text-right font-mono text-red-700' },
                    props.node.haber > 0 ? `$ ${props.node.haber.toLocaleString('es-AR', { minimumFractionDigits: 2 })}` : ''
                ),
                h('td', { class: 'px-4 py-2 text-sm text-right font-mono' },
                    props.node.saldo_deudor > 0
                        ? h('span', { class: 'text-green-700' }, `$ ${props.node.saldo_deudor.toLocaleString('es-AR', { minimumFractionDigits: 2 })}`)
                        : ''
                ),
                h('td', { class: 'px-4 py-2 text-sm text-right font-mono' },
                    props.node.saldo_acreedor > 0
                        ? h('span', { class: 'text-red-700' }, `$ ${props.node.saldo_acreedor.toLocaleString('es-AR', { minimumFractionDigits: 2 })}`)
                        : ''
                ),
            ]);

            return [row, ...children];
        };
    }
};
</script>
