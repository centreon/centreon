import type { StorybookConfig } from '@storybook/react-vite';
import { dirname, join } from 'path';
import remarkGfm from 'remark-gfm';
import turbosnap from 'vite-plugin-turbosnap';

const config: StorybookConfig = {
  stories: ['../src/**/*.mdx', '../src/**/*.stories.@(js|jsx|ts|tsx)'],
  addons: [
    getAbsolutePath('@storybook/addon-themes'),
    getAbsolutePath('@storybook/addon-essentials'),
    {
      name: '@storybook/addon-docs',
      options: {
        configureJSX: true,
        mdxPluginOptions: {
          mdxCompileOptions: {
            remarkPlugins: [remarkGfm]
          }
        }
      }
    },
    getAbsolutePath('@storybook/addon-a11y'),
    getAbsolutePath('storybook-addon-mock'),
    getAbsolutePath('storybook-dark-mode')
  ],
  framework: {
    name: getAbsolutePath('@storybook/react-vite'),
    options: {}
  },
  typescript: {
    reactDocgen: 'react-docgen-typescript'
  },
  docs: {},
  core: {
    builder: '@storybook/builder-vite'
  },
  async viteFinal(config, { configType }) {
    const { mergeConfig } = await import('vite');
    return mergeConfig(config, {
      plugins:
        configType === 'PRODUCTION'
          ? [turbosnap({ rootDir: process.cwd() })]
          : []
    });
  }
};

export default config;

function getAbsolutePath(value: string): any {
  return dirname(require.resolve(join(value, 'package.json')));
}
