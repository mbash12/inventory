import { createApp } from "vue";
import "./style.css";
import "virtual:windi.css";
import "@vuepic/vue-datepicker/dist/main.css";
import "vue-datepicker-next/index.css";
import 'v-calendar/style.css';
import App from "./App.vue";
import VCalendar from 'v-calendar';
import VueNumberFormat from '@coders-tm/vue-number-format';


import router from "./services/router";

createApp(App)
  .use(router)
  .use(VCalendar, {})
  .use(VueNumberFormat, {
    prefix: '',
    suffix: '',
    separator: '.',
    decimal: ',',
    precision: 2,
    prefill: false,
    reverseFill: false,
  })
  .mount("#app");
