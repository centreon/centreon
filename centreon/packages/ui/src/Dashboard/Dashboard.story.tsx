import { Typography } from '@mui/material';

import { DashboardLayout } from '.';

const layout = [
  {
    content: 'Panel 1',
    h: 4,
    i: 'a',
    shouldUseFluidTypography: false,
    w: 6,
    x: 0,
    y: 0
  },
  { content: 'Panel 2', h: 3, i: 'b', minW: 2, w: 7, x: 1, y: 7 },
  { content: 'Panel 3', h: 7, i: 'c', w: 6, x: 6, y: 6 },
  { content: 'Panel 4', h: 3, i: 'd', minW: 2, w: 5, x: 4, y: 10 }
];

export const Default = (): JSX.Element => (
  <DashboardLayout.Layout
    layout={layout}
    // Typed as arrays: the children of a tag would count as a single one.
    children={layout.map(({ i, content }) => (
      <DashboardLayout.Item
        children={[<Typography key="content">{content}</Typography>]}
        header={<div>header</div>}
        id={i}
        key={i}
      />
    ))}
  />
);
