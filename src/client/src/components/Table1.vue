<script setup>
import { watch, ref} from "vue";
const printable = ref(null);
const exportable1 = ref(null);
const exportable2 = ref(null);
const exportable3 = ref(null);
const emit = defineEmits(["close"]);
const props = defineProps({
    data: Array,
    proj: Array,
    prod: Array,
    action: String,
});
function printDiv() {
    if (props.action === "print") {
        print();
    }
    if (props.action === "excel") {
        let tbl1 = exportable1.value
        let tbl2 = exportable2.value
        let tbl3 = exportable3.value
            
        let worksheet_tmp1 = XLSX.utils.table_to_sheet(tbl1);
        let worksheet_tmp3 = XLSX.utils.table_to_sheet(tbl3);
        let worksheet_tmp2 = XLSX.utils.table_to_sheet(tbl2);
            
        let a = XLSX.utils.sheet_to_json(worksheet_tmp1, { header: 1 })
        let c = XLSX.utils.sheet_to_json(worksheet_tmp3, { header: 1 })
        let b = XLSX.utils.sheet_to_json(worksheet_tmp2, { header: 1 })
            
        a = ['',['Project Data'],''].concat(a).concat(['',['Product Data'],'']).concat(c).concat(['',['Delivery Data'],'']).concat(b)
          
        let worksheet = XLSX.utils.json_to_sheet(a, { skipHeader: true })
        
        const new_workbook = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(new_workbook, worksheet, "worksheet")
        XLSX.writeFile(new_workbook, 'pelangi-export.xlsx')
    }
    setTimeout(() => {
        emit("close");
    }, 500);
}

watch(props, () => {
    if (props.data !== null) {
        setTimeout(() => {
            printDiv();
        }, 100);
    }
});
</script>

<template>
    <div
        v-if="data !== null"
        class="fixed top-0 left-0 bg-white z-30 overflow-auto w-full h-full printing"
        ref="printable"
    >
        <div
            class="min-w-full min-h-full flex justify-start items-start printable flex flex-col gap-4"
        >
            <strong>Project Data</strong>
            <table
                border="1"
                class="border-collapse border text-xs w-full"
                ref="exportable1"
            >
                <thead>
                    <tr>
                        <template
                            v-if="proj"
                            v-for="(column, i) in Object.keys(proj[0])"
                            :key="i"
                        >
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
                    <template v-for="(row, i) in proj" :key="i">
                        <tr>
                            <template
                                v-for="(column, ii) in Object.values(row)"
                                :key="ii"
                            >
                                <td
                                    v-html="column"
                                    style="
                                        vertical-align: top;
                                        align-text: left;
                                    "
                                    class="border"
                                ></td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
            <strong>Product Data</strong>
            <table
                border="1"
                class="border-collapse border text-xs w-full"
                ref="exportable3"
            >
                <thead>
                    <tr>
                        <template
                            v-if="prod"
                            v-for="(column, i) in Object.keys(prod[0])"
                            :key="i"
                        >
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
                    <template v-for="(row, i) in prod" :key="i">
                        <tr>
                            <template
                                v-for="(column, ii) in Object.values(row)"
                                :key="ii"
                            >
                                <td
                                    v-html="column"
                                    style="
                                        vertical-align: top;
                                        align-text: left;
                                    "
                                    class="border"
                                ></td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
            <strong>Delivery Data</strong>
            <table
                border="1"
                class="border-collapse border text-xs w-full"
                ref="exportable2"
            >
                <thead>
                    <tr>
                        <template
                            v-if="data"
                            v-for="(column, i) in Object.keys(data[0])"
                            :key="i"
                        >
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
                            <template
                                v-for="(column, ii) in Object.values(row)"
                                :key="ii"
                            >
                                <td
                                    v-html="column"
                                    style="
                                        vertical-align: top;
                                        align-text: left;
                                    "
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
    .printable {
        visibility: visible;
    }
    .printing {
        opacity: 1;
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
