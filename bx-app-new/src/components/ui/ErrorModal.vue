<script setup lang="ts">
import { ref, computed } from "vue";

import { B24Icon } from '@bitrix24/b24icons-vue'

export interface ExampleProps {
  title?: string;
  description?: string;
  errors?: any;
}

const props = withDefaults(defineProps<ExampleProps>(), {
  title: "Ошибка при сохранении данных!",
  description: "",
});

// Описываем computed для корректного отображения ошибок
const formattedDescription = computed(() => {
  console.log(props);
  return props.errors ? JSON.stringify(props.errors, null, 2) : props.description;
});
// const props = defineProps<{ errors: any }>(); // Разрешаем null

const toast = useToast()
const modal = useModal()

</script>

<!--      @update:isOpen="(val) => (addConnectionModal = val)"-->
<template>
  <B24Modal
      :isOpen="addConnectionModal"
      :title="title"
      :description="formattedDescription"
  >
    <template #header>
      <div class="w-full flex flex-col items-center">
        <B24Icon name="Main::CloudErrorIcon" class="w-12 h-15 text-red" />
        <p>
          {{ props.title }}
        </p>
        <pre class="">
          {{ formattedDescription }}
        </pre>
      </div>
    </template>
    <template #body>
      <div class="flex justify-center items-center">
      </div>
    </template>
    <template #footer>
      <B24ModalDialogClose>
        <B24Button label="Ок" color="default" />
      </B24ModalDialogClose>
    </template>
  </B24Modal>
</template>