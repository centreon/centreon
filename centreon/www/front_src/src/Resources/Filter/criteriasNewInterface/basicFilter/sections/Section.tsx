import { Divider } from '@mui/material';

import { useStyles } from './sections.style';

interface Section {
  inputGroup: JSX.Element;
  selectInput: JSX.Element;
  status: JSX.Element;
}

const Section = ({ status, inputGroup, selectInput }: Section): JSX.Element => {
  const { classes } = useStyles();

  return (
    <>
      {selectInput}
      <Divider className={classes.dividerInputs} />
      {status}
      <Divider className={classes.dividerInputs} />
      {inputGroup}
    </>
  );
};

export default Section;
