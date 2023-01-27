import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  title: {
    marginBottom: theme.spacing(2)
  }
}));

interface Props {
  title: string;
}

const FormTitle = ({ title }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Typography className={classes.title} variant="h4">
      {title}
    </Typography>
  );
};

export default FormTitle;
