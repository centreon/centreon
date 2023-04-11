import { ThemeMode } from '@centreon/ui-context';
import { Paper } from '@mui/material';
import { ComponentMeta, ComponentStory } from '@storybook/react';
import React from 'react';

import { ColorStory } from '.';
import StoryBookThemeProvider from '../StoryBookThemeProvider';

export default {
  argTypes: {
  },
  component: ColorStory,
  title: 'Colors'
} as ComponentMeta<typeof ColorStory>;

const TemplateColorStory: ComponentStory<typeof ColorStory> = (args) => (
  <StoryBookThemeProvider themeMode={args.themeMode || ThemeMode.light}>
    <Paper sx={{
      padding: 2
    }}>
    <ColorStory {...args} />
    </Paper>
  </StoryBookThemeProvider>

);

export const primaryLight = (): JSX.Element => (
  <TemplateColorStory paletteKey="primary" title="primary" themeMode={ThemeMode.light} />
);

export const secondaryLight = (): JSX.Element => (
  <TemplateColorStory paletteKey="secondary" title="secondary" themeMode={ThemeMode.light}/>
);

export const statusLight = (): JSX.Element => (
  <TemplateColorStory isGrouped title="Status" themeMode={ThemeMode.light}/>
);

export const textLight = (): JSX.Element => (
  <TemplateColorStory isText paletteKey="text" title="Text" themeMode={ThemeMode.light}/>
);

export const backgroundLight = (): JSX.Element => (
  <TemplateColorStory paletteKey="background" title="Background" themeMode={ThemeMode.light}/>
);

export const actionLight = (): JSX.Element => (
  <TemplateColorStory paletteKey="action" title="Action" themeMode={ThemeMode.light}/>
);

export const primaryDark = (): JSX.Element => (
  <TemplateColorStory paletteKey="primary" title="primary" themeMode={ThemeMode.dark} />
);

export const secondaryDark = (): JSX.Element => (
  <TemplateColorStory paletteKey="secondary" title="secondary" themeMode={ThemeMode.dark}/>
);

export const statusDark = (): JSX.Element => (
  <TemplateColorStory isGrouped title="Status" themeMode={ThemeMode.dark}/>
);

export const textDark = (): JSX.Element => (
  <TemplateColorStory isText paletteKey="text" title="Text" themeMode={ThemeMode.dark}/>
);

export const backgroundDark = (): JSX.Element => (
  <TemplateColorStory paletteKey="background" title="Background" themeMode={ThemeMode.dark}/>
);

export const actionDark = (): JSX.Element => (
  <TemplateColorStory paletteKey="action" title="Action" themeMode={ThemeMode.dark}/>
);