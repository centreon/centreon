import { ComponentMeta, ComponentStory } from '@storybook/react';

import { ColorStory } from '.';

export default {
  argTypes: {
    paletteKey: { control: 'text' }
  },
  component: ColorStory,
  title: 'Colors'
} as ComponentMeta<typeof ColorStory>;

const TemplateColorStory: ComponentStory<typeof ColorStory> = (args) => (
  <ColorStory {...args} />
);

export const primary = (): JSX.Element => (
  <TemplateColorStory paletteKey="primary" title="primary" />
);

export const secondary = (): JSX.Element => (
  <TemplateColorStory paletteKey="secondary" title="secondary" />
);

export const status = (): JSX.Element => (
  <TemplateColorStory isGrouped title="Status" />
);

export const text = (): JSX.Element => (
  <TemplateColorStory isText paletteKey="text" title="Text" />
);

export const background = (): JSX.Element => (
  <TemplateColorStory paletteKey="background" title="Background" />
);

export const action = (): JSX.Element => (
  <TemplateColorStory paletteKey="action" title="Action" />
);
