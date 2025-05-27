<script setup lang="ts">
	import { ref, onMounted, watch, onUnmounted } from "vue";
	import { B24Frame, initializeB24Frame } from "@bitrix24/b24jssdk";
	import SuccessModal from "../ui/SuccessModal.vue";
	import ErrorModal from "../ui/ErrorModal.vue";

	const props = defineProps<{ params: any, app_connection_records: any }>();

	const emit = defineEmits(['update-records']);
	const modal = useModal();
	let app_connection_records = ref<Array<any>>([]); // Реактивный массив

	let $b24: B24Frame;
	// Когда компонент монтируется, загружаем данные

	onMounted(async () => {
		// app_connection_records.value = [...props.app_connection_records]; // Клонируем массив для реактивности
		$b24 = await initializeB24Frame();
	});

	onUnmounted(() => {
		$b24?.value?.destroy();
	})

	watch(() => props.app_connection_records, (newRecords) => {
		app_connection_records.value = [...newRecords];
	}, { immediate: true });


	const handleClick = async (record: any) => {
		console.log("record", record);
		let records = JSON.parse(JSON.stringify(app_connection_records.value));

		// Обновляем реактивный массив
		// app_connection_records.value = app_connection_records.value.filter(item => item.key !== record.key);
		records = records.filter(item => item.key !== record.key);
    	app_connection_records.value = records;

		console.log("Обновленный records:", records);

		let options = {
			"options": {
				"connection_records": records
			}
		};
		console.log("options", options);

		const saveResponse = await $b24.callMethod("app.option.set", options);
		console.log("saveResponse", saveResponse);
		if (saveResponse.getData().result) {
			emit('update-records', [...app_connection_records.value]);
			// props.app_connection_records = connectionRecords;
			openSuccessModal();
		}else{
		  openErrorModal(saveResponse.getData());
		}
	};


	const openSuccessModal = () => {
		modal.open(SuccessModal, {});
	}
	const openErrorModal = (errors: any) => {
		console.log("errors", errors);

		modal.open(ErrorModal, {
			"errors": errors
		});
	}
</script>

<template>

	<B24Modal
		class="light:bg-base-950"
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
