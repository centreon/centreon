import { Typography } from '@mui/material';

import type { Meta, StoryObj } from '@storybook/react';
import { ReactElement } from 'react';
import type { LayoutItem } from 'react-grid-layout';

import FluidTypography from '../Typography/FluidTypography';
import { DashboardLayout } from '.';

interface CustomLayout extends LayoutItem {
  content: string;
  shouldUseFluidTypography: boolean;
}

const dashboardLayout: Array<CustomLayout> = [
  {
    content: 'Hello world',
    h: 4,
    i: 'a',
    shouldUseFluidTypography: false,
    w: 6,
    x: 0,
    y: 0
  },
  {
    content: 'This is a panel',
    h: 3,
    i: 'b',
    minW: 2,
    shouldUseFluidTypography: false,
    w: 7,
    x: 1,
    y: 7
  },
  {
    content: 'And the last panel with fluid typography',
    h: 7,
    i: 'c',
    shouldUseFluidTypography: true,
    w: 6,
    x: 6,
    y: 6
  }
];

const generateLayout = (maxElements: number): Array<CustomLayout> => {
  return Array(maxElements)
    .fill(0)
    .map((_, i): CustomLayout => {
      return {
        content: `${i}`,
        h: 3,
        i: i.toString(),
        shouldUseFluidTypography: false,
        w: 3,
        x: (i * 3) % 12,
        y: Math.floor(i / 12)
      };
    });
};

interface DashboardTemplateProps {
  header?: ReactElement;
  layout?: Array<CustomLayout>;
}

const Header = (): ReactElement => (
  <Typography variant="body2">The title</Typography>
);

const DashboardTemplate = ({
  header,
  layout = dashboardLayout
}: DashboardTemplateProps): ReactElement => (
  <DashboardLayout.Layout<CustomLayout> layout={layout}>
    {layout.map(({ i, content, shouldUseFluidTypography }) => (
      <DashboardLayout.Item header={header} id={i} key={i}>
        {shouldUseFluidTypography ? (
          <FluidTypography text={content} />
        ) : (
          <Typography>{content}</Typography>
        )}
      </DashboardLayout.Item>
    ))}
  </DashboardLayout.Layout>
);

const meta: Meta<typeof DashboardTemplate> = {
  component: DashboardTemplate,
  title: 'Dashboard'
};

export default meta;
type Story = StoryObj<typeof DashboardTemplate>;

export const normal: Story = {
  args: {}
};

export const withManyPanels: Story = {
  args: {
    layout: generateLayout(100)
  }
};

export const withItemHeader: Story = {
  args: {
    header: <Header />
  }
};
