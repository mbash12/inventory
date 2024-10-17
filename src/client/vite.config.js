import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import WindiCSS from "vite-plugin-windicss";

export default defineConfig({
  plugins: [WindiCSS(), vue()],
  base: "/client/",
  optimizeDeps: {
    esbuildOptions: {
      target: 'es2020'
    }
  },
  build: {
    target: 'es2020',
    outDir: "../../public/client",
  }
});
