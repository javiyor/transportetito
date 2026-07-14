<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    arbol: Array,
    empresaId: Number,
    filtroTipo: String,
    totales: Object,
});

const expanded = ref(new Set());
const filterTipo = ref(props.filtroTipo);

const toggle = (id) => {
    const s = new Set(expanded.value);
    if (s.has(id)) { s.delete(id); } else { s.add(id); }
    expanded.value = s;
};

const expandAll = () => {
    const all = new Set();
    const collect = (nodes) => { nodes.forEach(n => { if (n.children?.length) { all.add(n.id); collect(n.children); } }); };
    collect(props.arbol);
    expanded.value = all;
};

const collapseAll = () => { expanded.value = new Set(); };

const changeFilter = () => {
    router.get(route('admin.plan-cuentas.index'), { tipo: filterTipo.value || null }, { preserveState: true, replace: true });
};

const nivelLabel = (n) => ({
    capitulo: 'Capítulo',
    rubro: 'Rubro',
    cuenta_madre: 'Cuenta Madre',
    cuenta: 'Cuenta',
    subcuenta: 'Subcuenta',
}[n] || n);

const tipoLabel = (t) => ({
    activo: 'Activo',
    pasivo: 'Pasivo',
    patrimonio_neto: 'Patrimonio Neto',
    ingreso: 'Ingreso',
    egreso: 'Egreso',
}[t] || t);

const nivelOptions = [
    { value: 'capitulo', label: 'Capítulo' },
    { value: 'rubro', label: 'Rubro' },
    { value: 'cuenta_madre', label: 'Cuenta Madre' },
    { value: 'cuenta', label: 'Cuenta' },
    { value: 'subcuenta', label: 'Subcuenta' },
];

const tipoOptions = [
    { value: 'activo', label: 'Activo' },
    { value: 'pasivo', label: 'Pasivo' },
    { value: 'patrimonio_neto', label: 'Patrimonio Neto' },
    { value: 'ingreso', label: 'Ingreso' },
    { value: 'egreso', label: 'Egreso' },
];

const naturalezaOptions = [
    { value: 'deudor', label: 'Deudor' },
    { value: 'acreedor', label: 'Acreedor' },
];

const defaultForm = () => ({
    parent_id: null,
    codigo: '',
    codigo_corto: '',
    nombre: '',
    tipo: '',
    naturaleza: '',
    nivel: 'cuenta',
    contabilizable: true,
    activo: true,
    orden: 0,
});

const createForm = useForm(defaultForm());
const createParentLabel = ref('');
const showCreateModal = ref(false);

const openCreate = (parent) => {
    createForm.reset();
    createForm.parent_id = parent?.id || null;
    createForm.tipo = parent?.tipo || '';
    createForm.activo = true;
    const parentNivel = parent?.nivel || null;
    const nivelOrder = { capitulo: 0, rubro: 1, cuenta_madre: 2, cuenta: 3, subcuenta: 4 };
    const nextNivel = parentNivel ? nivelOrder[parentNivel] + 1 : 0;
    const nivelKeys = ['capitulo', 'rubro', 'cuenta_madre', 'cuenta', 'subcuenta'];
    createForm.nivel = nivelKeys[nextNivel] || 'cuenta';
    if (parent?.naturaleza) createForm.naturaleza = parent.naturaleza;
    createParentLabel.value = parent ? `${parent.codigo_completo || parent.codigo} - ${parent.nombre}` : '(Raíz - Capítulo nuevo)';
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('admin.plan-cuentas.store'), { preserveScroll: true, onSuccess: () => { createForm.reset(); showCreateModal.value = false; } });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm(defaultForm());

const openEdit = (n) => {
    editId.value = n.id;
    editForm.codigo = n.codigo || '';
    editForm.codigo_corto = n.codigo_corto || '';
    editForm.nombre = n.nombre || '';
    editForm.tipo = n.tipo || '';
    editForm.naturaleza = n.naturaleza || '';
    editForm.nivel = n.nivel || 'cuenta';
    editForm.contabilizable = !!n.contabilizable;
    editForm.orden = n.orden || 0;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.plan-cuentas.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};

const confirmDelete = ref(null);
const submitDelete = () => {
    if (!confirmDelete.value) return;
    router.delete(route('admin.plan-cuentas.destroy', confirmDelete.value.id), { preserveScroll: true, onSuccess: () => (confirmDelete.value = null) });
};

const iconForLevel = (nivel) => ({
    capitulo: '📁',
    rubro: '📂',
    cuenta_madre: '📋',
    cuenta: '📄',
    subcuenta: '📝',
}[nivel] || '📄');

