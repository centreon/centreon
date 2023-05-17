import { FC } from 'react';

import { makeStyles } from 'tss-react/mui';

import Layout from './Layout';
import Toolbar from './Toolbar';

const useStyles = makeStyles()((theme) => ({
  toolbarContainer: {
    display: 'flex',
    flexDirection: 'column',
    height: '90vh',
    padding: theme.spacing(0, 3),
    rowGap: theme.spacing(2)
  }
}));

const Dashboard: FC = () => {
  const { classes } = useStyles();

  return (
    <div className={classes.toolbarContainer}>
      <Toolbar />
      <Layout />
    </div>
  );
};

export default Dashboard;
