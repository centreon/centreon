import { useTranslation } from 'react-i18next';

import { useMemoComponent } from '@centreon/ui';

import { labelMonitoringServer } from '../../translatedLabels';

import InputGroup from './basicFilter/InputGroup';
import { BasicCriteria, MemoizedChild } from './model';
import { findData } from './utils';

const MemoizedPoller = ({
  data,
  changeCriteria
}: Omit<MemoizedChild, 'filterName'>): JSX.Element => {
  const { t } = useTranslation();

  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={data}
        filterName={BasicCriteria.monitoringServers}
        label={t(labelMonitoringServer) as string}
      />
    ),
    memoProps: findData({
      data,
      filterName: BasicCriteria.monitoringServers
    })
  });
};
export default MemoizedPoller;