const rowBg = (nivel) => ({
    capitulo: 'bg-gray-100 font-bold',
    rubro: 'bg-gray-50 font-semibold',
    cuenta_madre: 'font-medium',
    cuenta: '',
    subcuenta: 'text-gray-600 text-sm',
}[nivel] || '');
</script>

<template>
    <AppLayout title="Admin / Plan de Cuentas">
        <Head title="Admin / Plan de Cuentas" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Plan de Cuentas</h2>
                <div class="flex items-center gap-3">
                    <select v-model="filterTipo" @change="changeFilter" class="w-48 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Todos los tipos</option>
                        <option v-for="t in tipoOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <a :href="route('admin.plan-cuentas.export')" class="text-sm text-indigo-600 hover:text-indigo-800">Exportar CSV</a>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Capítulos</div>
                    <div class="text-lg font-bold text-gray-900">{{ totales?.capitulos || 0 }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Cuentas / Subcuentas</div>
                    <div class="text-lg font-bold text-gray-900">{{ totales?.cuentas || 0 }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4 flex items-end justify-end">
                    <PrimaryButton @click="openCreate(null)">+ Nueva cuenta</PrimaryButton>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Árbol del Plan de Cuentas</h3>
                    <div class="flex items-center gap-2 text-sm">
                        <button type="button" class="text-indigo-600 hover:text-indigo-800" @click="expandAll">Expandir todo</button>
                        <span class="text-gray-300">|</span>
                        <button type="button" class="text-indigo-600 hover:text-indigo-800" @click="collapseAll">Colapsar todo</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nivel</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Naturaleza</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Contab.</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="node in arbol" :key="node.id">
                                <TreeNode
                                    :node="node"
                                    :depth="0"
                                    :expanded="expanded"
                                    @toggle="toggle"
                                    @create="openCreate"
                                    @edit="openEdit"
                                    @delete="confirmDelete = $event"
                                />
                            </template>
                            <tr v-if="!arbol.length">
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">Sin cuentas cargadas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="showCreateModal" @close="showCreateModal = false">
            <template #title>Crear {{ nivelLabel(createForm.nivel) }}</template>
            <template #content>
                <div class="text-sm text-gray-600 mb-3">Padre: <strong>{{ createParentLabel }}</strong></div>
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Código" />
                        <TextInput v-model="createForm.codigo" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.codigo" />
                    </div>
                    <div>
                        <InputLabel value="Código Corto" />
                        <TextInput v-model="createForm.codigo_corto" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.codigo_corto" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nombre" />
                        <TextInput v-model="createForm.nombre" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="createForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">Seleccionar...</option>
                            <option v-for="t in tipoOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Nivel" />
                        <select v-model="createForm.nivel" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="o in nivelOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.nivel" />
                    </div>
                    <div>
                        <InputLabel value="Naturaleza" />
                        <select v-model="createForm.naturaleza" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sin naturaleza</option>
                            <option v-for="n in naturalezaOptions" :key="n.value" :value="n.value">{{ n.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.naturaleza" />
                    </div>
                    <div>
                        <InputLabel value="Orden" />
                        <TextInput v-model="createForm.orden" type="number" min="0" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.orden" />
                    </div>
                    <div class="sm:col-span-2 flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="createForm.contabilizable" /> Contabilizable
                        </label>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="showCreateModal = false; createForm.reset()">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="createForm.processing" @click="submitCreate">Crear</PrimaryButton>
            </template>
        </DialogModal>

        <DialogModal :show="editing" @close="editing = false">
            <template #title>Editar {{ nivelLabel(editForm.nivel) }}</template>
            <template #content>
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submitEdit">
                    <div>
                        <InputLabel value="Código" />
                        <TextInput v-model="editForm.codigo" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.codigo" />
                    </div>
                    <div>
                        <InputLabel value="Código Corto" />
                        <TextInput v-model="editForm.codigo_corto" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.codigo_corto" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nombre" />
                        <TextInput v-model="editForm.nombre" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="editForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">Seleccionar...</option>
                            <option v-for="t in tipoOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Nivel" />
                        <select v-model="editForm.nivel" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="o in nivelOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.nivel" />
                    </div>
                    <div>
                        <InputLabel value="Naturaleza" />
                        <select v-model="editForm.naturaleza" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sin naturaleza</option>
                            <option v-for="n in naturalezaOptions" :key="n.value" :value="n.value">{{ n.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.naturaleza" />
                    </div>
                    <div>
                        <InputLabel value="Orden" />
                        <TextInput v-model="editForm.orden" type="number" min="0" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.orden" />
                    </div>
                    <div class="sm:col-span-2 flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="editForm.contabilizable" /> Contabilizable
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="editForm.activo" /> Activo
                        </label>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="editing = false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="editForm.processing" @click="submitEdit">Guardar</PrimaryButton>
            </template>
        </DialogModal>

        <DialogModal :show="!!confirmDelete" @close="confirmDelete = null">
            <template #title>Eliminar cuenta</template>
            <template #content>
                <p class="text-sm text-gray-700">¿Eliminar <strong>{{ confirmDelete?.codigo_completo || confirmDelete?.codigo }} - {{ confirmDelete?.nombre }}</strong>?</p>
                <p v-if="confirmDelete?.children?.length" class="text-sm text-red-600 mt-2">Tiene {{ confirmDelete.children.length }} hijo(s) que también se eliminarán.</p>
            </template>
            <template #footer>
                <SecondaryButton @click="confirmDelete = null">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3 bg-red-600 hover:bg-red-700" @click="submitDelete">Eliminar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import { h } from 'vue';

const TreeNode = {
    name: 'TreeNode',
    props: ['node', 'depth', 'expanded'],
    emits: ['toggle', 'create', 'edit', 'delete'],
    setup(props, { emit }) {
        const hasChildren = props.node.children?.length > 0;
        const isExpanded = props.expanded.has(props.node.id);

        const paddingLeft = () => `${12 + props.depth * 24}px`;

        const indent = () => {
            if (props.depth === 0) return '';
            return '─'.repeat(Math.min(props.depth, 3));
        };

        return () => {
            const children = hasChildren && isExpanded ? props.node.children.map(child =>
                h(TreeNode, {
                    node: child,
                    depth: props.depth + 1,
                    expanded: props.expanded,
                    'onToggle': (id) => emit('toggle', id),
                    'onCreate': (n) => emit('create', n),
                    'onEdit': (n) => emit('edit', n),
                    'onDelete': (n) => emit('delete', n),
                })
            ) : [];

            const toggleIcon = hasChildren
                ? h('button', {
                    class: 'text-gray-400 hover:text-gray-700 focus:outline-none text-xs mr-1',
                    onClick: () => emit('toggle', props.node.id),
                    innerHTML: isExpanded ? '▼' : '▶',
                })
                : h('span', { class: 'mr-3' });

            const bgClass = props.depth === 0 ? 'bg-gray-100 font-bold' :
                props.depth === 1 ? 'bg-gray-50 font-semibold' :
                props.depth === 2 ? 'font-medium' :
                props.depth === 3 ? '' : 'text-gray-600 text-sm';

            const nameText = props.node.codigo_completo
                ? `${props.node.codigo_completo} - ${props.node.nombre}`
                : `${props.node.codigo} - ${props.node.nombre}`;

            const row = h('tr', { class: [bgClass, 'hover:bg-indigo-50 transition'] }, [
                h('td', { class: 'px-4 py-2 text-sm' }, [
                    h('div', { class: 'flex items-center', style: { paddingLeft: paddingLeft() } }, [
                        toggleIcon,
                        h('span', nameText),
                        props.node.codigo_corto ? h('span', { class: 'ml-2 text-xs text-gray-400' }, `[${props.node.codigo_corto}]`) : null,
                    ]),
                ]),
                h('td', { class: 'px-4 py-2 text-sm font-mono' }, props.node.codigo_completo || props.node.codigo),
                h('td', { class: 'px-4 py-2 text-sm text-gray-500' }, props.node.nivel?.replace('_', ' ')),
                h('td', { class: 'px-4 py-2 text-sm text-gray-500' }, props.node.naturaleza || '-'),
                h('td', { class: 'px-4 py-2 text-sm text-center' }, props.node.contabilizable ? h('span', { class: 'text-green-600' }, '✓') : '-'),
                h('td', { class: 'px-4 py-2 text-sm text-center' }, props.node.activo ? h('span', { class: 'text-green-600' }, '✓') : h('span', { class: 'text-red-400' }, '✗')),
                h('td', { class: 'px-4 py-2 text-sm text-right' }, [
                    h('button', { class: 'text-indigo-600 hover:text-indigo-800 text-xs mr-2', onClick: () => emit('create', props.node) }, '+'),
                    h('button', { class: 'text-indigo-600 hover:text-indigo-800 text-xs mr-2', onClick: () => emit('edit', props.node) }, 'Editar'),
                    h('button', { class: 'text-red-500 hover:text-red-700 text-xs', onClick: () => emit('delete', props.node) }, 'Elim'),
                ]),
            ]);

            return [row, ...children];
        };
    }
};
</script>
