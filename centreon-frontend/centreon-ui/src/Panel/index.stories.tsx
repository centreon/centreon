import * as React from 'react';

import { Typography } from '@material-ui/core';

import Panel from '.';

export default { title: 'Panel' };

const header = <Typography>Header</Typography>;
const tab = <Typography>Tab</Typography>;

const Story = (props): JSX.Element => {
  return (
    <div
      style={{ height: '100vh', display: 'flex', flexDirection: 'row-reverse' }}
    >
      <Panel header={header} selectedTab={tab} {...props} />
    </div>
  );
};

export const normal = (): JSX.Element => <Story />;

const StoryResizable = (): JSX.Element => {
  const [width, setWidth] = React.useState(550);

  return <Story onResize={setWidth} width={width} />;
};

export const resizable = (): JSX.Element => <StoryResizable />;
