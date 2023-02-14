import DuplicateDialog from '.';

export default { title: 'Dialog/Duplicate' };

export const normal = (): JSX.Element => (
  <DuplicateDialog
    open
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
  />
);

export const confirmDisabled = (): JSX.Element => (
  <DuplicateDialog
    confirmDisabled
    open
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
  />
);
