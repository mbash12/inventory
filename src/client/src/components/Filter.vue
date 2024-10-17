<script setup>
import dayjs from "dayjs";
import { ref, reactive } from "vue";
import Datepicker from "vuejs3-datepicker";


const emit = defineEmits(["close"]);

const props = defineProps({
  show: Boolean,
  statuses: Array,
  filters: Object,
});
const filters = reactive(JSON.parse(JSON.stringify(props.filters)));
const openedDatePicker = ref(false);
const setStartDate = (e) => {
  filters.startDate = e;
  openedDatePicker.value = false;
};
const setEndDate = (e) => {
  filters.endDate = e;
  openedDatePicker.value = false;
};
const setStatuses = (e) => {
  filters.statuses = e.target.checked
    ? [...filters.statuses, e.target.value]
    : filters.statuses.filter((ee) => ee != e.target.value);
};
const save = () => {
  filters.category = "custom";
  Object.assign(props.filters, filters);
  emit("hide");
};
</script>

<template>
  <Transition name="filter">
    <div
      class="w-full h-full max-h-full fixed top-0 left-0 transition z-7"
      v-if="show"
    >
      <div
        class="absolute w-full h-full bg-black bg-opacity-30 top-0 left-0 filterbg transition"
        @click="$emit('hide')"
      ></div>
      <div
        class="w-full h-auto bg-white absolute bottom-0 left-0 p-4 filterct transition"
      >
        <div class="w-full h-full flex flex-col">
          <div class="flex justify-between border-b border-gray-300 p-4">
            <div class="text-[20px] font-medium text-black">Filters</div>
            <div
              class="font-medium text-[16px] text-app-200 cursor-pointer"
              @click="save"
            >
              SAVE
            </div>
          </div>
          <div class="max-h-[85vh] h-full w-full overflow-auto">
            <div class="flex flex-col p-4 w-full items-start py-2">
              <div class="py-2 font-normal text-black">Status</div>
              <div class="">
                <template v-for="status in statuses" :key="status">
                  <label class="flex w-full py-2 cursor-pointer" >
                    <input
                      type="checkbox"
                      name=""
                      id=""
                      @input="setStatuses"
                      :value="status"
                      v-bind:checked="filters.statuses.includes(status)"
                    />
                    <div
                      class="pl-2 capitalize font-light text-[15px] text-black"
                    >
                      {{ status }}
                    </div>
                  </label>
                </template>
              </div>
            </div>
            <div class="flex flex-col p-4 w-full items-start py-2">
              <div class="py-2 font-normal text-black">Start Date</div>
              <div
                class="h-11 w-full border border-gray-300 rounded-lg shadow-cs flex p-4 justify-between items-center cursor-pointer"
                @click="
                  openedDatePicker =
                    openedDatePicker === 'start' ? false : 'start'
                "
              >
                <img src="../assets/icon-calendar.png" alt="" />
                <div class="flex-1 text-left pl-4 text-black">
                  {{
                    filters.startDate &&
                    dayjs(filters.startDate).format("DD-MM-YYYY")
                  }}
                </div>
              </div>
              <Transition name="cal">
                <div
                  v-if="openedDatePicker === 'start'"
                  class="cal overflow-hidden transition-all rounded-lg mt-2 w-full text-black"
                >
                  <datepicker
                    :inline="true"
                    class="w-full text-black"
                    :value="filters.startDate"
                    @selected="setStartDate"
                  ></datepicker>
                </div>
              </Transition>
            </div>
            <div class="flex flex-col p-4 w-full items-start py-2">
              <div class="py-2 font-normal text-black">End Date</div>
              <div
                class="h-11 w-full border border-gray-300 rounded-lg shadow-cs flex p-4 justify-between items-center cursor-pointer"
                @click="
                  openedDatePicker = openedDatePicker === 'end' ? false : 'end'
                "
              >
                <img src="../assets/icon-calendar.png" alt="" />
                <div class="flex-1 text-left pl-4 text-black">
                  {{
                    filters.endDate &&
                    dayjs(filters.endDate).format("DD-MM-YYYY")
                  }}
                </div>
              </div>
              <Transition name="cal">
                <div
                  v-if="openedDatePicker === 'end'"
                  class="cal overflow-hidden transition-all rounded-lg mt-2 w-full text-black"
                >
                  <datepicker
                    :inline="true"
                    class="w-full text-black"
                    :value="filters.endDate"
                    @selected="setEndDate"
                  ></datepicker>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style>
.cal-enter-active,
.cal-leave-active {
  transition: all 0.2s;
  max-height: 500px;
}
.cal-enter-from,
.cal-leave-to {
  max-height: 0px;
}
.filter-enter-from .filterbg,
.filter-leave-to .filterbg {
  opacity: 0;
}
.filter-enter-from .filterct,
.filter-leave-to .filterct,
.filter-enter-from .filtercl,
.filter-leave-to .filtercl {
  transform: translateY(100vh);
}

.vuejs3-datepicker__calendar-actionarea,
.vuejs3-datepicker__calendar.vuejs3-green {
  position: relative;
  width: 100% !important;
  border-radius: 8px;
  margin-top: 0 !important;
}
.cal {
  box-shadow: 0 0.1rem 0.4rem rgb(0 0 0 / 8%);
  border: 1px solid #ddd;
}
.shadow-cs {
  box-shadow: 0 0.1rem 0.4rem rgb(0 0 0 / 8%);
}
.vuejs3-datepicker__calendar-actionarea > div {
  width: 100%;
  text-align: start;
  display: flex;
  flex-wrap: wrap;
}
.day__month_btn.up {
  text-transform: uppercase;
  font-weight: 800;
}
.cell.day-header {
  color: #888;
  font-weight: 500;
}
.vuejs3-datepicker__calendar-topbar {
  display: none;
}
.cell.day {
  border-radius: 8px !important;
}
.cell.day.selected {
  background: #0b9444 !important;
}
/* .cell.day.selected{
    background: transparent;
} */
/* .cell.day {
  position: relative;
  background: transparent !important;
  border: transparent !important;
  display: block;
}
.cell.day:not(.selected):hover {
  color: #0b9444;
} */
/* .cell.day:hover {
  border: transparent !important;
} */
/* .cell {
  background: transparent !important;
} */
/* 
.cell.day.selected::after {
  transition: all 0.3s ease;
  position: absolute;
  top: 0;
  content: "";
  width: 40px;
  height: 40px;
  left: 50%;
  transform: translateX(-50%);
  background: #0b9444;
  border-radius: 50%;
  
} */
</style>
