import * as React from 'react';

import { makeStyles } from '@material-ui/styles';

const useStyles = makeStyles(() => ({
  container: {
    display: 'grid',
    gridTemplateRows: '1fr',
    gridTemplateColumns: '1fr auto',
  },
  content: {
    gridArea: (panelDynamicAndOpen) =>
      panelDynamicAndOpen ? '1 / 1 / 1 / 1' : '1 / 1 / 1 / span 2',
  },
  panel: {
    gridArea: '1 / 2',
    zIndex: 4,
  },
}));

interface Props {
  children: JSX.Element;
  panel?: JSX.Element;
  open?: boolean;
  fixed?: boolean;
}

const WithPanel = ({
  children,
  panel,
  open,
  fixed = false,
}: Props): JSX.Element => {
  const panelDynamicAndOpen = !!fixed && open;
  const classes = useStyles(panelDynamicAndOpen);

  return (
    <div className={classes.container}>
      <div className={classes.content}>{children}</div>
      {open && <div className={classes.panel}>{panel}</div>}
    </div>
  );
};

export default WithPanel;
