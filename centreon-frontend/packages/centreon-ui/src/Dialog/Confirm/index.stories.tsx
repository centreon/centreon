import ConfirmDialog from '.';

export default { title: 'Dialog/Confirm' };

export const normal = (): JSX.Element => (
  <ConfirmDialog
    open
    labelMessage="Your progress will not be saved."
    labelTitle="Do you want to confirm action ?"
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
  />
);
