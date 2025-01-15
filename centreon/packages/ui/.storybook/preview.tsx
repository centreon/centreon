import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import isBetween from 'dayjs/plugin/isBetween';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import isToday from 'dayjs/plugin/isToday';
import isYesterday from 'dayjs/plugin/isYesterday';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import weekday from 'dayjs/plugin/weekday';
import { initialize, mswLoader } from 'msw-storybook-addon';
import { useDarkMode } from 'storybook-dark-mode';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(isToday);
dayjs.extend(isYesterday);
dayjs.extend(weekday);
dayjs.extend(isBetween);
dayjs.extend(isSameOrBefore);
dayjs.extend(duration);

import { ThemeMode } from '@centreon/ui-context';

import { Decorator, Preview } from '@storybook/react';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import StoryBookThemeProvider from '../src/StoryBookThemeProvider';
import QueryProvider from '../src/api/QueryProvider';
import { allModes } from './modes';

initialize();

const withThemeProvider: Decorator = (story, context): JSX.Element => (
  <StoryBookThemeProvider
    themeMode={useDarkMode() ? ThemeMode.dark : ThemeMode.light}
  >
    {story()}
  </StoryBookThemeProvider>
);

const withQueryProvider: Decorator = (story, context): JSX.Element => (
  <QueryProvider>
    {story()}
    {context.globals.reactquerydevtools && <ReactQueryDevtools />}
  </QueryProvider>
);

const preview: Preview = {
  loaders: [mswLoader],

  decorators: [withThemeProvider, withQueryProvider],

  globalTypes: {
    reactquerydevtools: {
      description: 'React-Query devtools',
      defaultValue: false,
      toolbar: {
        title: 'React-Query',
        items: [
          { value: true, icon: 'circle', title: 'Enable devtools' },
          { value: false, icon: 'circlehollow', title: 'Disable devtools' }
        ],
        dynamicTitle: false
      }
    }
  },

  parameters: {
    // actions: { argTypesRegex: "^on[A-Z].*" },
    a11y: {
      manual: true
    },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/
      }
    },
    chromatic: {
      diffThreshold: 0.1,
      delay: 300,
      modes: {
        desktop: allModes.desktop
      }
    },
    viewport: {
      defaultViewport: 'tablet',
      defaultOrientation: 'landscape'
    }
  },

  tags: ['autodocs']
};

export default preview;
