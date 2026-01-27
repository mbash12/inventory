<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { changePassword } from "../services/service";
import { loading } from "../services/router";
import Alert from "../components/Alert.vue";
const alertShowError = ref(false);
const alertShowSuccess = ref(false);
const router = useRouter();
const errors = reactive({
  newPassword: false,
  confirmNewPassword: false,
});
const user = reactive({
  newPassword: "",
  confirmNewPassword: "",
  passwordVisible: false,
  confirmPasswordVisible: false,
});
const doChangePassword = async () => {
  errors.newPassword = false;
  errors.confirmNewPassword = false;
  if (user.newPassword == "") errors.newPassword = "Please input new password";
  if (user.confirmNewPassword == "") errors.confirmNewPassword = "Please input confirm password";
  if (errors.newPassword || errors.confirmNewPassword) {
    alertShowError.value = "Please input required fields";
    return loading(false);
  }
  loading();
  changePassword(user.newPassword, user.confirmNewPassword)
    .then((r) => {
      if(r.code === 200){
        loading(false);
        alertShowSuccess.value = true;
        localStorage.removeItem('email')
        localStorage.removeItem('otp_code')
      }else{
        loading(false);
        if(r.errors.password) errors.newPassword = r.errors.password[0];
        if(r.errors.password_confirmation) errors.confirmNewPassword = r.errors.password_confirmation[0];
        alertShowError.value = r.message;
      }
    })
};
(() => {
  let email = localStorage.getItem("token");
  let otp_code = localStorage.getItem("otp_code");
  if (!(email || otp_code)) router.push("/login");
})();
</script>

<template>
  <div class="w-full h-full flex flex-col overflow-auto">
    <div class="w-full h-[190px] relative flex-shrink-0">
      <div class="w-full h-full absolute top-0 left-0">
        <img src="../assets/bg-0.jpg" alt="" class="w-full h-[245px] object-cover" />
      </div>
      <div class="w-full h-full absolute top-0 left-0">
        <div
          class="absolute top-6 left-4 w-10 h-10 flex items-center justify-center"
          @click="$router.back()"
        >
          <img src="../assets/icon-back.png" alt="" />
        </div>
        
      </div>
    </div>

    <div class="w-full flex-auto  bg-white relative rounded-t-3xl">
      <div class="w-full h-full px-8 flex flex-col">
        <div class="w-full flex-1 flex flex-col items-center gap-8 py-6">
          <!-- <img src="../assets/illustration-password.png" alt="" /> -->
          <div class="w-full   flex flex-col justify-center p-5">
          <div class="flex flex-col gap-2 w-full items-center mt-8">
            <div class="font-extrabold text-[28px] text-gray-800 font-raleway">
              Create New Password
            </div>
            <div class="font-normal text-[16px] text-gray-800 font-raleway">
              Enter your new password
            </div>
          </div>
        </div>
          <div class="flex flex-col gap-10 w-full items-end">
            <div class="w-full text-left">
              <div
                :class="`w-full border-b  h-[50px] flex ${
                  errors.newPassword ? 'border-red-500' : 'border-app-110'
                }`"
              >
                <div
                  :class="`w-[50px] h-[50px] border-r  flex items-center justify-center ${
                    errors.newPassword ? 'border-red-500' : 'border-app-110'
                  }`"
                >
                  <img src="../assets/icon-lock.png" alt=""  class="filter hue-218" />
                </div>
                <input
                  :type="user.passwordVisible ? 'text' : 'password'"
                  class="h-full flex-1 p-4 font-light bg-white text-black text-[16px]"
                  placeholder="New Password"
                  v-model="user.newPassword"
                  @input="errors.newPassword = false"
                />
                <div
                  class="w-[50px] h-[50px] flex items-center justify-center"
                  @click="user.passwordVisible = !user.passwordVisible"
                >
                  <img src="../assets/icon-eye-off.png" alt=""  />
                </div>
              </div>
              <div
                class="text-red-500 text-xs font-normal mt-2"
                v-show="errors.newPassword"
              >
                {{errors.newPassword}}
              </div>
            </div>
            <div class="w-full text-left">
              <div
                :class="`w-full border-b  h-[50px] flex ${
                  errors.confirmNewPassword ? 'border-red-500' : 'border-app-110'
                }`"
              >
                <div
                  :class="`w-[50px] h-[50px] border-r  flex items-center justify-center ${
                    errors.confirmNewPassword
                      ? 'border-red-500'
                      : 'border-app-110'
                  }`"
                >
                  <img src="../assets/icon-lock.png" alt=""  class="filter hue-218" />
                </div>

                <input
                  :type="user.confirmPasswordVisible ? 'text' : 'password'"
                  class="h-full flex-1 p-4 font-light text-[16px] bg-white text-black"
                  placeholder="Confirm New Password"
                  v-model="user.confirmNewPassword"
                  @input="errors.confirmNewPassword = false"
                />
                <div
                  class="w-[50px] h-[50px] flex items-center justify-center"
                  @click="
                    user.confirmPasswordVisible = !user.confirmPasswordVisible
                  "
                >
                  <img src="../assets/icon-eye-off.png" alt="" />
                </div>
              </div>
              <div
                class="text-red-500 text-xs font-normal mt-2"
                v-show="errors.confirmNewPassword"
              >
                {{errors.confirmNewPassword}}
              </div>
            </div>
          </div>
          <div class="flex items-center justify-center w-full">
            <div
              class="w-full h-12 bg-app-600 flex items-center justify-center rounded-full text-white font-medium text-[16px] mt-16 cursor-pointer"
              @click="doChangePassword"
            >
              Submit
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
  <Alert
    type="fail"
    title="Failed"
    :content="alertShowError"
    :show="alertShowError"
    @hide="alertShowError = false"
  ></Alert>
  <Alert
    type="success"
    title="Password changed successfully"
    content="Please login to your account"
    :show="alertShowSuccess"
    @hide="
      () => {
        alertShowSuccess = false;
        router.push('/login');
      }
    "
  ></Alert>
</template>
