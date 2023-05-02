import { ComponentMeta, ComponentStory } from '@storybook/react';

import TypographtStory from './story.utils';

export default {
  argTypes: {},
  component: TypographtStory,
  title: 'Typography'
} as ComponentMeta<typeof TypographtStory>;

const TemplateTypographtStory: ComponentStory<typeof TypographtStory> = (
  args
) => <TypographtStory {...args} />;

export const typography = (): JSX.Element => (
  <TemplateTypographtStory text="Hello world" />
);
