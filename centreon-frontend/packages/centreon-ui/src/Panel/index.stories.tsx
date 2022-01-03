import * as React from 'react';

import { Typography } from '@mui/material';

import Panel from '.';

export default { title: 'Panel' };

const header = <Typography>Header</Typography>;
const tab = <Typography>Tab</Typography>;

const Story = (props): JSX.Element => {
  return (
    <div
      style={{ display: 'flex', flexDirection: 'row-reverse', height: '100vh' }}
    >
      <Panel header={header} selectedTab={tab} {...props} />
    </div>
  );
};

export const normal = (): JSX.Element => <Story />;
