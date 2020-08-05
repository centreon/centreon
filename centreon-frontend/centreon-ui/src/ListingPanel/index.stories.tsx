import * as React from 'react';

import { Typography } from '@material-ui/core';

import ListingPanel from '.';

export default { title: 'Listing Panel' };

export const normal = (): JSX.Element => (
  <ListingPanel
    active
    Header={<Typography>Header</Typography>}
    Sections={[
      {
        expandable: true,
        id: 'first section',
        title: 'First section',
        Section: <Typography>First section</Typography>,
      },
      {
        expandable: true,
        id: 'second section',
        title: 'Second section',
        Section: <Typography>Second section</Typography>,
      },
      {
        expandable: true,
        id: 'third section',
        title: 'Third section',
        Section: <Typography>Third section</Typography>,
      },
    ]}
  />
);

export const normalWithLoadingState = (): JSX.Element => (
  <ListingPanel
    active
    Header={<Typography>Header</Typography>}
    loading
    Sections={[]}
  />
);
