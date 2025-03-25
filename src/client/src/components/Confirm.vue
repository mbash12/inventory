<script setup>
const props = defineProps({
    show: Boolean,
    type: String,
    title: String,
    buttonText: String,
    content: String,
});
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
                <div
                    class="w-full flex flex-col items-center justify-center p-6"
                >
                    <img
                        src="../assets/alert-success.png"
                        alt=""
                        v-if="props.type === 'success'"
                    />
                    <img src="../assets/alert-error.png" alt="" v-else />
                    <div
                        class="my-6 mt-8 font-bold text-[20px] leading-6 text-black text-center"
                    >
                        {{ title }}
                    </div>
                    <div
                        class="font-normal text-[16px] leading-5 text-gray-500 text-center"
                    >
                        {{ content }}
                    </div>
                    <div class="flex gap-4 w-full">
                        <div
                            :class="`w-2/3 h-12 flex items-center justify-center rounded-full  bg-gray-100 font-medium text-[16px]  cursor-pointer mt-8 text-black`"
                            @click="$emit('hide')"
                        >
                            Close
                        </div>
                        <div
                            :class="`w-2/3 h-12 flex items-center justify-center rounded-full text-white font-medium text-[16px]  cursor-pointer mt-8 ${
                                props.type === 'success'
                                    ? 'bg-app-600'
                                    : 'bg-app-800'
                            }`"
                            @click="$emit('fire')"
                        >
                            {{ buttonText ?? "OK" }}
                        </div>
                    </div>
                </div>
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
