
<script setup lang="ts">
import {initializeB24Frame} from "@bitrix24/b24jssdk";
import { ref, computed } from "vue";
import type { GridOptions, DomLayoutType } from 'ag-grid-community';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';
import { AgGridVue } from "ag-grid-vue3";
import ActionButton from "@/components/ui/ActionButton.vue";
import UserLink from "@/components/ui/UserLink.vue";
ModuleRegistry.registerModules([AllCommunityModule]);

const props = defineProps<{ params: any }>();

const columnDefs = ref( [
  {
    headerName: '№',
    field: 'number',
    cellRenderer: (params: any) => params.value, // Просто строка (ID)
    width: 10, // Фиксированная узкая ширина
  },
  {
    headerName: 'Пользователь',
    field: "user",
    flex: 2, // Гибкая ширина, занимает больше места
    cellRenderer: markRaw(UserLink),
    cellRendererParams: (params: any) => ({
      userId: params.value
    })
  },
  {
    headerName: 'Ключ привязки',
    field: "bind_key",
    cellStyle: { textAlign: "center" }, // Прижимаем кнопку вправо
    flex: 3, // Больше места для длинного ключа
    cellRenderer: (params: any) => {

      const container = document.createElement('div');
      container.style.display = 'flex';
      container.style.alignItems = 'center';
      container.style.gap = '8px'; // Расстояние между элементами

      const keySpan = document.createElement('span');
      keySpan.textContent = params.value;
      keySpan.style.cursor = 'text';
      keySpan.style.userSelect = 'all';

      const copyButton = document.createElement('button');
      copyButton.textContent = '📋';
      copyButton.style.cursor = 'pointer';
      copyButton.style.border = 'none';
      copyButton.style.background = 'transparent';
      copyButton.style.fontSize = '18px';

      // Функция копирования
      copyButton.addEventListener('click', () => {
        navigator.clipboard.writeText(params.value)
            .then(() => {
              copyButton.textContent = '✅';
              setTimeout(() => copyButton.textContent = '📋', 1000);
            })
            .catch(err => console.error('Ошибка копирования:', err));
      });

      container.appendChild(keySpan);
      container.appendChild(copyButton);

      return container;
      // return `
      //       <div style="display: flex; align-items: center;">
      //           <span style="cursor: text; user-select: all;">${params.value}</span>
      //               <!-- <button onclick="copyToClipboard('${params.value}')"
      //               style="margin-left: 10px; padding: 2px 5px; cursor: pointer;">
      //               📋
      //           </button> -->
      //       </div>
      //   `;
    }
  },
  {
    headerName: '',
    field: "action",
    width: 120, // Фиксированная ширина для кнопки
    cellStyle: { textAlign: "right" }, // Прижимаем кнопку вправо
    cellRenderer: markRaw(ActionButton),
    cellRendererParams: (params: any) => ({
      params: params,
      app_connection_records: props?.params?.connection_records,
    })
  }
]);

const rowData = computed(() => {
  if (props.params && props.params.connection_records) {
    return props.params.connection_records.map(item => ({
      number: item.id,
      user: item.employee_id,
      bind_key: item.key,
      action: item.id
    }));
  }else if (!window.$BX24) {
    return [
      { number: 1, user: 'Иван', bind_key: "aojd2opw3aihda5ihuwd" ,action: true },
      { number: 2, user: 'Мария', bind_key: "aojd2opw3aihda5ihuwd" ,action: true },
      { number: 3, user: 'Пётр', bind_key: "aojd2opw3aihda5ihuwd" ,action: true },
    ];
  }
});

const gridOptions = ref({
  suppressLastEmptyLine: true,
  suppressHorizontalScroll: true,
  domLayout: "autoHeight" as DomLayoutType,
  defaultColDef: {
    sortable: true,
    resizable: true
  },
  onGridReady: function (params: any){
    params.api.sizeColumnsToFit();
  }
});

const addConnectionModal = ref(false);

onMounted(async () => {
  try {

  }catch (error) {
    console.error(error)
  }
})

</script>
<template>
  <div class="ag-theme-alpine">
    <AgGridVue
        :columnDefs="columnDefs"
        :rowData="rowData"
        :gridOptions="gridOptions"
        rowSelection="single"
        class="ag-grid"
    />
  </div>
</template>