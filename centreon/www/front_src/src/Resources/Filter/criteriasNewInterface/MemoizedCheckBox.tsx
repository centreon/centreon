import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '@centreon/ui';

import { CheckBoxWrapper } from './CheckBoxWrapper';
import { MemoizedChild } from './model';
import { findData } from './utils';

const MemoizedCheckBox = ({
  data,
  changeCriteria,
  title,
  filterName
}: MemoizedChild): JSX.Element => {
  const { t } = useTranslation();

  return useMemoComponent({
    Component: (
      <CheckBoxWrapper
        changeCriteria={changeCriteria}
        data={data}
        filterName={filterName}
        title={t(title as string)}
      />
    ),
    memoProps: findData({ data, filterName })
  });
};

export default MemoizedCheckBox;
