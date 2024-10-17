<script setup>
import { ref, onMounted, reactive } from "vue";
import DatePicker from "vue-datepicker-next";

import VueDatePicker from "@vuepic/vue-datepicker";
import { Bar, Doughnut } from "vue-chartjs";
import { nom, getReport } from "../services/service";
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement,
} from "chart.js";
import dayjs from "dayjs";

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement
);
const state = reactive({
    isMontly: false,
    ready: false,
    year: null,
    date: null,
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false,
            },
        },
    },
    options1: {
        // indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: {
                display: false,
            },
        },
    },
    po_deposit_1: {
        labels: [],
        datasets: [
            {
                label: "Total Budget",
                backgroundColor: "#2CCBB4",
                data: [],
                // barThickness: 10,
                barPercentage: 0.7,
                categoryPercentage: 0.5,
            },
            {
                label: "Remaining Budget",
                backgroundColor: "#FE962D",
                data: [],
                // barThickness: 10,
                barPercentage: 0.7,
                categoryPercentage: 0.5,
            },
        ],
    },
    po_deposit_2: null,
    po_deposit_2_bar: {
        labels: [
            "Invoice On Progress",
            "Item Not Delivered",
            "Deposit Not Used",
        ],
        datasets: [
            {
                backgroundColor: ["#2CCBB4", "#FE962D", "#FF6060"],
                data: [],
            },
        ],
    },
    po_project_1: {
        labels: [],
        datasets: [
            {
                label: "Invoice on Progress",
                backgroundColor: "#2DD1EF",
                data: [],
                // barThickness: 10,
                barPercentage: 0.7,
                categoryPercentage: 0.5,
            },
            {
                label: "Item Not Delivered",
                backgroundColor: "#FF6060",
                data: [],
                // barThickness: 10,
                barPercentage: 0.7,
                categoryPercentage: 0.5,
            },
        ],
    },
    po_project_2: null,
    po_project_2_bar: {
        labels: ["Invoice On Progress", "Item Not Delivered"],
        datasets: [
            {
                backgroundColor: ["#2DD1EF", "#FF6060"],
                data: [],
            },
        ],
    },
    po_progress_1: {
        labels: [],
        datasets: [
            {
                label: "PO Not Delivered",
                backgroundColor: "#2DD1EF",
                data: [],
                // barThickness: 10,
                barPercentage: 0.4,
                categoryPercentage: 0.5,
            },
        ],
    },
    po_progress_2: null,
    po_progress_2_doughnut: {
        labels: ["PO Deposit", "PO Project"],
        datasets: [
            {
                backgroundColor: ["#2CCBB4", "#FF6060"],
                data: [],
            },
        ],
    },
    top_ten: null,
});
const date = ref();
function generateLabels(startDate, endDate) {
    const labels = [];
    const dateSet = new Set();

    const diffInDays = (endDate - startDate) / (1000 * 60 * 60 * 24);

    for (let i = 0; i <= diffInDays; i++) {
        const currentDate = new Date(
            startDate.getTime() + i * 24 * 60 * 60 * 1000
        );
        let label = currentDate.toISOString().substr(0, 10); // yyyy-mm-dd format

        if (diffInDays > 31) {
            label = label.substr(0, 7); // yyyy-mm format
        }

        if (!dateSet.has(label)) {
            labels.push(label);
            dateSet.add(label);
        }
    }

    return labels;
}
const init = () => {
    state.ready = false;
    let dates =
        dayjs(state.date[0]).format("YYYY-MM-DD") +
        "_" +
        dayjs(state.date[1]).format("YYYY-MM-DD");
    const date1 = state.date[0];
    const date2 = state.date[1];
    let labels = generateLabels(date1, date2);
    state.po_deposit_1.labels = labels;
    state.po_project_1.labels = labels;
    state.po_progress_1.labels = labels;
    getReport(dates).then((r) => {
        if (r.code == 200) {
            state.po_deposit_1.datasets[0].data = labels?.map((month) => {
                const entry = r.data.po_deposit_1.find(
                    (item) => item.month_name === month
                );
                return entry ? Number(entry.total_budget) : 0;
            });
            state.po_deposit_1.datasets[1].data = labels?.map((month) => {
                const entry = r.data.po_deposit_1.find(
                    (item) => item.month_name === month
                );
                return entry ? Number(entry.total_balance) : 0;
            });
            state.po_project_1.datasets[0].data = labels?.map((month) => {
                const entry = r.data.po_project_1.find(
                    (item) => item.month_name === month
                );
                return entry ? Number(entry.total_invoice_null) : 0;
            });
            state.po_project_1.datasets[1].data = labels?.map((month) => {
                const entry = r.data.po_project_1.find(
                    (item) => item.month_name === month
                );
                return entry ? Number(entry.total_not_delivered) : 0;
            });
            state.po_progress_1.datasets[0].data = labels?.map((month) => {
                const entry = r.data.po_progress_1.find(
                    (item) => item.month_name === month
                );
                return entry ? Number(entry.po_on_progress) : 0;
            });
            state.po_progress_2_doughnut.datasets[0].data = Object.values(
                r.data?.po_progress_2
            );
            state.po_project_2_bar.datasets[0].data = Object.values(
                r.data?.po_project_2
            );
            state.po_deposit_2_bar.datasets[0].data = Object.values(
                r.data?.po_deposit_2
            );
            state.po_progress_2 = r.data?.po_progress_2;
            state.po_deposit_2 = r.data?.po_deposit_2;
            state.po_project_2 = r.data?.po_project_2;
            state.top_ten = r.data?.top_ten;
            state.ready = true;
        }
    });
};
onMounted(() => {
    state.year = new Date().getFullYear();
    state.date = [dayjs().startOf("year").toDate(), new Date()];
    init();
    // const startDate = new Date();
    // const endDate = new Date(new Date().setDate(startDate.getDate() + 7));
    // date.value = [startDate, endDate];
});
</script>

