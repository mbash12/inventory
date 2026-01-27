<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import VOtpInput from "vue3-otp-input";
import { checkOtp, forgot } from "../services/service";
import Alert from "../components/Alert.vue";
import { loading } from "../services/router";
const router = useRouter();
const otpInput = ref(null);
const alertShowError = ref(false);
const alertShowSuccess = ref(false);
const resendCountDown = ref(120);
const user = reactive({
  otp: "",
});
const handleOnComplete = (value) => {
  null;
};
const handleOnChange = (value) => {
  user.otp = value;
};
const countDown = () => {
  let i = 120;
  const process = () => {
    i--;
    resendCountDown.value = i;
    if (i > 0) {
      setTimeout(() => {
        process();
      }, 1000);
    }
  };
  process();
};
const doCheckOtp = async () => {
  let email = localStorage.getItem("email");
  loading();
  checkOtp(user.otp, email).then((r) => {
    if (r.code === 200) {
      loading(false);
      localStorage.setItem("otp_code",user.otp);
      alertShowSuccess.value = true;
    } else {
      loading(false);
      alertShowError.value = r.message;
    }
  });
};
const resend = () => {
  if (resendCountDown.value > 0) return;
  loading();
  let email = localStorage.getItem("email");
  forgot(email).then((r) => {
    if (r.code === 200) {
      loading(false);
      resendCountDown.value = "Sent!";
      setTimeout(() => {
        countDown();
      }, 3000);
    } else {
      loading(false);
      alertShowError.value = r.message;
    }
  });
};
(() => {
  countDown();
  let email = localStorage.getItem("email");
  if (!email) router.push("/desktop/login");
})();
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
          <div class="w-full h-full flex flex-col">
        <div class="w-full flex-1 flex flex-col items-center gap-8 ">
          <div class="flex flex-col gap-4 w-full items-center mt-8">
            <div class="font-extrabold text-[28px] text-gray-800 font-raleway">
              OTP Verification
            </div>
            <div class="font-normal text-[16px] text-gray-800 font-raleway">
              We have sent OTP on your Email Addres
            </div>
          </div>
          <div class="flex flex-col gap-6 w-full">
            <div class="w-full">
              <v-otp-input
                ref="otpInput"
                input-classes="otp-input"
                separator=""
                :num-inputs="6"
                :should-auto-focus="true"
                :is-input-num="true"
                @on-change="handleOnChange"
                @on-complete="handleOnComplete"
              />
            </div>
          </div>

          <div class="flex items-center justify-center w-full">
            <div class="mr-2 text-[15px] font-normal text-app-112">
              Didn't receive a OTP?
            </div>
            <div
              :class="`text-[15px] ${
                resendCountDown == 0
                  ? 'text-app-600 font-extrabold cursor-pointer'
                  : resendCountDown == 'Sent!'
                  ? 'text-orange-500'
                  : 'text-app-112'
              }`"
              @click="resend"
            >
              {{
                resendCountDown == 0
                  ? "Resend OTP"
                  : typeof resendCountDown == "string"
                  ? resendCountDown
                  : `Resend in ${new Date(resendCountDown * 1000)
                      .toISOString()
                      .slice(14, 19)}`
              }}
            </div>
          </div>
          <div class="flex items-center justify-center w-full mb-12 mt-4">
            <div
              class="w-full h-12 bg-app-600 flex items-center justify-center rounded-full text-white font-medium text-[16px] cursor-pointer"
              @click="doCheckOtp"
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
    title="Failed"
    :content="alertShowError"
    :show="alertShowError!=false"
    @hide="alertShowError = false"
  ></Alert>
  <Alert
    type="success"
    title="Success"
    content="Please create new password"
    :show="alertShowSuccess"
    @hide="
      () => {
        alertShowSuccess = false;
        router.push('/desktop/create-new-password');
      }
    "
  ></Alert>
</template>


<style>
.otp-input {
  width: 100%;
  height: 70px;
  padding: 5px;
  margin: 0 10px;
  font-size: 44px;
  border-bottom: 4px solid rgba(0, 0, 0, 0.14);
  text-align: center;
  font-weight: 700;
  background-color: white;
  color: black;
}
</style>
