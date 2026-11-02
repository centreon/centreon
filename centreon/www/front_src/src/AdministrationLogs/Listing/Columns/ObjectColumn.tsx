import { Link as MuiLink, Typography } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import type { ActivityLog } from '../../models';
import {
  labelLinkedResourceChanged,
  labelOnHost,
  labelOnHostGroup
} from '../../translatedLabels';
import { useSelectedTarget } from '../../useSelectedTarget';

const ObjectColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { target } = row as unknown as ActivityLog;
  const { select } = useSelectedTarget();

  const getParentLabel = (): string | null => {
    if (!equals(target.type, 'Service')) {
      return null;
    }

    if (!target.parent || isEmpty(target.parent.names)) {
      return t(labelLinkedResourceChanged);
    }

    return t(
      equals(target.parent.type, 'HostGroup') ? labelOnHostGroup : labelOnHost,
      { names: target.parent.names.join(', ') }
    );
  };

  const parentLabel = getParentLabel();

  return (
    <div className="flex min-w-0 items-baseline gap-2">
      <MuiLink
        className="truncate text-left"
        component="button"
        onClick={(event) => {
          // The row click expands the changes
          event.stopPropagation();
          select({ id: target.id, type: target.type });
        }}
        underline="hover"
      >
        {target.name}
      </MuiLink>
      {parentLabel && (
        <Typography
          className="truncate text-text-secondary"
          component="span"
          variant="caption"
        >
          {parentLabel}
        </Typography>
      )}
    </div>
  );
};

export default ObjectColumn;
