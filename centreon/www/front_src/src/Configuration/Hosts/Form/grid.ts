import { type InputPropsWithoutGroup, InputType } from '@centreon/ui';

// The panel opens at 720, 900 or 1400px, its content about 40px narrower: the
// narrow design already pairs fields from 600px, the wide one from 1100px.
export const stackedColumns = 'grid-cols-1';
export const twoColumns = 'grid-cols-2 gap-x-8';
export const twoColumnsFromNarrow = 'grid-cols-1 gap-x-8 @[600px]:grid-cols-2';
export const twoColumnsFromWide = 'grid-cols-1 gap-x-8 @[1100px]:grid-cols-2';
export const threeColumnsFromWide = 'grid-cols-1 gap-x-8 @[1100px]:grid-cols-3';

// Inputs kept together under an optional title. The label only keys the grid
// among its siblings: it renders nothing.
export const getBlock = ({
  className = stackedColumns,
  columns,
  name,
  title
}: {
  className?: string;
  columns: Array<InputPropsWithoutGroup>;
  name: string;
  title?: string;
}): InputPropsWithoutGroup => ({
  ...(title && { additionalLabel: title }),
  fieldName: name,
  grid: { className, columns },
  label: `host-form-${name}`,
  type: InputType.Grid
});
