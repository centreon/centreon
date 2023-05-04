import { ComponentMeta } from '@storybook/react';
import { Layout } from 'react-grid-layout';

import { Typography } from '@mui/material';

import FluidTypography from '../Typography/FluidTypography';

import { DashboardLayout, DashboardItem } from '.';
import { map } from 'ramda';

interface CustomLayout extends Layout {
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

const generateLayout = (maxElements: number) => {
  return Array(maxElements).fill(0).map((_, i): CustomLayout => {
    return {
      x: (i * 3) % 12,
      y: Math.floor(i / 12),
      w: 3,
      h: 3,
      i: i.toString(),
      content: `${i}`,
      shouldUseFluidTypography: false
    };
  });
}

interface DashboardTemplateProps {
  displayGrid?: boolean;
  layout?: Array<CustomLayout>;
}

const DashboardTemplate = ({
  displayGrid,
  layout = dashboardLayout
}: DashboardTemplateProps): JSX.Element => (
    <DashboardLayout<CustomLayout>
      displayGrid={displayGrid}
      layout={layout}
    >
      {layout.map(({ i, content, shouldUseFluidTypography }) => (
        <DashboardItem key={i}>
          {shouldUseFluidTypography ? (
            <FluidTypography text={content} />
          ) : (
            <Typography>{content}</Typography>
          )}
        </DashboardItem>
      ))}
    </DashboardLayout>
  );

export default {
  argTypes: {},
  component: DashboardTemplate,
  title: 'Dashboard'
} as ComponentMeta<typeof DashboardTemplate>;

export const normal = DashboardTemplate.bind({});

export const withGridDisplayed = DashboardTemplate.bind({});
withGridDisplayed.args = {
  displayGrid: true
};

export const withManyPanels = DashboardTemplate.bind({});
withManyPanels.args = {
  layout: generateLayout(1000)
}
