<script setup>
import { ref, onMounted, reactive, computed } from "vue";

const chartRef = ref(null);

import { Bar, Doughnut, Line } from "vue-chartjs";  // remove Area import
import { nom, getReport } from "../services/service";
import dayjs from "dayjs";

import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement,
    PointElement,
    LineElement,
} from "chart.js";
ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement,
    PointElement,
    LineElement
);
const state = reactive({
    isMontly: false,
    ready: false,
    year: new Date().getFullYear(),
    page: 1,
    limit: 10,
    data: null,
    search: '',
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
        },
        scales: {
            x: {
                grid: {
                    display: true,
                    drawBorder: false,
                    color: 'rgba(200, 200, 200, 0.2)'
                }
            },
            y: {
                grid: {
                    display: true,
                    drawBorder: false,
                    color: 'rgba(200, 200, 200, 0.2)'
                }
            }
        }
    },

});


const computedPages = computed(() => {
    const total = totalPages.value ?? 1;
    const current = state.page;

    // Always show 5 consecutive pages centered around current
    let start = Math.max(1, current - 2);
    let end = Math.min(total, current + 2);

    // Adjust window if near edges
    if (current <= 3) {
        end = Math.min(total, 5);
    }
    if (current >= total - 2) {
        start = Math.max(1, total - 4);
    }

    return Array.from(
        { length: end - start + 1 },
        (_, i) => start + i
    );
});


const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const chartData = computed(() => ({
    labels: months,
    datasets: [{
        label: 'Sales',
        data: Object.values(state.data?.grand_total_by_month ?? {}).map(Number),
        backgroundColor: function (context) {
            const chart = context.chart;
            const { ctx, chartArea } = chart;
            if (!chartArea) return null;
            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
            gradient.addColorStop(0, 'rgba(54, 162, 235, 0)');
            gradient.addColorStop(1, 'rgba(54, 162, 235, 0.3)');
            return gradient;
        },
        borderColor: 'rgb(54, 162, 235)',
        borderWidth: 2,
        tension: 0,
        fill: true,
        pointRadius: 4,
        pointBackgroundColor: 'rgb(54, 162, 235)',
        pointBorderColor: '#fff',
        pointBorderWidth: 2
    }]
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
    },
    scales: {
        x: {
            grid: {
                display: true,
                drawBorder: false,
                color: 'rgba(200, 200, 200, 0.2)'
            }
        },
        y: {
            grid: {
                display: true,
                drawBorder: false,
                color: 'rgba(200, 200, 200, 0.2)'
            }
        }
    }
}));

const clientPercentages = computed(() => {
  const clients = state.data?.top_5_clients || [];
  const total = clients.reduce((acc, client) => acc + (Number(client.total) || 0), 0);
  return clients.map(client => {
    if (total === 0) return 0;
    return ((Number(client.total) / total) * 100).toFixed(1);
  });
});

const filteredPoBreakdown = computed(() => {
  if (!state.data?.po_breakdown) return [];
  const searchTerm = state.search.toLowerCase();
  return state.data.po_breakdown.filter(item => 
    item.client_company.toLowerCase().includes(searchTerm)
  );
});

const totalItems = computed(() => filteredPoBreakdown.value.length);
const totalPages = computed(() => Math.ceil(totalItems.value / state.limit));

const paginatedData = computed(() => {
  const start = (state.page - 1) * state.limit;
  const end = start + state.limit;
  return filteredPoBreakdown.value.slice(start, end);
});

const doSearch = () => {
  state.page = 1;
};

const prevPage = () => {
  if (state.page > 1) state.page--;
};

const nextPage = () => {
  if (state.page < totalPages.value) state.page++;
};

const goToPage = (page) => {
  state.page = page;
};

const init = async () => {
    const res = await getReport(state.year);
    state.data = res.data;
};
onMounted(async () => {
    state.year = new Date().getFullYear();
    await init();
    
});
</script>

