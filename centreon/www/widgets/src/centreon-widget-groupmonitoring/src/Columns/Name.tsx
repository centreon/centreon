import { Link } from '@mui/material';

import { EllipsisTypography } from '@centreon/ui';

import { RowProps } from '../models';
import { getResourcesUrl } from '../../../utils';

export const Name = ({ row, groupType }: RowProps): JSX.Element => {
  const url = getResourcesUrl({
    allResources: [
      {
        resourceType: groupType,
        resources: [
          {
            id: row?.id,
            name: row?.name
          }
        ]
      }
    ],
    isForOneResource: false,
    states: [],
    statuses: [],
    type: 'all'
  });

  return (
    <EllipsisTypography>
      <Link
        color="inherit"
        component="a"
        href={url}
        target="_blank"
        underline="hover"
      >
        {row.name}
      </Link>
    </EllipsisTypography>
  );
};
