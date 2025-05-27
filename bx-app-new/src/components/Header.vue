<script setup lang="ts">
    import type { ButtonProps } from '@bitrix24/b24ui-nuxt';
</script>
<template>
    <header class="bg-white">
        <B24Container class="text-black p-4 flex justify-between items-center">
            <nav class="flex justify-between items-center gap-4">
                <nuxt-link to="/">
                    <B24Button color="success"> Подключения </B24Button>
                </nuxt-link> 
                <nuxt-link to="/settings">
                    <B24Button color="default"> Настройки </B24Button>
                </nuxt-link> 
            </nav>
        </B24Container>
    </header>
</template>
  