import * as React from 'react';

import { makeStyles } from '@material-ui/core';

import MenuLoader from '.';

export default { title: 'Menu Skeleton' };

const useStyles = makeStyles((theme) => ({
  container: {
    backgroundColor: theme.palette.primary.main,
  },
}));

interface Props {
  width?: number;
}

const MenuLoaderStory = ({ width }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.container}>
      <MenuLoader animate={false} width={width} />
    </div>
  );
};

export const menuLoader = (): JSX.Element => <MenuLoaderStory />;

export const menuLoaderWithCustomWidth = (): JSX.Element => (
  <MenuLoaderStory width={40} />
);
