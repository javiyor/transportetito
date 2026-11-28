<script setup>
import { Link } from '@inertiajs/vue3'
import { parentPath } from '@/Utils/navigation.js'
</script>

<template>
    <Link :href="parentPath()"><slot /></Link>
</template>
