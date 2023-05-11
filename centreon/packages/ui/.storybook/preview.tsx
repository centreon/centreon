import { useDarkMode } from "storybook-dark-mode";

import { ThemeMode } from "@centreon/ui-context";

import StoryBookThemeProvider from "../src/StoryBookThemeProvider";
import QueryProvider from "../src/api/QueryProvider";
import { Decorator, Preview } from "@storybook/react";
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";

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
  decorators: [
    withThemeProvider,
    withQueryProvider,
  ],
  globalTypes: {
    reactquerydevtools: {
      description: "React-Query devtools",
      defaultValue: false,
      toolbar: {
        title: "React-Query",
        items: [
          { value: true, icon: "circle", title: "Enable devtools" },
          { value: false, icon: "circlehollow", title: "Disable devtools" },
        ],
        dynamicTitle: false,
      },
    },
  },
  parameters: {
    actions: { argTypesRegex: "^on[A-Z].*" },
    a11y: {
      manual: true,
    },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/,
      }
    },
  }
};

export default preview;
