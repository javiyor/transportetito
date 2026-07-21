<template>
    <img :src="src" alt="Logo" class="block h-10 w-auto" />
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    src: { type: String, default: null },
});

const page = usePage();

const src = computed(() => {
    if (props.src) return props.src;
    const logoUrl = page.props.tt?.currentEmpresa?.logo_url;
    if (logoUrl) return logoUrl;
    return '/images/logo.jpeg';
});
</script>
