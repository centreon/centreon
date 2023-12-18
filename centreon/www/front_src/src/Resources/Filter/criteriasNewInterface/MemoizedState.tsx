import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '@centreon/ui';

import { CheckBoxWrapper } from './CheckBoxWrapper';
import { BasicCriteria, MemoizedChild } from './model';
import { findData } from './utils';

const MemoizedState = ({
  basicData,
  changeCriteria
}: MemoizedChild): JSX.Element => {
  const { t } = useTranslation();

  return useMemoComponent({
    Component: (
      <CheckBoxWrapper
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={BasicCriteria.states}
        title={t('State')}
      />
    ),
    memoProps: [
      findData({ data: basicData, filterName: BasicCriteria.states })?.value
    ]
  });
};

export default MemoizedState;
