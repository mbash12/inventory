<script setup>
import { watch, ref} from "vue";
const printable = ref(null);
const exportable = ref(null);
const emit = defineEmits(["close"]);
const props = defineProps({
  data: Array,
  action: String,
});
function printDiv() {
  if (props.action === "print") {
    print();
  }
  if (props.action === "excel") {
    var wb = XLSX.utils.table_to_book(exportable.value);
    XLSX.writeFile(wb, "pelangi-export.xlsx");
  }
  setTimeout(() => {
    emit("close");
  }, 500);
}

watch(props, () => {
  if (props.data !== null) {
    setTimeout(() => {
      printDiv();
    }, 10);
  }
});
</script>

<template>
  <div
    v-if="data !== null"
    class="fixed top-0 left-0 bg-white z-30 overflow-auto w-full h-full printing"
    ref="printable"
  >
    <div class="min-w-full min-h-full flex justify-start items-start printable">
      <table border="1" class="border-collapse border text-xs" ref="exportable">
        <thead>
          <tr>
            <template v-for="(column, i) in Object.keys(data[0])" :key="i">
              <th
                style="vertical-align: top; align-text: left"
                align="left"
                class="border"
              >
                {{ column }}
              </th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template v-for="(row, i) in data" :key="i">
            <tr>
              <template v-for="(column, ii) in Object.values(row)" :key="ii">
                <td
                  v-html="column"
                  style="vertical-align: top; align-text: left"
                  class="border"
                ></td>
              </template>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
<style scoped>
@media screen {
  .printing {
    opacity: 0;
  }
}
@media print {
  @page {
    size: landscape;
  }

  body {
    visibility: hidden;
  }
  .printing {
    opacity: 1;
  }
  .printable {
    visibility: visible;
  }
  .printable table {
    min-width: 100%;
  }
  .printable * {
    font-size: 8pt;
  }
  th,
  td {
    padding: 4px;
    line-height: 1.2;
    border-width: 1px;
  }
}
</style>