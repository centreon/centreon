import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '@centreon/ui';

import { labelPoller } from '../../../Header/Poller/translatedLabels';

import InputGroup from './basicFilter/InputGroup';
import { BasicCriteria, MemoizedChild } from './model';
import { findData } from './utils';

const MemoizedPoller = ({
  basicData,
  changeCriteria
}: MemoizedChild): JSX.Element => {
  const { t } = useTranslation();

  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={BasicCriteria.monitoringServers}
        label={t(labelPoller) as string}
      />
    ),
    memoProps: [
      findData({
        data: basicData,
        filterName: BasicCriteria.monitoringServers
      })?.value
    ]
  });
};
export default MemoizedPoller;
