import { ReactElement } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Typography, Theme } from '@mui/material';

import Dialog from '.';

export default { title: 'Dialog' };

const useStyles = makeStyles()((theme: Theme) => ({
  actions: {
    borderTop: `.2px solid ${theme.palette.common.white}`
  },
  content: {
    color: theme.palette.common.white
  },
  paper: {
    background: theme.palette.pending.main
  },
  root: {
    background: theme.palette.primary.dark
  },
  title: {
    color: theme.palette.common.white
  }
}));

interface Props {
  children: ReactElement;
  confirmDisabled?: boolean;
  submitting?: boolean;
}
const Story = ({ children, ...props }: Props): JSX.Element => (
  <Dialog
    open
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
    {...props}
  >
    {children}
  </Dialog>
);

export const normal = (): JSX.Element => (
  <Story>
    <Typography>Dialog</Typography>
  </Story>
);

export const confirmDisabled = (): JSX.Element => (
  <Story confirmDisabled>
    <Typography>Dialog</Typography>
  </Story>
);

export const confirmDisabledSubmitting = (): JSX.Element => (
  <Story confirmDisabled submitting>
    <Typography>Dialog</Typography>
  </Story>
);

const CustomDialog = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Dialog
      open
      className={classes.root}
      dialogActionsClassName={classes.actions}
      dialogContentClassName={classes.content}
      dialogPaperClassName={classes.paper}
      dialogTitleClassName={classes.title}
      onCancel={(): void => undefined}
      onConfirm={(): void => undefined}
    >
      Custom dialog
    </Dialog>
  );
};

export const customDialog = (): JSX.Element => <CustomDialog />;
