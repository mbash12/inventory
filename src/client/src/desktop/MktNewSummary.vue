<script setup>
import dayjs from "dayjs";
import { onMounted, reactive, ref } from "vue";
import {
    getProject,
    createProject,
    updateProject,
    currentUser,
    getClientList,
    nom,
    goto,
    ASSETSURL
} from "../services/service";
import { useRoute, useRouter } from "vue-router";
import { loading } from "../services/router";
import Confirm from "../components/Confirm.vue";
import Alert from "../components/Alert.vue";
import Select1 from "../components/Select1.vue";
import INumber from "../components/INumber.vue";
import Upload from "../components/Upload.vue";
import UploadModal from "../components/UploadModal.vue";
const confirmDelete = ref(false);
const alertShowSuccess = ref(false);
const alertShowFailed = ref(false);
const route = useRoute();
const router = useRouter();
const currentUrl = route.path;
const state = reactive({
    on_process: false,
    selected: null,
    selected_type: null,
    clients: [],
    errors: {},
    id: null,
    title: null,
    job_number: null,
    client_po_date: null,
    pic_name: null,
    total_price: null,
    client_po_number: null,
    client_company: null,
    client_pic_name: null,
    is_po_deposit: false,
    status: "new",
    products: [],
    documents: {
        do: false,
        bast: false,
        gr: false,
    },
    bast_files: [],
    gr_files: [],
    do_files: [],
    back: null,
    product_index: null,
});

const deleteSelectedProduct = () => {
    confirmDelete.value = false;
    state.prd[state.selected_type].splice(state.selected, 1);
};
const setDelete = (type, i) => {
    state.selected = i;
    state.selected_type = type;
    confirmDelete.value = true;
};
const submit = () => {
    loading();
    let status = state.status;

    // Check if documents are complete and files are uploaded
    if (
        state.documents.bast &&
        state.bast_files?.length > 0 &&
        state.documents.gr &&
        state.gr_files?.length > 0
    ) {
        status = "delivered";
    }

    let data = {
        title: state.title,
        total_price: state.total_price,
        job_number: state.job_number,
        client_po_date: state.client_po_date,
        pic_name: state.pic_name,
        client_po_number: state.client_po_number,
        client_company: state.client_company,
        client_pic_name: state.client_pic_name,
        status: status,
        products: state.products,
        documents: JSON.stringify(state.documents),
        project_type: state.project_type,
        bast_files: JSON.stringify(state.bast_files),
        gr_files: JSON.stringify(state.gr_files),
    };
    updateProject(state.id, data).then((r) => {
        loading(false);
        if (r.code === 200) {
            alertShowSuccess.value = true;
        } else {
            state.errors = r.errors;
            alertShowFailed.value = true;
        }
    });
};
onMounted(() => {
    state.pic_name = currentUser.user.user.name;
    state.back = route.query.back;
    loading();
    let id = currentUrl.split("/").slice(-1);
    getProject(id).then((r) => {
        loading(false);
        if (r.code === 200) {
            let data = r.data;
            state.id = data.id;
            state.job_number = data.job_number;
            state.client_po_date = dayjs(data.client_po_date).format(
                "YYYY-MM-DD"
            );
            state.pic_name = data.pic_name;
            state.client_po_number = data.client_po_number;
            state.client_company = data.client_company;
            state.client_pic_name = data.client_pic_name;
            state.status = data.status;
            state.is_po_deposit = data.is_po_deposit;
            state.title = data.title;
            state.total_price = data.total_price;
            state.products = [
                ...data.products_data.map((e) => ({
                    ...e,
                    design_files: JSON.parse(e.design_files),
                    design_approved: e.design_approved == 1,
                })),
            ];
            state.project_type = data.project_type;
            state.documents = JSON.parse(data.documents);
            state.bast_files = JSON.parse(data.bast_files);
            state.gr_files = JSON.parse(data.gr_files);

            if (data.client_po_number == null) {
                state.on_process = true;
            }
            setTimeout(() => {
                if (data.manufacture !== null) {
                    document
                        .querySelectorAll(
                            ".proform table input,.proform table  textarea,.proform table  select,.proform table  button"
                        )
                        .forEach((el) => {
                            el.setAttribute("disabled", true);
                        });
                }
                if (data.is_po_deposit) {
                    document
                        .querySelectorAll(
                            ".proform input, .proform textarea, .proform .input"
                        )
                        .forEach((el) => {
                            el.setAttribute("disabled", true);
                            el.classList.remove("bg-white");
                            el.classList.remove("bg-transparent");
                            el.classList.add("bg-gray-50");
                        });
                }
            }, 500);
        }
    });
});
</script>

