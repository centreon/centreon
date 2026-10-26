import { type Column, ColumnType } from '@centreon/ui';
import { intervalLengthAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  labelDurationInMinutes,
  labelDurationInSeconds,
  labelHost,
  labelName,
  labelScheduling,
  labelTemplate
} from '../translatedLabels';
import { toDuration } from '../utils';
import Host from './Host';
import Name from './Name';
import Template from './Template';

interface Props {
  columns: Array<Column>;
}

// `getFormattedString` is mandatory: DataCell falls back to an empty string,
// not to `row[id]`.
const useColumns = (): Props => {
  const { t } = useTranslation();
  const intervalLength = useAtomValue(intervalLengthAtom);

  const formatInterval = (interval: unknown): string => {
    const duration = toDuration(
      interval as number | null | undefined,
      intervalLength
    );

    if (!duration) {
      return '-';
    }

    return t(
      duration.unit === 'min' ? labelDurationInMinutes : labelDurationInSeconds,
      { count: duration.count }
    );
  };

  const columns: Array<Column> = [
    {
      Component: Host,
      // The link navigates; the cell must not also open the service.
      clickable: true,
      disablePadding: false,
      id: 'host',
      label: t(labelHost),
      sortable: true,
      sortField: 'host.name',
      type: ColumnType.component
    },
    {
      Component: Name,
      disablePadding: false,
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.component
    },
    {
      disablePadding: false,
      getFormattedString: ({
        normalCheckInterval,
        retryCheckInterval
      }: Record<string, unknown>) =>
        `${formatInterval(normalCheckInterval)} / ${formatInterval(retryCheckInterval)}`,
      id: 'scheduling',
      label: t(labelScheduling),
      type: ColumnType.string
    },
    {
      Component: Template,
      clickable: true,
      disablePadding: false,
      id: 'template',
      label: t(labelTemplate),
      type: ColumnType.component
    }
  ];

  return { columns };
};

export default useColumns;
