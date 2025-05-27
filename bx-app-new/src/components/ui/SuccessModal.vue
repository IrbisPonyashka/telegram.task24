<script setup lang="ts">
import { ref } from 'vue';

const toast = useToast()
const modal = useModal()
console.log("dev")
</script>

<template>
  <B24Modal
      :isOpen="addConnectionModal"
      @update:isOpen="(val) => (addConnectionModal = val)"
      title="Данные успешно сохранены!"
      description=""
  >
    <template #footer>
      <B24ModalDialogClose>
        <B24Button label="Ок" color="success" @click="addConnectionModal = false" />
      </B24ModalDialogClose>
    </template>
  </B24Modal>
</template>