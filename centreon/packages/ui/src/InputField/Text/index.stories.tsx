import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import TextField from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  root: {
    background: theme.palette.background.paper,
    borderRadius: theme.spacing(0.5),
    width: theme.spacing(25)
  }
}));

export default { title: 'InputField/Text' };

export const withLabelAndHelperText = (): JSX.Element => (
  <TextField helperText="choose a name for current object" label="name" />
);

export const withPlaceholderOnly = (): JSX.Element => (
  <TextField placeholder="name" />
);

export const withError = (): JSX.Element => (
  <TextField error="Wrong name" label="name" />
);

export const fullWidth = (): JSX.Element => (
  <TextField fullWidth label="full width" />
);

export const compact = (): JSX.Element => (
  <TextField placeholder="Compact" size="compact" />
);

export const small = (): JSX.Element => (
  <TextField placeholder="Small" size="small" />
);

export const medium = (): JSX.Element => (
  <TextField placeholder="Medium" size="medium" />
);

export const large = (): JSX.Element => (
  <TextField placeholder="Large" size="large" />
);

export const transparent = (): JSX.Element => (
  <TextField transparent placeholder="Transparent" />
);

const CustomTextField = (): JSX.Element => {
  const { classes } = useStyles();

  return <TextField className={classes.root} label="custom input" />;
};

export const customTextField = (): JSX.Element => <CustomTextField />;
