import path from "path"
import { defineConfig } from "vite"
import react from "@vitejs/plugin-react-swc";
import smvp from 'speed-measure-vite-plugin';


// https://vitejs.dev/config/
export default defineConfig({
  root: ".",
  base: "/",
  build: {
    lib: {
      entry: path.resolve(__dirname, "src/index.ts"),
      formats: ["es"],
      fileName: "index",
    },
    rollupOptions: {
      external: ["react", "react-dom"],
      output: {
        globals: {
          react: "React",
          "react-dom": "ReactDOM",
        },
      },
    },
    sourcemap: true,
  },
  esbuild: {},
  plugins: smvp([react()]),
})
