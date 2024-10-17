<script setup>
import { reactive, watch } from "vue";
import { projectDelivery } from "../services/service";
import { loading } from "../services/router";
const emit = defineEmits(["submit"]);
const props = defineProps({
    show: Boolean,
    project: Object,
    // shipping_vendors: Array,
    warehouses: Array
});
const state = reactive({
    id: null,
    // shipping_vendor: null,
    manufacture: null
});
watch(props, () => {
    if (props.show) {
        state.id = props.project?.id;
    }
});
const submit = () => {
    loading();
    projectDelivery(state.id, { manufacture: state.manufacture }).then(
        (r) => {
            loading(false);
            emit("submit");
        }
    );
};
</script>

<template>
    <Transition name="alert">
        <div
            class="w-full h-full fixed top-0 left-0 transition flex items-center justify-center z-10"
            v-if="show"
        >
            <div
                class="absolute w-full h-full bg-black bg-opacity-30 top-0 left-0 alertbg transition"
            ></div>
            <div
                class="w-10/12 bg-white absolute p-4 alertct transition rounded-lg relative max-w-360px"
            >
                <div
                    class="absolute alertcl right-4 transition cursor-pointer"
                    @click="$emit('hide')"
                >
                    <img src="../assets/icon-close.png" alt="" />
                </div>
                <form @submit.prevent="submit"
                    class="w-full flex flex-col items-center justify-center py-6 px-4"
                >
                    <!-- <label class="flex flex-col gap-2 w-full text-sm">
                        <span>Shipping Vendor</span>
                        <select
                            v-model="state.shipping_vendor"
                            class="border rounded h-10 p-2"
                            required=""
                        >
                            <option :value="null">
                                Select shipping vendor
                            </option>
                            <option
                                v-for="(wh, i) in shipping_vendors"
                                :value="wh.id"
                            >
                                {{ wh.name }}
                            </option>
                        </select>
                    </label>
                    <br> -->
                    <label class="flex flex-col gap-2 w-full text-sm">
                        <span>Manufacture</span>
                        <select
                            v-model="state.manufacture"
                            class="border rounded h-10 p-2"
                            required=""
                        >
                            <option :value="null">
                                Select manufacture
                            </option>
                            <option
                                v-for="(wh, i) in warehouses"
                                :value="wh.id"
                            >
                                {{ wh.name }}
                            </option>
                        </select>
                    </label>
                    <button
                        :class="`w-full h-12 flex items-center justify-center rounded-full text-white font-medium text-[16px]  cursor-pointer mt-8 bg-app-600`"
                    >
                        Save
                    </button>
                </form>
            </div>
        </div>
    </Transition>
</template>

<style>
/* .alert-enter-from,
.alert-leave-to {
  transform: translateX(-100vw);
} */

.alert-enter-from .alertbg,
.alert-leave-to .alertbg {
    opacity: 0;
}
.alert-enter-from .alertct,
.alert-leave-to .alertct,
.alert-enter-from .alertcl,
.alert-leave-to .alertcl {
    transform: scale(1.2);
    opacity: 0;
}
</style>
