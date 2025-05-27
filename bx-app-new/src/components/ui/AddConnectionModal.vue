<script setup lang="ts">
  import {onMounted, onUnmounted, ref} from 'vue';
  import {B24Frame, initializeB24Frame} from "@bitrix24/b24jssdk";
  import SuccessModal from "../ui/SuccessModal.vue";
  import ErrorModal from "../ui/ErrorModal.vue";

  const count = ref(0)

  const toast = useToast()
  const modal = useModal()

  const selectedUser = ref(null);
  const addConnectionModal = ref(false);
  const props = defineProps<{ userData: any, appOptions: any }>(); // Разрешаем null

  // const userData = computed(() => props.userData || []);
  // const propsConnectionRecords = computed(() => props.appOptions?.connection_records || []);
  // const propsConnectionRecords = computed(() => props.appOptions?.value?.connection_records || []);

  console.log("props addConnectionModal", props);

  let $b24: B24Frame;
  // Когда компонент монтируется, загружаем данные
  onMounted(async () => {
    $b24 = await initializeB24Frame();
  });
  onUnmounted(() => {
    $b24.value?.destroy();
  });

  const selectUser = async () => {
    if ($b24) {
      try {
        const result = await $b24.dialog.selectUser();
        if (result) {
          console.log("user result", result);
          selectedUser.value = result; // Обновляем выбранного пользователя
          addConnectionModal.value = true; // Открываем модалку
        }
      } catch (error) {
        console.error("Ошибка при выборе пользователя:", error);
      }
    } else {
      console.error("BX24 не инициализирован!");
    }
  };

  const saveConnection = async () => {
    if (!props.userData) {
      console.error("Пользователь не выбран!");
      return;
    }

    let connection_record_fields = {
      key: generateBindKey(),
      employee_id: props.userData.id,
      timestamp: Date.now(),
    };

    let connectionRecords:any = [];
    connectionRecords = Array.isArray(props?.appOptions?.connection_records)
        ? [...props.appOptions.connection_records]
        : [];
    console.log("connectionRecords", props.appOptions.connection_records);

    if(connectionRecords.length > 0)
    {
      let lastRecord = connectionRecords.reduce((max, item) => {
        return Number(item.id) > Number(max.id) ? item : max;
      }, connectionRecords[0]);

      // console.log("lastRecord", lastRecord);

      connection_record_fields.id = Number(lastRecord.id) + 1;
      connectionRecords.push(connection_record_fields);
    }else{
      connection_record_fields.id = 1;
      connectionRecords = [connection_record_fields];
    }

    let options = {
      "options": {
        "connection_records": connectionRecords
      }
    };

    console.log("options", options);

    try {
      const safeOptions = JSON.parse(JSON.stringify(options));
      console.log("safeOptions", safeOptions);
      const saveResponse = await $b24.callMethod("app.option.set", safeOptions);
      console.log("saveResponse", saveResponse);
      if (saveResponse.getData().result) {
        addConnectionModal.value = false; // Закрываем основную модалку
        props.appOptions.connection_records = connectionRecords;
        openSuccessModal();
      }else{
        openErrorModal();
      }
    } catch (error) {
      openErrorModal(error);
      console.error("Ошибка при сохранении данных:", error);
    }
  };

  const generateBindKey = (length = 24, useNumbers = true) => {
    const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    const numbers = "0123456789";
    const characters = useNumbers ? letters + numbers : letters;

    let key = "";
    for (let i = 0; i < length; i++) {
      key += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    return key;
  }

  const handleClick = (userId) => {
    $b24.slider.openPath($b24.slider.getUrl('/company/personal/user/' + userId + '/'), 100);
    // if (window?.BX24) {
    //   window.BX24.slider.openPath(window.BX24.slider.getUrl(`/company/personal/user/${userId}/`), 100);
    // }
  };

  const openSuccessModal = () => {
    modal.open(SuccessModal, {});
  }
  const openErrorModal = (errors) => {
    modal.open(ErrorModal, { "errors": errors } );
  }

</script>

<template>
    <!-- Модальное окно добавления -->
    <B24Modal
        :isOpen="addConnectionModal"
        @update:isOpen="(val) => (addConnectionModal = val)"
        title="Сохранить данные"
        description=""
    >

      <template #body>
       <span class="text-black">
         Выбранный сотрудник:
          <B24Link
              raw
              active
              is-action
              active-class="font-bold"
              @click.prevent="handleClick(props.userData.id)"
          >
            {{ props.userData.name }}
          </B24Link>
       </span>
      </template>

      <template #footer>
        <B24Button label="Сохранить" color="success" @click="saveConnection" />
        <B24ModalDialogClose>
            <B24Button label="Отменить" color="default" @click="addConnectionModal = false" />
        </B24ModalDialogClose>
      </template>
    </B24Modal>
</template>