import DuplicateDialog from '.';

export default { title: 'Dialog/Duplicate' };

export const normal = (): JSX.Element => (
  <DuplicateDialog
    open
    onCancel={(): void => undefined}
    onConfirm={(): void => undefined}
  />
);
