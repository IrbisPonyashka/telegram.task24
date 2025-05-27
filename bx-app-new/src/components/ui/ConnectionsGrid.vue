<script setup lang="ts">

  import {initializeB24Frame} from "@bitrix24/b24jssdk";
  import { ref, computed } from "vue";
  import ActionButton from "../ui/ActionButton.vue";
  import RocketIcon from '@bitrix24/b24icons-vue/main/RocketIcon'
  import CopyPlatesIcon from '@bitrix24/b24icons-vue/actions/CopyPlatesIcon'

  import UserLink from "../ui/UserLink.vue";

  const props = defineProps<{ params: any }>();
  const columns = [
    { headerName: '№', field: 'number', width: 50 },
    { headerName: 'Сотрудник', field: 'employee_id', width: 150 },
    { headerName: 'Ключ привязки', field: 'key', width: 250 },
    { headerName: '', field: 'action', width: 120 },
  ];

  const connectionRecords = ref(props.params?.connection_records ?? null);

  const updateRecords = (newRecords: any) => {
    connectionRecords.value = newRecords; // Обновляем данные
  };

  const rows = computed(() => {
    if (props.params && props.params.connection_records) {
      console.log("connectionRecords.value", connectionRecords.value);
      
      return connectionRecords.value ?? props.params.connection_records.map((item: any) => ({
        id: item.id,
        employee_id: item.employee_id,
        key: item.key,
        action: item.id,
        timestamp: item.timestamp,
      }));
    }else if (!window.$BX24) {
      return connectionRecords.value ?? [
        // { id: 1, employee_id: '1', key: "abcdefghijklmnopqerst" ,   action: true },
        // { id: 5, employee_id: '2', key: "qwertyuiopasdfghjkl" ,   action: true },
        // { id: 6, employee_id: '3', key: "tsreqponmlkjihgfedcba" ,   action: true },
      ];
    }
  });


</script>
<template>
  <B24TableWrapper
      class="overflow-x-auto w-full bg-white"
      bordered
      rounded
      size="sm"
      :b24ui="{
      base: ''
    }"
  >
    <table>
      <!-- head -->
      <thead>
        <tr>
          <th
              v-for="column in columns"
              :id="column.field"
              :class="['px-4 py-2 text-left', column.field === 'employee_id' ? 'w-[300px]' : '']"
          >
            {{column.headerName}}
          </th>
        </tr>
      </thead>
      <tbody>
      <tr v-for="(row, number) in rows">
        <!--        <tr v-for="(row, number) in sortedRows" :key="row.id">-->
        <td
            v-for="column in columns"
            :class="[
                'px-4 py-2',
                column.field === 'number' ? 'w-[120px] whitespace-nowrap overflow-hidden text-ellipsis' : '',
                column.field === 'employee_id' ? 'w-[360px] whitespace-nowrap overflow-hidden text-ellipsis' : '',
                column.field === 'key' ? 'w-[260px] whitespace-nowrap overflow-hidden text-ellipsis' : '',
                column.field === 'action' ? 'w-[360px] text-right' : '',
              ]"
        >
          <div v-if="column.field == 'number' " >
            <span> {{ number+1 }} </span>
          </div>
          <div v-else-if="column.field == 'action' " >
              <span>
                <ActionButton
                    :params="row"
                    :app_connection_records="rows"
                    @update-records="updateRecords"
                >
                </ActionButton>
              </span>
          </div>
          <div v-else-if="column.field == 'employee_id' " >
              <span>
                <UserLink
                    :userId="row[column.field]"
                >
                </UserLink>
              </span>
          </div>
          <div v-else-if="column.field == 'key'" :id="column.field">
            <div  class="flex">
              <B24Input
                  size="xs"
                  :model-value="row[column.field]"
                  color="default"
                  placeholder="Search..."
              />
            </div>
          </div>


          <div v-else >
            <span> {{ row[column.field] }} </span>
          </div>
        </td>
      </tr>
      </tbody>
    </table>
  </B24TableWrapper>
</template>


