import { Box, CssBaseline } from '@mui/material';

import { ThemeProvider } from '@centreon/ui';
import { ThemeMode, userAtom } from '@centreon/ui-context';

import 'dayjs/locale/en';
import 'dayjs/locale/pt';
import 'dayjs/locale/fr';
import 'dayjs/locale/es';
import 'dayjs/locale/de';

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
import i18next from 'i18next';
import { getDefaultStore } from 'jotai';
import type { ComponentType } from 'react';
import { flushSync } from 'react-dom';
import { createRoot, type Root } from 'react-dom/client';
import { initReactI18next } from 'react-i18next';

import '../../www/front_src/src/app.css';

// Same dayjs plugins as the app entry (Main/index.tsx).
for (const plugin of [
  localizedFormat,
  utcPlugin,
  timezonePlugin,
  isToday,
  isYesterday,
  weekday,
  isBetween,
  isSameOrBefore,
  duration
]) {
  dayjs.extend(plugin);
}

// No resources: labels render as their English keys, like the Cypress suite.
i18next.use(initReactI18next).init({ lng: 'en', resources: {} });

// The theme follows the emulated color scheme, so specs switch it with
// test.use({ colorScheme }) or page.emulateMedia({ colorScheme }).
const darkScheme = window.matchMedia('(prefers-color-scheme: dark)');

const applyColorScheme = (): void => {
  const store = getDefaultStore();
  const themeMode = darkScheme.matches ? ThemeMode.dark : ThemeMode.light;

  store.set(userAtom, { ...store.get(userAtom), themeMode });
  // Tailwind `dark:` variants, mirrored on the root element like Main/useUser.ts.
  document.documentElement.classList.toggle('dark', darkScheme.matches);
};

applyColorScheme();
darkScheme.addEventListener('change', applyColorScheme);

type StoryComponent = ComponentType<Record<string, unknown>>;

interface MountParams {
  props?: Record<string, unknown>;
  story: string;
}

declare const require: {
  context: (
    directory: string,
    useSubdirectories: boolean,
    regExp: RegExp
  ) => {
    (key: string): Record<string, StoryComponent>;
    keys: () => Array<string>;
  };
};

declare global {
  interface Window {
    galleryErrors: Array<string>;
    mount: (params: MountParams) => Promise<void>;
    unmount: () => Promise<void>;
  }
}

// Dashboards/.../Widgets/widgets.ts registers its test widgets only for Cypress
// component runs; replace with a runner-neutral flag once Cypress is gone.
(window as unknown as { Cypress: unknown }).Cypress = {
  testingType: 'component'
};

const storyModules = require.context(
  '../../www/front_src/src',
  true,
  /\.story\.tsx$/
);

// Story id = path under www/front_src/src without the .story.tsx extension,
// plus the export name. Any unique suffix of that id also resolves.
const stories = new Map<string, StoryComponent>();

for (const key of storyModules.keys()) {
  const modulePath = key.replace(/^\.\//, '').replace(/\.story\.tsx$/, '');

  for (const [exportName, story] of Object.entries(storyModules(key))) {
    stories.set(`${modulePath}/${exportName}`, story);
  }
}

const resolveStory = (id: string): StoryComponent => {
  const exactMatch = stories.get(id);

  if (exactMatch) {
    return exactMatch;
  }

  const suffixMatches = [...stories.keys()].filter((key) =>
    key.endsWith(`/${id}`)
  );

  if (suffixMatches.length === 0) {
    throw new Error(
      `Unknown story "${id}". Known stories: ${[...stories.keys()].join(', ')}`
    );
  }

  if (suffixMatches.length > 1) {
    throw new Error(
      `Ambiguous story "${id}", matching: ${suffixMatches.join(', ')}`
    );
  }

  return stories.get(suffixMatches[0]) as StoryComponent;
};

const container = document.getElementById('root') as HTMLElement;

// React 19 reports render and effect errors here instead of throwing them;
// tests/components/fixtures.ts fails the test when any is left.
window.galleryErrors = [];

const recordError = (error: unknown): void => {
  window.galleryErrors.push(
    error instanceof Error ? (error.stack ?? error.message) : String(error)
  );
};

// The root is kept across calls so update() reconciles instead of remounting.
let root: Root | null = null;

// mount() rejects when the story is unknown or when rendering it, or running
// its effects, throws.
window.mount = async ({ story, props = {} }: MountParams): Promise<void> => {
  const Story = resolveStory(story);
  const errorCount = window.galleryErrors.length;

  root ??= createRoot(container, {
    onCaughtError: recordError,
    onUncaughtError: recordError
  });

  // App-wide setup lives here, mirroring what the app entry provides.
  flushSync(() => {
    root?.render(
      <ThemeProvider>
        <CssBaseline />
        <Box sx={{ backgroundColor: 'background.paper' }}>
          <Story {...props} />
        </Box>
      </ThemeProvider>
    );
  });

  // Lets passive effects run before reporting.
  await new Promise((resolve) => setTimeout(resolve));

  const errors = window.galleryErrors.slice(errorCount);
  if (errors.length > 0) {
    throw new Error(`Story "${story}" failed to render:\n${errors.join('\n')}`);
  }
};

window.unmount = (): Promise<void> => {
  flushSync(() => {
    root?.render(null);
  });

  return Promise.resolve();
};
