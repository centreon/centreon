import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../atoms';
import { labelActions, labelEnableDisable } from '../../translatedLabels';
import { Actions } from './Actions';
import Status from './Status/Status';

interface Props {
  staticColumns: Array<Column>;
}

const useColumns = (): Props => {
  const { t } = useTranslation();

  const configuration = useAtomValue(configurationAtom);
  const actions = configuration?.actions;

  const staticColumns = [
    {
      Component: Actions,
      clickable: true,
      disablePadding: false,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    },
    ...(actions?.enableDisable
      ? [
          {
            Component: Status,
            clickable: true,
            id: 'is_activated',
            sortField: 'is_activated',
            label: t(labelEnableDisable),
            type: ColumnType.component,
            width: 'max-content',
            sortable: true
          }
        ]
      : [])
  ];

  return { staticColumns };
};

export default useColumns;
