import type { StorybookConfig } from "@storybook/react-vite";
import remarkGfm from "remark-gfm";
import turbosnap from 'vite-plugin-turbosnap';
import { mergeConfig } from 'vite';

const config: StorybookConfig = {
  stories: ["../src/**/*.mdx", "../src/**/*.stories.@(js|jsx|ts|tsx)"],
  addons: [
    "@storybook/addon-essentials",
    {
      name: "@storybook/addon-docs",
      options: {
        configureJSX: true,
        mdxPluginOptions: {
          mdxCompileOptions: {
            remarkPlugins: [remarkGfm],
          },
        },
      },
    },
    "@storybook/addon-styling",
    "@storybook/addon-a11y",
    "@storybook/addon-interactions",
    "storybook-addon-mock",
    "storybook-dark-mode",
  ],
  framework: {
    name: "@storybook/react-vite",
    options: {},
  },
  typescript: {
    reactDocgen: "react-docgen-typescript",
  },
  docs: {
    autodocs: "tag",
  },
  core: { builder: '@storybook/builder-vite' },
  async viteFinal(config, { configType }) {
    return mergeConfig(config, {
      plugins: configType === 'PRODUCTION' ? [turbosnap({ rootDir: process.cwd() })] : [],
    });
  },
};

export default config;