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
    <QueryProvider>
      {story()}
      {context.globals.reactquerydevtools && <ReactQueryDevtools />}
    </QueryProvider>
  </StoryBookThemeProvider>
);

const preview: Preview = {
  decorators: [withThemeProvider],
  globalTypes: {
    reactquerydevtools: {
      name: "React-Query",
      description: "React-Query devtools",
      defaultValue: false,
      toolbar: {
        items: [
          { value: true, icon: "circle", title: "Enable devtools" },
          { value: false, icon: "circlehollow", title: "Disable devtools" },
        ],
        dynamicTitle: false,
      },
    },
  },
};

export default preview;
