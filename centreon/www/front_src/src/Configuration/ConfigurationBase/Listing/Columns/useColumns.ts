import { Column, ColumnType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { labelActions, labelEnableDisable } from '../../translatedLabels';
import { Actions } from './Actions';
import Status from './Status/Status';

interface Props {
  staticColumns: Array<Column>;
}

const useColumns = (): Props => {
  const { t } = useTranslation();

  const staticColumns = [
    {
      Component: Actions,
      clickable: true,
      disablePadding: false,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    },

    {
      Component: Status,
      clickable: true,
      id: 'is_activated',
      label: t(labelEnableDisable),
      sortable: true,
      sortField: 'is_activated',
      type: ColumnType.component,
      width: 'max-content'
    }
  ];

  return { staticColumns };
};

export default useColumns;
