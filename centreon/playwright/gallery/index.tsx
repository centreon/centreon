import { Box, CssBaseline } from '@mui/material';

import { ThemeProvider } from '@centreon/ui';

import type { ComponentType } from 'react';
import { flushSync } from 'react-dom';
import { createRoot, type Root } from 'react-dom/client';

import '../../www/front_src/src/app.css';

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
    mount: (params: MountParams) => Promise<void>;
    unmount: () => Promise<void>;
  }
}

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

// The root is kept across calls so update() reconciles instead of remounting.
let root: Root | null = null;

// Rendering is synchronous, but the contract is promise-based: mount() rejects
// when the story is unknown or its render throws.
window.mount = ({ story, props = {} }: MountParams): Promise<void> => {
  const Story = resolveStory(story);

  root ??= createRoot(container);

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

  return Promise.resolve();
};

window.unmount = (): Promise<void> => {
  flushSync(() => {
    root?.render(null);
  });

  return Promise.resolve();
};
