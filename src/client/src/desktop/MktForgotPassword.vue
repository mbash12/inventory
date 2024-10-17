<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import Alert from "../components/Alert.vue";
import { loading } from "../services/router";
import { forgot } from "../services/service";
const errors = reactive({
  email: false,
});
const user = reactive({
  email: "",
});
const alertShowError = ref(false);
const alertShowSuccess = ref(false);
const router = useRouter();
const sendResetEmail = async () => {
    errors.email = false;

  loading();
  if (user.email == "") errors.email = "Please input email address";
  if (errors.email) {
    alertShowError.value = "Please input required fields";
    loading(false);
    return;
  }
  forgot(user.email)
    .then((r) => {
      if(r.code === 200){
        loading(false);
        localStorage.setItem("email", user.email)
        alertShowSuccess.value = true;
      }else{
        loading(false);
        if(r.errors.email) errors.email = r.errors.email[0];
        alertShowError.value = r.message;
      }
    })
};
</script>

<template>
  <div class="flex fixed top-0 left-0 w-full h-full">
    <img
      src="../assets/bg-1.jpg"
      alt=""
      class="w-full h-full object-cover absolute filter blur-sm brightness-50"
    />
    <div class="flex w-full h-full relative gap-40">
      <div class="w-1/2 h-full flex items-center justify-end pr-20">
        <img src="../assets/logo-white.svg" alt="" class="h-70px" />
      </div>
      <div class="w-1/2 h-full flex items-center justify-start pl-20">
        <div
          class="bg-white rounded-2xl p-8 w-420px flex flex-col items-center shadow-xl pt-12"
        >
          <div class="w-full h-full  flex flex-col">
        <div class="w-full flex-1 flex flex-col items-center gap-8 ">
          <div class="w-full   flex flex-col justify-center p-5">
          <div class="flex flex-col gap-2 w-full items-center mt-8">
            <div class="font-extrabold text-[28px] text-gray-800 font-raleway">
              Forgot Password
            </div>
            <div class="font-normal text-[16px] text-gray-800 font-raleway">
              Please enter your email address
            </div>
          </div>
        </div>
          <div
            class="font-semibold text-[16px] text-app-600 leading-6 font-raleway my-2 text-center"
          >
            Enter the email you registered with to receive password change
            instructions
          </div>
          <div class="flex flex-col gap-10 w-full items-end">
            <div class="w-full text-left">
              <div
                :class="`w-full border-b  h-[50px] flex ${
                  errors.email ? 'border-red-500' : 'border-app-110'
                }`"
              >
                <div
                  :class="`w-[50px] h-[50px] border-r  flex items-center justify-center ${errors.email?'border-red-500':'border-app-110'}`"
                >
                  <img src="../assets/icon-mail.png" alt="" class=" filter hue-rotate-218"/>
                </div>
                <input
                  type="text"
                  class="h-full flex-1 p-4 font-light text-[16px] bg-white text-black"
                  placeholder="Email"
                  v-model="user.email"
                  @input="errors.email=false"
                />
              </div>
              <div class="text-red-500 text-xs font-normal mt-2" v-show="errors.email">
                {{errors.email}}
              </div>
            </div>
          </div>
          <div class="flex items-center justify-center w-full">
            <div
              class="w-full h-12 bg-app-600 flex items-center justify-center rounded-full text-white font-medium text-[16px] mt-16 cursor-pointer"
              @click="sendResetEmail"
            >
              Send
            </div>
          </div>
        </div>
        <div class="flex items-center justify-center h-24 flex-shrink-0">
          <div class="w-full font-normal text-[14px] text-app-112 text-center">
            © 2023 | Pelangi Sentral Kreasi
          </div>
        </div>
      </div>
        </div>
      </div>
    </div>
  </div>

  <Alert
    type="fail"
    title="Login failed"
    :content="alertShowError"
    :show="alertShowError != false"
    @hide="alertShowError = false"
  ></Alert>
  <Alert
    type="success"
    title="Email forget password complete."
    content="Password reset link was sent to your email. Please verify your email"
    :show="alertShowSuccess"
    @hide="
      () => {
        alertShowSuccess = false;
        router.push('/desktop/verify-otp');
      }
    "
  ></Alert>
</template>
