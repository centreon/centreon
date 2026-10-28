import type { InputProps } from '@centreon/ui';

// Applied to every input, grid columns included, so none is left editable
// without `configuration_service_write`. The input's own rule, such as a field
// that waits for another one, is kept.
export const disableWithoutWriteAccess =
  (canEdit: boolean) =>
  <Input extends Omit<InputProps, 'group'>>(input: Input): Input => ({
    ...input,
    getDisabled: (values) => !canEdit || !!input.getDisabled?.(values),
    ...(input.grid && {
      grid: {
        ...input.grid,
        columns: input.grid.columns.map(disableWithoutWriteAccess(canEdit))
      }
    })
  });