<template>
    <div class="p-8">
        <div class="flex items-center gap-2 mb-6">
            <div class="text-2xl text-[#001737] font-semibold text-left">
                Dashboard
            </div>

            <div class="ml-4 relative">
                <select v-model="state.year"
                    class="h-45px pl-4 pr-10 border bg-white rounded-lg appearance-none w-[200px]">
                    <option v-for="year in Array.from({ length: 10 }, (_, i) => new Date().getFullYear() - i)" :key="year"
                        :value="year">
                        {{ year }}
                    </option>
                </select>
                <i class="ri-calendar-line absolute transform right-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
            </div>

            <button
                class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-[#306DCE] hover:shadow-sm hover:bg-gray-50 flex items-center"
                @click="init">
                <strong>GO</strong>
            </button>
        </div>
        <div class="flex flex-col gap-8">
            <div class="flex gap-8">
                <div class="w-6/10 bg-white rounded-xl p-6 shadow-sm flex flex-col gap-4">
                    <Line ref="chartRef" :data="chartData" :options="chartOptions" />
                </div>
                <div class="w-4/10 bg-white rounded-xl p-6 shadow-sm flex flex-col gap-4">
                    <strong class="text-xl mb-2">Top 5 Client Report</strong>
                    <div class="flex gap-1 text-sm w-full" v-for="(item, i ) in state.data?.top_5_clients" :key="i">
                        <div class="w-3">
                            {{ i+1 }}.
                        </div>
                        <div class="flex flex-col flex-1">
                            <span>{{ item?.client_company }}</span>
                            <span class="text-gray-500">Rp {{ nom(item?.total??0) }}</span>
                            <div class="flex gap-2 items-center">
                                <div class="w-full rounded h-2 bg-gray-100 overflow-hidden w-full">
                                    <div class="h-2 bg-green-500" :style="{ width: clientPercentages[i] + '%' }"></div>
                                </div>
                                <span class="text-gray-500">{{ clientPercentages[i] }}%</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="w-full bg-white rounded-xl p-6 shadow-sm flex flex-col gap-4">
                <table class="tbblx fst">
                    <thead>
                        <tr>
                            <th>
                                PO
                            </th>
                            <th v-for="i in months">
                                {{ i }}
                            </th>
                            
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>
                                PO Deposit
                            </th>
                            <td v-for="i in state.data?.deposit_true_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                PO Non-Deposit
                            </th>
                            <td v-for="i in state.data?.deposit_false_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                PO Deposit<br>
                                (Belum Invoice)
                            </th>
                            <td v-for="i in state.data?.deposit_true_not_sent_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                PO Non-Deposit<br>
                                (Belum Invoice)
                            </th>
                            <td v-for="i in state.data?.deposit_false_not_sent_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="tbblx scn">
                    <thead>
                        <tr>
                            <th>
                                Not Delivered
                            </th>
                            <th v-for="i in months">
                                {{ i }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>
                                New
                            </th>
                            <td v-for="i in state.data?.new_projects_by_month">
                                Rp {{ nom(i)  }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Production
                            </th>
                            <td v-for="i in state.data?.production_projects_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Ready to Deliver
                            </th>
                            <td v-for="i in state.data?.ready_projects_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Partial Delivery
                            </th>
                            <td v-for="i in state.data?.partial_projects_by_month">
                                Rp {{nom(i)}}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table class="tbblx trd">
                    <thead>
                        <tr>
                            <th>

                            </th>
                            <th v-for="i in months">
                                {{ i }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>
                                Deposit Not Used
                            </th>
                            <td v-for="i in state.data?.remaining_amount_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                PO On Progress
                            </th>
                            <td v-for="i in state.data?.null_po_number_by_month">
                                Rp {{ nom(i) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="w-full bg-white rounded-xl p-6 shadow-sm flex flex-col gap-4">
                <div class="flex items-center justify-between">

                    <strong class="text-xl mb-2">PO Breakdown</strong>

                    <div>
                        <form
                            class="rounded-full w-350px h-45px border bg-white relative flex items-center border-gray-300"
                            @submit.prevent="doSearch">
                            <input type="text" class="h-full w-full rounded-full bg-transparent pl-45px"
                                placeholder="Search" v-model="state.search" @input="state.page=1" />
                            <i class="ri-search-line absolute left-4 text-xl text-[#667085]"></i>
                        </form>
                    </div>
                </div>
                <table class="tbbly">
                    <thead>
                        <tr>
                            <th>
                                No
                            </th>
                            <th>
                                Client Name
                            </th>
                            <th>
                                <span class="text-red-500">
                                    Not Issued
                                </span>
                            </th>
                            <th>
                                <span class="text-green-500">
                                    Issued
                                </span>
                            </th>
                            <th>
                                Grand Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in paginatedData">
                            <td>
                                {{ i+1 }}
                            </td>
                            <td>
                                {{ item.client_company }}
                            </td>
                            <td>
                                Rp. {{ nom(item.not_issued) }}
                            </td>
                            <td>
                                Rp. {{ nom(item.issued) }}
                            </td>
                            <td>
                                Rp. {{ nom(item.total) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex gap-1 text-md justify-center mt-6">
                    <!-- Previous Button -->
                    <button
                        class="h-[40px] px-3 border bg-white rounded shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center justify-center disabled:opacity-50"
                        :disabled="state.page === 1" @click="prevPage">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>

                    <!-- Pagination Buttons -->
                    <template v-for="(p, index) in computedPages" :key="p">
                        <!-- Insert ellipsis if there's a gap between consecutive pages -->
                        <template v-if="index > 0 && p - computedPages[index - 1] > 1">
                            <span class="h-[40px] px-3 flex items-center justify-center">...</span>
                        </template>
                        <button class="h-[40px] px-3 flex items-center justify-center font-semibold" :class="state.page === p
                        ? 'border bg-white rounded shadow text-red-500 hover:shadow-sm hover:bg-gray-50 '
                        : 'text-gray-700'
                    " :disabled="state.page === p" @click="goToPage(p)">
                            <span>{{ p }}</span>
                        </button>
                    </template>

                    <!-- Next Button -->
                    <button
                        class="h-[40px] px-3 border bg-white rounded shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center justify-center disabled:opacity-50"
                        :disabled="state.page === totalPages" @click="nextPage">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
<style>
select {
    cursor: pointer;
    background-color: white;
}

select:focus {
    outline: none;
    border-color: #306DCE;
}

.tbblx {
    font-size: 11px;
    margin-bottom: 30px;

    thead {
        background: #eee;
    }

    tr {
        border-bottom: 1px solid #ddd;
    }

    th:not(:first-child),
    td {
        width: 7.6%;
    }

    td {
        padding: 2px 10px;
        text-align: center;
    }

    th {
        padding: 6px 10px;
        text-align: center;
    }

    th:first-child {
        text-align: left;
    }

    tbody {
        th {
            height: 50px;
        }
    }

    &.fst tbody {
        th {
            background: #E9AD2C35;
            font-weight: 400;
        }
    }

    &.scn tbody {
        th {
            background: #FF5C5C35;
            font-weight: 400;
        }
    }

    &.trd tbody {
        th {
            background: #5AB5FF55;
            font-weight: 400;
        }
    }
}

.tbbly {
    font-size: 12px;

    th,
    td {
        text-align: left;
        padding: 6px 10px;
    }

    thead {
        border-bottom: 1px solid #ddd;
    }
}
</style>
