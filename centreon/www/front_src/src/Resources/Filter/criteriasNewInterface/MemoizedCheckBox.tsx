import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '@centreon/ui';

import { CheckBoxWrapper } from './CheckBoxWrapper';
import { MemoizedChild } from './model';
import { findData } from './utils';

const MemoizedCheckBox = ({
  basicData,
  changeCriteria,
  title,
  filterName
}: MemoizedChild): JSX.Element => {
  const { t } = useTranslation();

  return useMemoComponent({
    Component: (
      <CheckBoxWrapper
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName as string}
        title={t(title as string)}
      />
    ),
    memoProps: [
      findData({ data: basicData, filterName: filterName as string })?.value
    ]
  });
};

export default MemoizedCheckBox;