<template>
    <div class="p-8">
        <div class="flex items-center gap-2 mb-6">
            <div class="text-2xl text-[#001737] font-semibold text-left">
                Dashboard
            </div>

            <div class="ml-auto">
                <date-picker v-model:value="state.date" range></date-picker>
                <!-- <VueDatePicker v-model="date" range style="height: 45px" /> -->

                <!-- <select
                    name="year"
                    id=""
                    v-model="state.year"
                    @change="init"
                    class="w-full h-full"
                >
                    <option
                        v-for="(year, i) in Array.from(
                            { length: 11 },
                            (_, i) => new Date().getFullYear() - 5 + i
                        )"
                        :value="year"
                    >
                        {{ year }}
                    </option>
                </select> -->
            </div>

            <button
                class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#306DCE] hover:shadow-sm hover:bg-gray-50 flex items-center"
                @click="init"
            >
                <!-- <i class="ri-printer-fill text-xl"></i> -->
                <strong>GO</strong>
            </button>
        </div>
        <div class="flex flex-col gap-8" v-if="state.ready">
            <div class="flex gap-8">
                <div
                    class="w-7/10 bg-white rounded-xl p-8 shadow-sm flex flex-col gap-4"
                >
                    <div class="flex">
                        <h3 class="font-semibold text-xl mb-10">PO Deposit</h3>
                        <div class="flex gap-2 items-center ml-auto">
                            <span
                                class="w-2 h-2 bg-[#2CCBB4] rounded-full"
                            ></span>
                            <span class="text-xs"> Total Budget</span>
                        </div>
                        <div class="flex gap-2 items-center ml-6">
                            <span
                                class="w-2 h-2 bg-[#FE962D] rounded-full"
                            ></span>
                            <span class="text-xs"> Remaining Budget</span>
                        </div>
                    </div>
                    <Bar
                        id="deposit_1"
                        :options="state.options"
                        :data="state.po_deposit_1"
                    />
                </div>
                <div
                    class="w-3/10 bg-white rounded-xl p-8 shadow-sm flex flex-col gap-2"
                >
                    <h3 class="font-semibold text-xl mb-10 mb-6">
                        PO Deposit Progress
                    </h3>

                    <Bar
                        :data="state.po_deposit_2_bar"
                        :options="state.options1"
                    />
                    <hr class="mb-3" />
                    <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#2CCBB4] rounded-full"
                        ></span>
                        <span class="text-sm"> Invoice On Progress</span>
                        <span class="text-sm ml-auto font-medium">
                            Rp.
                            {{
                                nom(state.po_deposit_2?.total_invoice_null ?? 0)
                            }}</span
                        >
                    </div>
                    <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#FF6060] rounded-full"
                        ></span>
                        <span class="text-sm"> Item Not Delivered</span>
                        <span class="text-sm ml-auto font-medium">
                            Rp.
                            {{
                                nom(
                                    state.po_deposit_2?.total_not_delivered ?? 0
                                )
                            }}</span
                        >
                    </div>
                    <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#FF9F40] rounded-full"
                        ></span>
                        <span class="text-sm"> Deposit Not Used</span>
                        <span class="text-sm ml-auto font-medium">
                            Rp.
                            {{
                                nom(state.po_deposit_2?.total_balance ?? 0)
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <div class="flex gap-8">
                <div
                    class="w-7/10 bg-white rounded-xl p-8 shadow-sm flex flex-col gap-4"
                >
                    <div class="flex">
                        <h3 class="font-semibold text-xl">PO Project</h3>
                        <div class="flex gap-2 items-center ml-auto">
                            <span
                                class="w-2 h-2 bg-[#2DD1EF] rounded-full"
                            ></span>
                            <span class="text-xs"> Invoice on Progress</span>
                        </div>
                        <div class="flex gap-2 items-center ml-6">
                            <span
                                class="w-2 h-2 bg-[#FF6060] rounded-full"
                            ></span>
                            <span class="text-xs"> Item Not Delivered</span>
                        </div>
                    </div>
                    <Bar
                        id="project_1"
                        :options="state.options"
                        :data="state.po_project_1"
                    />
                </div>
                <div
                    class="w-3/10 bg-white rounded-xl p-8 shadow-sm flex flex-col gap-2"
                >
                    <h3 class="font-semibold text-xl mb-6">PO Project</h3>
                    <Bar
                        :data="state.po_project_2_bar"
                        :options="state.options1"
                    />
                    <hr class="mb-3" />
                    <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#2DD1EF] rounded-full"
                        ></span>
                        <span class="text-sm"> Invoice On Progress</span>
                        <span class="text-sm ml-auto font-medium">
                            {{
                                nom(state.po_project_2?.total_invoice_null ?? 0)
                            }}</span
                        >
                    </div>
                    <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#FF6060] rounded-full"
                        ></span>
                        <span class="text-sm"> Item Not Delivered</span>
                        <span class="text-sm ml-auto font-medium">
                            {{
                                nom(
                                    state.po_project_2?.total_not_delivered ?? 0
                                )
                            }}</span
                        >
                    </div>
                </div>
            </div>
            <div class="flex gap-8">
                <div
                    class="w-7/10 bg-white rounded-xl p-8 shadow-sm flex flex-col gap-4"
                >
                    <div class="flex">
                        <h3 class="font-semibold text-xl">PO on Progress</h3>
                        <div class="flex gap-2 items-center ml-auto">
                            <span
                                class="w-2 h-2 bg-[#2DD1EF] rounded-full"
                            ></span>
                            <span class="text-xs"> PO Not Delivered</span>
                        </div>
                    </div>
                    <Bar
                        id="progress_1"
                        :options="state.options"
                        :data="state.po_progress_1"
                    />
                </div>
                <div
                    class="w-3/10 bg-white rounded-xl p-8 shadow-sm flex flex-col gap-2"
                >
                    <h3 class="font-semibold text-xl mb-6">Gand Total PO</h3>
                    <Doughnut
                        :data="state.po_progress_2_doughnut"
                        :options="state.options"
                    />
                    <hr class="mb-3" />
                    <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#2CCBB4] rounded-full"
                        ></span>
                        <span class="text-sm"> PO Deposit</span>
                        <span class="text-sm ml-auto font-medium">
                            {{
                                nom(state.po_progress_2?.total_po_deposit ?? 0)
                            }}</span
                        >
                    </div>
                    <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#FF6060] rounded-full"
                        ></span>
                        <span class="text-sm"> PO Project</span>
                        <span class="text-sm ml-auto font-medium">
                            {{
                                nom(
                                    state.po_progress_2?.total_po_non_deposit ??
                                        0
                                )
                            }}</span
                        >
                    </div>
                    <!-- <div class="flex gap-2 items-center">
                        <span
                            class="w-10px h-10px bg-[#FF9F40] rounded-full"
                        ></span>
                        <span class="text-sm"> PO On Progress</span>
                        <span class="text-sm ml-auto font-medium">
                            </span
                        >
                    </div> -->
                </div>
            </div>
            <div class="flex gap-8">
                <div
                    class="w-full bg-white rounded-xl p-8 shadow-sm flex flex-col gap-4"
                >
                    <div class="flex">
                        <h3 class="font-semibold text-xl mb-10">
                            Top 10 Client Report
                        </h3>
                    </div>
                    <table>
                        <thead>
                            <tr
                                class="text-left text-gray-500 text-sm border-b"
                            >
                                <th class="py-2">No</th>
                                <th class="py-2">Client Name</th>
                                <th class="py-2">Total PO</th>
                                <th class="py-2">Percentage Total PO</th>
                            </tr>
                        </thead>
                        <tbody class="text-14px">
                            <tr v-for="(row, i) in state.top_ten">
                                <td class="py-2">{{ i + 1 }}</td>
                                <td class="py-2">{{ row.client_company }}</td>
                                <td class="py-2">
                                    Rp. {{ nom(row.total_expense ?? 0) }}
                                </td>
                                <td class="py-2">
                                    <div class="flex gap-4 items-center">
                                        <div
                                            class="h-2 w-full bg-gray-200 rounded overflow-hidden"
                                        >
                                            <div
                                                class="h-full bg-green-500"
                                                :style="
                                                    'width:' +
                                                    row.percentage +
                                                    '%'
                                                "
                                            ></div>
                                        </div>
                                        <div class="w-80px text-right">
                                            {{ parseInt(row.percentage) }}%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
<style>
.dp__pointer {
    height: 45px;
    border-radius: 10px;
}
.mx-input{
    height: 45px;
}
</style>
