import { ComponentMeta, ComponentStory } from '@storybook/react';

import TypographyStory from './story.utils';

export default {
  argTypes: {},
  component: TypographyStory,
  title: 'Typography'
} as ComponentMeta<typeof TypographyStory>;

const TemplateTypographtStory: ComponentStory<typeof TypographyStory> = (
  args
) => <TypographyStory {...args} />;

export const typography = (): JSX.Element => (
  <TemplateTypographtStory text="Hello world" />
);
