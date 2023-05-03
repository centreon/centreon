import React from 'react';

import { ComponentMeta, ComponentStory } from '@storybook/react';

import { Paper } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import StoryBookThemeProvider from '../StoryBookThemeProvider';

import { ColorStory } from '.';

export default {
  argTypes: {},
  component: ColorStory,
  title: 'Colors'
} as ComponentMeta<typeof ColorStory>;

const TemplateColorStory: ComponentStory<typeof ColorStory> = (args) => {
  const { themeMode } = args;

  return (
    <StoryBookThemeProvider themeMode={themeMode || ThemeMode.light}>
      <Paper
        sx={{
          padding: 2
        }}
      >
        <ColorStory {...args} />
      </Paper>
    </StoryBookThemeProvider>
  );
};

export const primaryLight = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="primary"
    themeMode={ThemeMode.light}
    title="primary"
  />
);

export const secondaryLight = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="secondary"
    themeMode={ThemeMode.light}
    title="secondary"
  />
);

export const statusLight = (): JSX.Element => (
  <TemplateColorStory isGrouped themeMode={ThemeMode.light} title="Status" />
);

export const textLight = (): JSX.Element => (
  <TemplateColorStory
    isText
    paletteKey="text"
    themeMode={ThemeMode.light}
    title="Text"
  />
);

export const backgroundLight = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="background"
    themeMode={ThemeMode.light}
    title="Background"
  />
);

export const actionLight = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="action"
    themeMode={ThemeMode.light}
    title="Action"
  />
);

export const primaryDark = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="primary"
    themeMode={ThemeMode.dark}
    title="primary"
  />
);

export const secondaryDark = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="secondary"
    themeMode={ThemeMode.dark}
    title="secondary"
  />
);

export const statusDark = (): JSX.Element => (
  <TemplateColorStory isGrouped themeMode={ThemeMode.dark} title="Status" />
);

export const textDark = (): JSX.Element => (
  <TemplateColorStory
    isText
    paletteKey="text"
    themeMode={ThemeMode.dark}
    title="Text"
  />
);

export const backgroundDark = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="background"
    themeMode={ThemeMode.dark}
    title="Background"
  />
);

export const actionDark = (): JSX.Element => (
  <TemplateColorStory
    paletteKey="action"
    themeMode={ThemeMode.dark}
    title="Action"
  />
);
