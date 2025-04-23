<script setup>
import Swal from "sweetalert2";
import { ref, computed } from 'vue';
import { upload,ASSETSURL } from "../services/service";

const props = defineProps({
    files: {
        type: Array,
        required: false,
        default: () => []
    },
    required: {
        type: Boolean,
        required: false,
        default: false
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false
    }
});

const emit = defineEmits(["update"]);

const isUploading = ref(false);
const validationError = ref(null);

const allowedExtensions = ["jpg", "png", "pdf", 'jpeg'];
const maxFileSize = 5 * 1024 * 1024; // 5MB

const hasFiles = computed(() => props.files?.length > 0);

const validateFile = (file) => {
    const fileExtension = file.name.split(".").pop().toLowerCase();
    
    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            error: "Only JPG, PNG, and PDF files are allowed."
        };
    }

    if (file.size > maxFileSize) {
        return {
            valid: false,
            error: "The file exceeds the maximum size of 5MB."
        };
    }

    return { valid: true };
};

const uploadFile = async (event) => {
    const file = event.target.files[0];
    if (!file) return;
    
    isUploading.value = true;
    validationError.value = null;

    try {
        const validation = validateFile(file);
        if (!validation.valid) {
            validationError.value = validation.error;
            Swal.fire({
                icon: "error",
                title: "Invalid File",
                text: validation.error,
            });
            return;
        }

        // Create a new array with the new file
        const result = await upload(file);
        if (!result.path) {
            throw new Error(result.message);
        }
        const updatedFiles = [...props.files, result.path];
        emit("update", updatedFiles);
    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: "error",
            title: "Upload Failed",
            text: "There was an error uploading your file.",
        });
    } finally {
        isUploading.value = false;
    }
};

const deleteFile = (index) => {
    Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
    }).then((result) => {
        if (result.isConfirmed) {
            emit("update", props.files?.filter((_, i) => i !== index));
            Swal.fire("Deleted!", "Your file has been deleted.", "success");
        }
    });
};

const getFileType = (filename) => {
    const extension = filename.split(".").pop().toLowerCase();
    switch (extension) {
        case "jpg":
        case "jpeg":
            return "JPEG Image";
        case "png":
            return "PNG Image";
        case "pdf":
            return "PDF Document";
        default:
            return "Unknown Type";
    }
};

const inputValue = computed(() => {
    return props.files && props.files.length > 0 ? 'valid' : '';
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4" v-if="!disabled">
        
        <div
            class="border border-dashed border-gray-200 p-6 flex flex-col gap-2 items-center justify-center rounded-xl relative"
            :class="{ 'opacity-50': isUploading }"
        >
            <i class="ri-upload-cloud-2-line text-4xl"></i>
            <span class="text-sm">{{ isUploading ? 'Uploading...' : 'Choose a file or drag & drop it here' }}</span>
            <span class="text-sm text-gray-400">JPEG, PNG, and PDF formats, up to 5MB</span>
            <div class="flex px-4 py-1.5 mt-1 rounded border text-gray-500 text-sm">
                Browse File
            </div>
            <input
                type="file"
                class="w-full h-full absolute top-0 left-0 opacity-0"
                @input="uploadFile($event)"
                accept="image/*,application/pdf"
                :disabled="isUploading"
            />
            
        </div>
        <input 
            type="text" 
            :name="'files-validation'"
            :value="inputValue"
            :required="props.required"
            class="opacity-0 h-1px w-full"
        />
        
        <div v-if="validationError" class="text-red-500 text-sm">
            {{ validationError }}
        </div>
        </div>  

        <div class="flex flex-col gap-2 overflow-y-auto max-h-300px" v-if="hasFiles">
            <div
                class="flex p-4 rounded-lg bg-gray-100 relative items-center gap-4"
                v-for="(file, index) in props.files"
                :key="index"
            >
                <svg
                    height="40"
                    viewBox="0 0 58 56"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M49.9372 54.3196H21.7977C18.2657 54.3196 15.4024 51.4563 15.4024 47.9242V8.27305C15.4024 4.741 18.2657 1.8777 21.7977 1.8777H38.8551L56.3326 19.3552V47.9242C56.3326 51.4563 53.4693 54.3196 49.9372 54.3196Z"
                        fill="#EEF1F7"
                        stroke="#CBD0DC"
                        stroke-width="2.55814"
                    />
                    <rect
                        x="0.692871"
                        y="24.9014"
                        width="38.3721"
                        height="22.3837"
                        rx="4.47674"
                        fill="#D82042"
                    />
                    <text
                        x="18"
                        y="42"
                        fill="#FFF"
                        font-size="14"
                        font-family="Arial"
                        text-anchor="middle"
                    >
                        {{ file.split(".").pop().toUpperCase() }}
                    </text>
                </svg>
                <a :href="ASSETSURL+file" target="_blank" class="flex flex-col">
                    <span class="text-sm">
                        {{ file.length > 20 ? "..." + file.slice(-20) : file }}
                    </span>
                    <span class="text-xs text-gray-400">{{
                        getFileType(file)
                    }}</span>
                </a>
                <span class="absolute top-1 right-2" v-if="!disabled" @click="deleteFile(index)">
                    <i class="ri-delete-bin-line text-xl cursor-pointer"></i>
                </span>
            </div>
        </div>
    </div>
</template>
