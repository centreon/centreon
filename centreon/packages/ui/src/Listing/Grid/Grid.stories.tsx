import { Meta, StoryObj } from '@storybook/react';

import Grid from './Grid';
import GridItem from './Item/GridItem';

const meta: Meta<typeof Grid> = {
  component: Grid
};

export default meta;
type Story = StoryObj<typeof Grid>;

export const Default: Story = {
  args: {
    children: (
      <>
        {[...Array(5)].map((_, i) => (
          <GridItem
            description={`Item description ${i}`}
            key={`k-${i}`} // eslint-disable-line react/no-array-index-key
            title={`Item ${i}`}
          />
        ))}
      </>
    )
  }
};

export const withFixedHeightContainer: Story = {
  args: { ...Default.args },
  render: (args) => (
    <div style={{ height: '400px' }}>
      <Grid {...args} />
    </div>
  )
};
