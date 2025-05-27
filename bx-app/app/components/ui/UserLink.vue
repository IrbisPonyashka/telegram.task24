<script setup lang="ts">
import {ref, onMounted, watch, onUnmounted} from "vue";
import {B24Frame, initializeB24Frame} from "@bitrix24/b24jssdk";

const props = defineProps<{ params: string | null }>(); // Разрешаем null
const userData = ref<{ name: string; lastName: string } | null>(null);

let $b24: B24Frame;
// Когда компонент монтируется, загружаем данные
onMounted(async () => {
  $b24 = await initializeB24Frame();
  if (props.params.value) {
    fetchUserData(props.params.value);
  }
});
onUnmounted(() => {
  $b24.value?.destroy();
})

// Отслеживаем изменения props.userId (если данные приходят позже)
watch(() => props.params, (newUserId) => {
  console.log("Изменился userId:", newUserId);
  if (newUserId) {
    fetchUserData(newUserId);
  }
});



const fetchUserData = async (userId: string) => {
  if (!userId) return;

  try {
    const res = await $BX24.callMethod("user.get", { filter: { ID: userId } });
    const user = res.getData().result[0];
    if (user) {
      userData.value = {
        id: userId,
        name: user.NAME,
        lastName: user.LAST_NAME
      };
    }
  } catch (error) {
    console.error("Ошибка загрузки пользователя", error);
  }
};

const handleClick = (userId) => {
  $b24.slider.openPath($b24.slider.getUrl('/company/personal/user/' + userId + '/'), 100);
  // if (window?.BX24) {
  //   window.BX24.slider.openPath(window.BX24.slider.getUrl(`/company/personal/user/${userId}/`), 100);
  // }
};

</script>

<template>
  <div v-if="userData">
    <B24Link
        raw
        active
        is-action
        active-class="font-bold text-blue-900"
        @click.prevent="handleClick(userData.id)"
    >
      {{ userData.name }} {{ userData.lastName }}
    </B24Link>
  </div>
  <div v-else>
    <span>Загрузка...</span>
  </div>
</template>
