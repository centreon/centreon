import { Divider } from '@mui/material';

import { useStyles } from '../criterias.style';

interface BasicFilter {
  poller: JSX.Element;
  sections: JSX.Element;
  state: JSX.Element;
}

const BasicFilter = ({ sections, poller, state }: BasicFilter): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.containerFilter}>
      {sections}
      {poller}
      <Divider className={classes.divider} />
      {state}
    </div>
  );
};

export default BasicFilter;
