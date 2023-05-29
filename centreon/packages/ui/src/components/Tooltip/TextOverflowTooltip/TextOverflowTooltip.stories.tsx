import { useEffect, useState } from 'react';

import { Meta, StoryObj } from '@storybook/react';
import { within } from '@storybook/testing-library';

// import { expect } from '@storybook/jest';
import { TextOverflowTooltip } from './TextOverflowTooltip';

const meta: Meta<typeof TextOverflowTooltip> = {
  args: {},
  component: TextOverflowTooltip,
  parameters: {
    layout: 'centered'
  }
};

export default meta;
type Story = StoryObj<typeof TextOverflowTooltip>;

export const Default: Story = {
  args: {
    // isOpen: true,
    label:
      'Qui deserunt pariatur quis. Duis nisi velit culpa labore ipsum reprehenderit sunt laborum anim sint quis magna consequat amet. Voluptate tempor nostrud eiusmod enim qui reprehenderit.'
  },
  play: async ({ args, canvasElement, step }) => {
    const canvas = within(canvasElement);
    const paragraph = canvas.getByTestId('paragraph');

    // FIXME
    // await userEvent.hover(paragraph);
    // await new Promise((resolve) => setTimeout(resolve, 500));
    // const tooltip = await within(
    //   canvasElement.parentNode as HTMLElement
    // ).getByRole('tooltip');
    // await expect(tooltip).toBeDefined();
  },
  render: ({ label, children, ...args }) => {
    const [labelValue, setLabelValue] = useState(label);
    useEffect(() => setLabelValue(label), [label]);

    return (
      <TextOverflowTooltip {...args} data-testid="tooltip" label={labelValue}>
        <p
          data-testid="paragraph"
          style={{
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap',
            width: '40vw'
          }}
        >
          {labelValue}
        </p>
      </TextOverflowTooltip>
    );
  }
};

export const AsMultiLine: Story = {
  args: {
    ...Default.args
  },
  render: ({ label, children, ...args }) => {
    const [labelValue, setLabelValue] = useState(label);
    useEffect(() => setLabelValue(label), [label]);

    return (
      <TextOverflowTooltip {...args} label={labelValue}>
        <p
          style={{
            WebkitBoxOrient: 'vertical',
            WebkitLineClamp: '2',
            display: '-webkit-box',
            overflow: 'hidden',
            width: '40vw'
          }}
        >
          {labelValue}
        </p>
      </TextOverflowTooltip>
    );
  }
};
