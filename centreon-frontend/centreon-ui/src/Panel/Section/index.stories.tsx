import * as React from 'react';

import { Typography } from '@material-ui/core';

import Panel from '.';

export default { title: 'Panel/Section' };

interface Props {
  sections;
  secondaryPanel?;
  loading?;
}

const PanelWithHeader = ({
  sections,
  secondaryPanel = undefined,
  loading = false,
}: Props): JSX.Element => (
  <div style={{ height: '100vh' }}>
    <Panel
      header={<Typography>Header</Typography>}
      sections={sections}
      loading={loading}
      secondaryPanel={secondaryPanel}
      onClose={() => undefined}
    />
  </div>
);

const sections = [
  {
    expandable: true,
    id: 'first section',
    title: 'First section',
    section: <Typography>First section</Typography>,
  },
  {
    expandable: true,
    id: 'second section',
    title: 'Second section',
    section: <Typography>Second section</Typography>,
  },
  {
    expandable: true,
    id: 'third section',
    title: 'Third section',
    section: <Typography>Third section</Typography>,
  },
];

export const normal = (): JSX.Element => (
  <PanelWithHeader sections={sections} />
);

export const withLoading = (): JSX.Element => (
  <PanelWithHeader loading sections={[]} />
);

export const withSecondaryPanel = (): JSX.Element => (
  <PanelWithHeader
    sections={sections}
    secondaryPanel={<Typography variant="h6">Secondary Panel</Typography>}
  />
);
