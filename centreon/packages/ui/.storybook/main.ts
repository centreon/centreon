import type { StorybookConfig } from '@storybook/react-vite';


const config: StorybookConfig = {
  stories: [
    '../src/**/*.mdx',
    '../src/**/*.stories.@(js|jsx|ts|tsx)'
  ],
  addons: [
    '@storybook/addon-essentials',
    {
      name: '@storybook/addon-docs',
      options: {
        configureJSX: true
      }
    },
    'storybook-addon-mock',
    'storybook-dark-mode',
    '@storybook/addon-mdx-gfm',
  ],
  features: {},
  framework: {
    name: '@storybook/react-vite',
    options: {}
  },
  typescript: {
    reactDocgen: 'react-docgen-typescript'
  },
  docs: {
    autodocs: 'tag'
  },
};

export default config;