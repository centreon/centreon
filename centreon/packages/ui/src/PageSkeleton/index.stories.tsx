import { ComponentMeta, ComponentStory } from '@storybook/react';

import PageSkeleton from '.';

export default {
  argTypes: {
    animate: { control: 'boolean' },
    displayHeaderAndNavigation: { control: 'boolean' }
  },
  component: PageSkeleton,
  title: 'Page Skeleton'
} as ComponentMeta<typeof PageSkeleton>;

const TemplatePageSkeleton: ComponentStory<typeof PageSkeleton> = (args) => (
  <PageSkeleton {...args} />
);

export const PlaygroundPageSkeleton = TemplatePageSkeleton.bind({});
PlaygroundPageSkeleton.args = {
  animate: true,
  displayHeaderAndNavigation: true
};

export const normal = (): JSX.Element => <PageSkeleton animate={false} />;

export const normalWidthHeaderAndNavigation = (): JSX.Element => (
  <PageSkeleton displayHeaderAndNavigation animate={false} />
);
