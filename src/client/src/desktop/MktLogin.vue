<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { loading } from "../services/router";
import { login } from "../services/service";
import Alert from "../components/Alert.vue";
const errors = reactive({
  email: false,
  password: false,
});
const user = reactive({
  email: "",
  password: "",
  passwordVisible: false,
});
const alertShowError = ref(false);
const router = useRouter();
const doLogin = async () => {
  loading();
  errors.email = false;
  errors.password = false;
  if (user.email == "") errors.email = "Please input email address";
  if (user.password == "") errors.password = "Please input password";
  if (errors.email || errors.password) {
    alertShowError.value = "Please input required fields";
    return loading(false);
  }
  login(user.email, user.password)
    .then((r) => {
      if (r.code === 200) {
        router.push("/desktop");
        return loading(false);
      } else {
        loading(false);
        if(r.errors){
            if (r.errors.email) errors.email = r.errors.email[0];
            if (r.errors.password) errors.password = r.errors.password[0];
        }
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
          <div class="w-full h-full flex flex-col">
            <div class="w-full flex-1 flex flex-col items-center gap-8">
              <!-- <img src="../assets/illustration-login.png" alt="" /> -->
              <img
                src="../assets/logo_pelangi_c.svg"
                alt=""
                class="h-[52px] object-contain mt-4"
              />
              <h1 class="text-gray-700 text-xl font-semibold">LOGIN</h1>
              <div class="flex flex-col gap-10 w-full items-end">
                <div class="w-full text-left">
                  <div
                    :class="`w-full border-b  h-[50px] flex ${
                      errors.email ? 'border-red-500' : 'border-app-110'
                    }`"
                  >
                    <div
                      :class="`w-[50px] h-[50px] border-r  flex items-center justify-center ${
                        errors.email ? 'border-red-500' : 'border-app-110'
                      }`"
                    >
                      <img
                        src="../assets/icon-mail.png"
                        alt=""
                        class="filter hue-218"
                      />
                    </div>
                    <input
                      type="text"
                      class="h-full flex-1 p-4 font-light text-[16px] bg-white text-black"
                      placeholder="Email"
                      v-model="user.email"
                      @input="errors.email = false"
                    />
                  </div>
                  <div
                    class="text-red-500 text-xs font-normal mt-2"
                    v-show="errors.email"
                  >
                    {{ errors.password }}
                  </div>
                </div>
                <div class="w-full text-left">
                  <div
                    :class="`w-full border-b  h-[50px] flex ${
                      errors.password ? 'border-red-500' : 'border-app-110'
                    }`"
                  >
                    <div
                      :class="`w-[50px] h-[50px] border-r  flex items-center justify-center ${
                        errors.password ? 'border-red-500' : 'border-app-110'
                      }`"
                    >
                      <img
                        src="../assets/icon-lock.png"
                        alt=""
                        class="filter hue-218"
                      />
                    </div>
                    <input
                      :type="user.passwordVisible ? 'text' : 'password'"
                      class="h-full flex-1 p-4 font-light text-[16px] bg-white text-black"
                      placeholder="Password"
                      v-model="user.password"
                      @input="errors.password = false"
                    />
                    <div
                      class="w-[50px] h-[50px] flex items-center justify-center"
                      @click="user.passwordVisible = !user.passwordVisible"
                    >
                      <img src="../assets/icon-eye-off.png" alt="" />
                    </div>
                  </div>

                  <div
                    class="text-red-500 text-xs font-normal mt-2"
                    v-show="errors.password"
                  >
                    {{ errors.password }}
                  </div>
                </div>
                <!-- <router-link to="/desktop/forgot-password">
                  <div class="text-app-300 font-normal text-[16px]">
                    Forgot Password?
                  </div>
                </router-link> -->
              </div>
              <div class="flex items-center justify-center w-full">
                <div
                  class="w-full h-12 bg-app-600 flex items-center justify-center rounded-full text-white font-medium text-[16px] mt-6 cursor-pointer"
                  @click="doLogin"
                >
                  Sign In
                </div>
              </div>
            </div>
            <div class="flex items-center justify-center h-24 flex-shrink-0">
              <div
                class="w-full font-normal text-[14px] text-app-112 text-center"
              >
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
</template>
