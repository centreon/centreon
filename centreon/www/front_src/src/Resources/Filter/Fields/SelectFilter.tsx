// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import type { SelectEntry } from '@centreon/ui';
import { SelectField } from '@centreon/ui';

import memoizeComponent from '../../memoizedComponent';

interface Props {
  ariaLabel: string;
  onChange: (event) => void;
  options: Array<SelectEntry>;
  selectedOptionId: string | number;
}

const SelectFilter = ({
  options,
  selectedOptionId,
  onChange,
  ariaLabel
}: Props): JSX.Element => (
  <SelectField
    ariaLabel={ariaLabel}
    dataTestId="selectedFilter"
    onChange={onChange}
    options={options}
    selectedOptionId={selectedOptionId}
  />
);

const memoProps = ['options', 'selectedOptionId'];

const MemoizedSelectFilter = memoizeComponent<Props>({
  Component: SelectFilter,
  memoProps
});

export default MemoizedSelectFilter;
