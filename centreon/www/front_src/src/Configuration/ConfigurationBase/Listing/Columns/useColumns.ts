import { Column, ColumnType } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../atoms';
import { labelActions, labelEnableDisable } from '../../translatedLabels';
import { Actions } from './Actions';
import Status from './Status/Status';

interface Props {
  staticColumns: Array<Column>;
}

// Neither cell renders anything unless the module declares the matching action,
// so offering the column regardless means offering a blank one.
const useColumns = (): Props => {
  const { t } = useTranslation();

  const configuration = useAtomValue(configurationAtom);
  const actions = configuration?.actions;

  const actionsColumn = {
    Component: Actions,
    clickable: true,
    disablePadding: false,
    id: 'actions',
    label: t(labelActions),
    type: ColumnType.component
  };

  const statusColumn = {
    Component: Status,
    clickable: true,
    id: 'is_activated',
    label: t(labelEnableDisable),
    sortable: true,
    sortField: 'is_activated',
    type: ColumnType.component,
    width: 'max-content'
  };

  const hasRowActions =
    actions?.delete ||
    actions?.duplicate ||
    !isEmpty(actions?.rowActions ?? []);

  const staticColumns = [
    ...(hasRowActions ? [actionsColumn] : []),
    ...(actions?.enableDisable ? [statusColumn] : [])
  ];

  return { staticColumns };
};

export default useColumns;
