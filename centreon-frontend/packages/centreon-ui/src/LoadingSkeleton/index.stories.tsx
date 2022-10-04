import { ComponentMeta, ComponentStory } from '@storybook/react';

import LoadingSkeleton from '.';

export default {
  argTypes: {
    height: { control: 'number' },
    width: { control: 'number' },
  },
  component: LoadingSkeleton,
  title: 'Loading Skeleton',
} as ComponentMeta<typeof LoadingSkeleton>;

const TemplateLoadingSkeleton: ComponentStory<typeof LoadingSkeleton> = (
  args,
) => <LoadingSkeleton {...args} />;

export const PlaygroundLoadingSkeleton = TemplateLoadingSkeleton.bind({});
PlaygroundLoadingSkeleton.args = { height: 50, width: 400 };

export const normal = (): JSX.Element => (
  <LoadingSkeleton height={50} width={400} />
);
