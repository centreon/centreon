import type { StorybookConfig } from "@storybook/react-webpack5";
import remarkGfm from "remark-gfm";

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
  features: {},
  framework: {
    name: '@modern-js/storybook',
    options: {
      bundler: 'rspack'
    },
  },
  typescript: {
    reactDocgen: "react-docgen",
  },
  docs: {
    autodocs: "tag",
  },
};

export default config;
