import ExpandMoreIcon from '@mui/icons-material/ExpandMore';

import { type ComponentColumnProps, IconButton } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import type { ActivityLog } from '../../models';
import { labelHideChanges, labelShowChanges } from '../../translatedLabels';
import { useExpandedLogs } from '../../useExpandedLogs';

const ExpandColumn = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const { t } = useTranslation();
  const { isExpanded, toggle } = useExpandedLogs();

  const { id, hasChanges } = row as unknown as ActivityLog;

  if (!hasChanges) {
    return null;
  }

  const label = t(isExpanded(id) ? labelHideChanges : labelShowChanges);

  return (
    <IconButton
      ariaLabel={label}
      onClick={(event) => {
        // The row click toggles too
        event.stopPropagation();
        toggle(id);
      }}
      title={label}
    >
      <ExpandMoreIcon
        className={`transition-transform ${isExpanded(id) ? 'rotate-0' : '-rotate-90'}`}
        fontSize="small"
      />
    </IconButton>
  );
};

export default ExpandColumn;
