import { type ComponentColumnProps, StatusChip } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import type { ActivityLog } from '../../models';
import { labelFilterBy } from '../../translatedLabels';
import { useAddFilter } from '../../useAddFilter';
import { actionLabels, actionSeverityCodes } from '../../utils';

const ActionColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const addFilter = useAddFilter();

  const { action } = row as unknown as ActivityLog;
  const label = t(actionLabels[action]);

  return (
    // Same size as the status chips of the Resource Status listing
    <StatusChip
      className="h-5 w-full min-w-20 font-bold"
      clickable
      label={label}
      onClick={(event) => {
        // The row click expands the changes
        event.stopPropagation();
        addFilter('actions', { id: action, name: label });
      }}
      severityCode={actionSeverityCodes[action]}
      title={t(labelFilterBy, { value: label })}
    />
  );
};

export default ActionColumn;