<template>
    <div class="p-8">
        <div
            class="text-2xl text-[#001737] font-semibold mb-6 text-left flex items-center"
        >
            <div
                class="cursor-pointer mr-4"
                @click="goto(state.back + '?filter=true')"
            >
                <i class="ri-arrow-left-line"></i>
            </div>
            Summary {{ state.project_type }}

            <!-- <span class="p-2 rounded bg-red-500 text-white text-xs ml-2" v-show="state.manufacture !== null && currentUrl.includes('edit')
                ">Product Is Readonly</span> -->
        </div>
        <form @submit.prevent="submit">
            <div class="w-full bg-white rounded-lg shadow p-8 proform">
                <div class="flex gap-8">
                    <div class="w-1/2">
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left">Job Number</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="text"
                                    required
                                    class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                    v-model="state.job_number"
                                    disabled
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-briefcase-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty(
                                            'job_number'
                                        )
                                    "
                                    >{{ state.errors?.job_number[0] }}</span
                                >
                            </div>
                        </label>
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left">Title</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="text"
                                    class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                    v-model="state.title"
                                    disabled
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-briefcase-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="state.errors.hasOwnProperty('title')"
                                    >{{ state.errors?.title[0] }}</span
                                >
                            </div>
                        </label>

                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left"
                                >Marketing PIC Name</span
                            >
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="text"
                                    required
                                    class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                    v-model="state.pic_name"
                                    disabled
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-user-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty('pic_name')
                                    "
                                    >{{ state.errors?.pic_name[0] }}</span
                                >
                            </div>
                        </label>

                        <label class="flex flex-col gap-1 mb-1">
                            <div class="flex justify-between">
                                <span class="text-sm text-left">Omzet</span>
                            </div>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <INumber
                                    required
                                    class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                    v-model="state.total_price"
                                    disabled
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-money-dollar-box-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty('pic_name')
                                    "
                                    >{{ state.errors?.pic_name[0] }}</span
                                >
                            </div>
                        </label>
                    </div>
                    <div class="w-1/2">
                        <label class="flex flex-col gap-1 mb-1">
                            <div class="flex gap-4 items-center">
                                <div class="text-sm text-left leading-4">
                                    PO Number
                                </div>
                            </div>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <div
                                    class="bg-transparent w-full h-full rounded-lg pl-45px text-14px flex items-center"
                                >
                                    {{
                                        state.client_po_number ?? "Dalam Proses"
                                    }}
                                </div>
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-box-3-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty(
                                            'client_po_number'
                                        )
                                    "
                                    >{{
                                        state.errors?.client_po_number[0]
                                    }}</span
                                >
                            </div>
                        </label>
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left">PO Date</span>
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="date"
                                    class="bg-transparent w-full h-full rounded-lg pl-45px pr-4 noicon text-14px"
                                    v-model="state.client_po_date"
                                    onfocus="this.showPicker()"
                                    required
                                    disabled
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-calendar-event-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty(
                                            'client_po_date'
                                        )
                                    "
                                    >{{ state.errors?.client_po_date[0] }}</span
                                >
                            </div>
                        </label>
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left"
                                >Client Company</span
                            >
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <Select1
                                    @select="(e) => (state.client_company = e)"
                                    :value="state.client_company"
                                    required="true"
                                    disabled
                                />
                                <!-- <select
                                v-model="state.client_company"
                                required
                                class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                            >
                                <option
                                    v-for="option in state.clients"
                                    :value="option.name"
                                    :key="option.id"
                                >
                                    {{ option.name }}
                                </option>
                            </select> -->
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-hotel-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty(
                                            'client_company'
                                        )
                                    "
                                    >{{ state.errors?.client_company[0] }}</span
                                >
                            </div>
                        </label>
                        <label class="flex flex-col gap-1 mb-1">
                            <span class="text-sm text-left"
                                >Client PIC Name</span
                            >
                            <div
                                class="w-full border rounded-lg bg-white h-45px relative flex items-center"
                            >
                                <input
                                    type="text"
                                    required
                                    class="bg-transparent w-full h-full rounded-lg pl-45px text-14px"
                                    v-model="state.client_pic_name"
                                    disabled
                                />
                                <div
                                    class="absolute left-4 text-red-500 text-xl"
                                >
                                    <i class="ri-user-line"></i>
                                </div>
                            </div>
                            <div class="h-3 flex -mt-1">
                                <span
                                    class="text-xs text-red-500"
                                    v-if="
                                        state.errors.hasOwnProperty(
                                            'client_pic_name'
                                        )
                                    "
                                    >{{
                                        state.errors?.client_pic_name[0]
                                    }}</span
                                >
                            </div>
                        </label>
                    </div>
                </div>

                <!-- <div class="text-sm text-[#667085] mt-2 mb-2">
                    Documents to be Uploaded
                </div>
                <div class="border p-3 flex justify-between rounded-lg">
                    <label class="input flex items-center gap-2">
                        <input
                            type="checkbox"
                            :value="true"
                            v-model="state.documents.do"
                            disabled
                        />
                        <span class="text-sm">DO (Delivery Order)</span>
                    </label>
                    <label class="input flex items-center gap-2">
                        <input
                            type="checkbox"
                            :value="true"
                            v-model="state.documents.bast"
                            disabled
                        />
                        <span class="text-sm"
                            >BAST (Berita Acara Serah Terima)</span
                        >
                    </label>
                    <label class="input flex items-center gap-2">
                        <input
                            type="checkbox"
                            :value="true"
                            v-model="state.documents.gr"
                            disabled
                        />
                        <span class="text-sm">GR/TBP</span>
                    </label>
                    <span></span>
                </div> -->

                <div class="flex gap-8 w-full mt-4">
                    <div
                        class="flex flex-col flex-1"
                        v-if="state.project_type == 'design'"
                    >
                        <strong class="capitalize text-lg font-bold"
                            >Upload BAST Document</strong
                        >
                        <span class="text-sm text-gray-400"
                            >Only able to upload BAST if all Designs are
                            Approved</span
                        >
                        <span class="mt-4"></span>
                        <Upload
                            :files="state.bast_files ?? []"
                            @update="state.bast_files = $event"
                        />
                    </div>
                    <div class="flex flex-col flex-1" v-if="state.documents.gr">
                        <strong class="capitalize text-lg font-bold"
                            >Upload GR/TBP Document</strong
                        >
                        <span class="text-sm text-gray-400"
                            >Only able to upload GR/TBP if all Designs are
                            Approved</span
                        >
                        <span class="mt-4"></span>
                        <Upload
                            :files="state.gr_files ?? []"
                            @update="state.gr_files = $event"
                        />
                    </div>
                    <!-- <div
                        class="flex flex-col flex-1"
                        v-if="
                            ['gimmick', 'printing'].includes(state.project_type)
                        "
                    >
                        <strong class="capitalize text-lg font-bold"
                            >Upload DO Document</strong
                        >
                        <span class="text-sm text-gray-400"
                            >Only able to upload DO if all Products are
                            Delivered</span
                        >
                        <span class="mt-4"></span>
                        <Upload
                            :files="state.do_files"
                            @update="state.do_files = $event"
                        />
                    </div> -->
                </div>
            </div>

            <div class="mt-10 mb-4 flex flex-col">
                <strong class="capitalize text-xl font-bold">
                    {{ state.project_type }}
                </strong>
                <span class="text-sm text-gray">
                    Submitted document has been updated.
                </span>
            </div>

            <div class="w-full bg-white rounded-lg shadow p-8 proform">
                <div
                    class="border rounded-lg"
                    v-if="state?.project_type == 'gimmick'"
                >
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Product Name
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Quantity
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Description
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Delivery Orders
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="Product Name"
                                            v-model="product.name"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.quantity"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.quantity`
                                                ][0].replace(
                                                    `products.${i}.quantity `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <textarea
                                            class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent"
                                            placeholder="e.g. : Warna/Finishing"
                                            v-model="product.description"
                                            disabled
                                        ></textarea>

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div class="px-4 text-sm">
                                        <div v-if="product.deliveries && product.deliveries.length > 0">
                                            <div v-for="(delivery, index) in product.deliveries" :key="index" class="mb-1 flex items-center gap-2">
                                                <span>{{ delivery.do_number }}</span>
                                                <span class="text-gray-500">({{ delivery.quantity }} pcs)</span>
                                                <template v-if="delivery.do_files && delivery.do_files.length">
                                                    <a  v-for="item in delivery.do_files" :href="ASSETSURL+item" class="text-red-500 hover:text-blue-700" target="_blank">
                                                        <i class="ri-file-line"></i>
                                                    </a>
                                                </template>
                                            </div>
                                        </div>
                                        <div v-else class="text-gray-400">No deliveries yet</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="border rounded-lg"
                    v-if="state?.project_type == 'design'"
                >
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Item Name
                                </th>

                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Quantity
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Item Specification
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Design Approved?
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Approved Design Files
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="Item Name"
                                            v-model="product.name"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.quantity"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.quantity`
                                                ][0].replace(
                                                    `products.${i}.quantity `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <textarea
                                            class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent"
                                            placeholder="e.g. : Ukuran/sisi"
                                            v-model="product.description"
                                            disabled
                                        ></textarea>

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1 input"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="product.design_approved"
                                            :value="true"
                                        />
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-start flex-col gap-1 py-1 px-4"
                                    >
                                        <span class="text-sm text-gray"
                                            >{{
                                                product.design_files?.length ??
                                                0
                                            }}
                                            file{{
                                                product.design_files?.length
                                                    ? "s"
                                                    : ""
                                            }}
                                            uploaded</span
                                        >
                                        <button
                                            @click="state.product_index = i"
                                            type="button"
                                            class="border px-2 py-1 rounded text-xs text-gray"
                                        >
                                            {{
                                                product.design_files?.length
                                                    ? "Manage Files"
                                                    : "Upload Files"
                                            }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="border rounded-lg"
                    v-if="state?.project_type == 'printing'"
                >
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Product Name
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-160px">
                                    Quantity
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Specification
                                </th>

                                <th class="text-left text-sm px-4 py-2">
                                    Delivery Orders
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="Product Name"
                                            v-model="product.name"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="0"
                                            v-model="product.quantity"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.quantity`
                                                ][0].replace(
                                                    `products.${i}.quantity `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <textarea
                                            class="w-full h-full px-4 text-sm h-40px pt-10px bg-transparent"
                                            placeholder="e.g. : Ukuran/Sisi/Finishing"
                                            v-model="product.description"
                                            disabled
                                        ></textarea>

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>

                                <td class="">
                                    <div class="px-4 text-sm">
                                        <div v-if="product.deliveries && product.deliveries.length > 0">
                                            <div v-for="(delivery, index) in product.deliveries" :key="index" class="mb-1 flex items-center gap-2">
                                                <span>{{ delivery.do_number }}</span>
                                                <span class="text-gray-500">({{ delivery.quantity }} pcs)</span>
                                                <template v-if="delivery.do_files && delivery.do_files.length">
                                                    <a  v-for="item in delivery.do_files" :href="ASSETSURL+item" class="text-red-500 hover:text-blue-700" target="_blank">
                                                        <i class="ri-file-line"></i>
                                                    </a>
                                                </template>
                                            </div>
                                        </div>
                                        <div v-else class="text-gray-400">No deliveries yet</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="border rounded-lg"
                    v-if="state?.project_type == 'payment'"
                >
                    <table class="w-full rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="border-b">
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Supplier Name
                                </th>
                                <th class="text-left text-sm px-4 py-2 w-1/4">
                                    Nomor rekening
                                </th>
                                <th class="text-left text-sm px-4 py-2">
                                    Nominal
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b"
                                v-for="(product, i) in state.products"
                                :key="i"
                            >
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="e.g. : PT Astra"
                                            v-model="product.name"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.name`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.name`
                                                ][0].replace(
                                                    `products.${i}.name `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <input
                                            type="text"
                                            class="w-full h-40px px-4 text-sm bg-transparent"
                                            placeholder="e.g. : 83292092 "
                                            v-model="product.description"
                                            disabled
                                        />

                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.description`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.description`
                                                ][0].replace(
                                                    `products.${i}.description `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="">
                                    <div
                                        class="flex items-center flex-col gap-1"
                                    >
                                        <INumber
                                            class="w-full h-40px pl-4 pr-1 text-sm bg-transparent"
                                            placeholder="e.g. : 255.000 "
                                            v-model="product.quantity"
                                            required
                                            disabled
                                        />
                                        <span
                                            class="text-xs text-red-500 text-left w-full block pl-4"
                                            v-if="
                                                state.errors.hasOwnProperty(
                                                    `products.${i}.quantity`
                                                )
                                            "
                                            >{{
                                                state.errors[
                                                    `products.${i}.quantity`
                                                ][0].replace(
                                                    `products.${i}.quantity `,
                                                    ""
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-4 mt-8">
                    <button
                        class="h-45px px-4 border bg-white rounded-lg gap-2 shadow text-gray-700 hover:shadow-sm hover:bg-gray-50 flex items-center"
                        type="button"
                        @click="router.push(state.back + '?filter=true')"
                    >
                        <i class="ri-close-circle-line text-xl"></i>
                        <strong>Cancel</strong>
                    </button>
                    <button
                        class="h-45px px-4 border border-red-300 bg-red-500 rounded-lg gap-2 shadow text-white hover:shadow-sm hover:bg-red-600 flex items-center"
                        v-if="state.is_po_deposit == false"
                    >
                        <i class="ri-save-line text-xl"></i>
                        <strong>Save</strong>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <UploadModal
        :show="state.product_index !== null"
        @hide="state.product_index = null"
        :files="state.products[state.product_index]?.design_files ?? []"
        @action="state.products[state.product_index].design_files = $event"
    />

    <Confirm
        type="fail"
        title="Delete Confirmation"
        content="Are you sure want to delete selected product?"
        buttonText="Delete"
        :show="confirmDelete != false"
        @hide="confirmDelete = false"
        @fire="deleteSelectedProduct"
    >
    </Confirm>
    <Alert
        type="failed"
        title="Failed to save"
        content=""
        :show="alertShowFailed"
        @hide="
            () => {
                alertShowFailed = false;
            }
        "
    ></Alert>
    <Alert
        type="success"
        title="Project Data Saved"
        content=""
        :show="alertShowSuccess"
        @hide="
            () => {
                alertShowSuccess = false;
                router.push(state.back + '?filter=true');
            }
        "
    ></Alert>
</template>
