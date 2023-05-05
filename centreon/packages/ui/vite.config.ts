import path from "path";
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import smvp from "speed-measure-vite-plugin";
import svgr from "vite-plugin-svgr";

// https://vitejs.dev/config/
export default defineConfig({
  root: ".",
  base: "/",
  // TODO distribute a build / lib instead of source (including font assets)
  build: {
    lib: {
      entry: path.resolve(__dirname, "src/index.ts"),
      formats: ["es"],
      fileName: (format) => `index.${format}.js`,
    },
    rollupOptions: {
      external: ["react", "react-dom", "@emotion/react", "@emotion/styled"],
      output: {
        globals: {
          react: "React",
        },
      },
    },
    sourcemap: true,
  },
  esbuild: {},
  resolve: {
    alias: {
      "@centreon/ui/fonts": path.resolve(__dirname, "/fonts"),
    }
  },
  plugins: smvp([
    svgr({
      svgrOptions: {
        memo: true,
        icon: true,
        svgo: true,
        svgoConfig: {
          plugins: [
            {
              prefixIds: true,
            },
          ],
        },
      },
    }),
    react(),
  ]),
});
