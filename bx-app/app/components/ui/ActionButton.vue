<script setup lang="ts">

  import {ref, onMounted, watch, onUnmounted} from "vue";
  import {B24Frame, initializeB24Frame} from "@bitrix24/b24jssdk";

  const removeConnectionModal = ref(false);

  const props = defineProps<{ params: string | null, app_connection_records: any }>(); // Разрешаем null

  let $b24: B24Frame;
  // Когда компонент монтируется, загружаем данные
  onMounted(async () => {
    $b24 = await initializeB24Frame();
  });
  onUnmounted(() => {
    $b24.value?.destroy();
  })

  const handleClick = (params: any) => {
    console.log("params", params);
    let app_connection_records = [...params.app_connection_records];
    let rowData = params.data;

    app_connection_records = app_connection_records.filter(item => item.key !== rowData.bind_key);

    let options = {
      "options": {
        "connection_records": app_connection_records
      }
    };

    const saveResponse = await $b24.callMethod("app.option.set", safeOptions);
    console.log("saveResponse", saveResponse);
    if (saveResponse.getData().result) {
      addConnectionModal.value = false; // Закрываем основную модалку
      props.appOptions.connection_records = connectionRecords;
      openSuccessModal();
    }else{
      openErrorModal();
    }

    console.log("app_connection_records", app_connection_records);
    console.log("rowData", rowData);
  };
</script>

<template>

    <B24Modal
        :isOpen="removeConnectionModal"
        class="light:bg-base-950"
        v-model="removeConnectionModal"
        title="Вы уверены, что хотите удалить запись?"
        description="Восстановить данные после удаления не получится."
    >
      <B24Button label="Удалить" color="danger" size="xs" > </B24Button>
      <template #footer>
        <B24ModalDialogClose>
          <B24Button label="Удалить" color="danger" size="sm" @click.prevent="handleClick(props.params)"/>
        </B24ModalDialogClose>
        <B24ModalDialogClose>
          <B24Button label="Отменить" color="default"  size="sm" />
        </B24ModalDialogClose>
      </template>

    </B24Modal>
<!--    <B24Button :color="redColor" :size="xsSize" @click="handleClick">-->
<!--        Удалить-->
<!--    </B24Button>-->
</template>
