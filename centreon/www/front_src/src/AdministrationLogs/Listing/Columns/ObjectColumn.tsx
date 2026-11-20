import { Link as MuiLink, Typography } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import type { ActivityLog } from '../../models';
import { useSelectedTarget } from '../../useSelectedTarget';
import { useTargetParentLabel } from '../../useTargetParentLabel';

const ObjectColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const { target } = row as unknown as ActivityLog;
  const { select } = useSelectedTarget();
  const getParentLabel = useTargetParentLabel();

  const parentLabel = getParentLabel(target);

  return (
    <div className="flex min-w-0 items-baseline gap-2">
      <MuiLink
        className="truncate text-left"
        component="button"
        onClick={(event) => {
          // The row click opens the log detail, the name opens the history
          // of the object
          event.stopPropagation();
          select(target);
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
