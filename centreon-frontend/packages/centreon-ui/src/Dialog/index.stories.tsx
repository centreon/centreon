import React from 'react';

import { Typography } from '@material-ui/core';

import Dialog from '.';

export default { title: 'Dialog' };

interface Props {
  children: React.ReactNode;
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
