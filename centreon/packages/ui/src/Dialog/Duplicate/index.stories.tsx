import DuplicateDialog from '.';

export default { title: 'Dialog/Duplicate' };

export const normal = (): JSX.Element => (
  <DuplicateDialog
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
    open
  />
);

export const confirmDisabled = (): JSX.Element => (
  <DuplicateDialog
    confirmDisabled
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
    open
  />
);

export const withLimitNumber = (): JSX.Element => (
  <DuplicateDialog
    limit={10}
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
    open
  />
);
