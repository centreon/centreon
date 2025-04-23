import { ComponentMeta } from '@storybook/react';
import { Layout } from 'react-grid-layout';

import { Typography } from '@mui/material';

import FluidTypography from '../Typography/FluidTypography';

import { DashboardLayout } from '.';

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
  header?: JSX.Element;
  layout?: Array<CustomLayout>;
}

const Header = (): JSX.Element => (
  <Typography variant="body2">The title</Typography>
);

const DashboardTemplate = ({
  header,
  layout = dashboardLayout
}: DashboardTemplateProps): JSX.Element => (
  <DashboardLayout.Layout<CustomLayout> layout={layout}>
    {layout.map(({ i, content, shouldUseFluidTypography }) => (
      <DashboardLayout.Item header={header} key={i}>
        {shouldUseFluidTypography ? (
          <FluidTypography text={content} />
        ) : (
          <Typography>{content}</Typography>
        )}
      </DashboardLayout.Item>
    ))}
  </DashboardLayout.Layout>
);

export default {
  argTypes: {},
  component: DashboardTemplate,
  title: 'Dashboard'
} as ComponentMeta<typeof DashboardTemplate>;

export const normal = DashboardTemplate.bind({});

export const withManyPanels = DashboardTemplate.bind({});
withManyPanels.args = {
  layout: generateLayout(100)
};

export const withItemHeader = DashboardTemplate.bind({});
withItemHeader.args = {
  header: <Header />
};
