<script setup lang="ts">
  import { ref, onMounted, onUnmounted } from 'vue';
  import { initializeB24Frame, B24Frame, useB24Helper } from '@bitrix24/b24jssdk'
  // import { useModal } from "@bitrix24/b24ui"; // Импорт useModal, если он есть
  import AddConnectionModal from "../src/components/ui/AddConnectionModal.vue";
  import ConnectionsGrid from "../src/components/ui/ConnectionsGrid.vue";

  let $b24: B24Frame;
  const { initB24Helper, destroyB24Helper, getB24Helper } = useB24Helper();

  const selectedUser = ref(null);
  const modal = useModal();
  const toast = useToast();

  const appOptions = ref(null);

  onMounted(async () => {
    try {
      $b24 = await initializeB24Frame();
      if($b24) {
        window.$BX24 = $b24;
        let getAppOptionsResponse = await $b24.callMethod('app.option.get',{});
        appOptions.value = getAppOptionsResponse.getData().result;
      }

    }catch (error) {
      console.error(error)
    }
  })

  onUnmounted(() => {
    $b24.value?.destroy();
  })

  const selectUser = async () => {
    if ($b24) {
      try {
        const result = await $b24.dialog.selectUser();
        if (result) {
          selectedUser.value = result; // Обновляем выбранного пользователя
          // addConnectionModal.value = true; // Открываем модалку
          console.log("openAddConctnModal appOptions", appOptions.value)
          openAddConctnModal(selectedUser.value, appOptions.value);
        }
      } catch (error) {
        console.error("Ошибка при выборе пользователя:", error);
      }
    } else {
      console.error("BX24 не инициализирован!");
    }
  };

  const openAddConctnModal = (userData, appOptions) => {
    modal.open(AddConnectionModal, {
      userData: userData,
      appOptions: appOptions,
      onSuccess() {
        toast.add({
          title: 'Success !',
          id: 'modal-success'
        })
      }
    })
  }

</script>

<template>
  <B24App class="text-base-master bg-gray-100 font-b24-system antialiased">
    <B24Container class="pt-6 pb-6" >
      <div class="work_content">
        <ConnectionsGrid :params="appOptions" />

        <div class="bottom_bar mt-6">
          <B24Button label="Добавить" color="primary" @click="selectUser">
            Добавить
          </B24Button>


          <!-- Модальное окно успеха -->
          <!--          <B24Modal-->
          <!--              :isOpen="successModal"-->
          <!--              @update:isOpen="(val) => (successModal = val)"-->
          <!--              title="Успешно сохранено">-->
          <!--            <template #body>-->
          <!--              <p>Данные о сотруднике успешно сохранены!</p>-->
          <!--            </template>-->
          <!--            <template #footer>-->
          <!--              <B24Button label="Ок" color="primary" @click="successModal = false" />-->
          <!--            </template>-->
          <!--          </B24Modal>-->
        </div>

      </div>
    </B24Container>
  </B24App>
</template>
