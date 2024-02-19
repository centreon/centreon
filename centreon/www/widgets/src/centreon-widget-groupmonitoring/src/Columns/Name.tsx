import { Link } from '@mui/material';

import { RowProps } from '../models';
import { getResourcesUrl } from '../../../utils';

export const Name = ({ row, resourceType }: RowProps): JSX.Element => {
  const url = getResourcesUrl({
    allResources: [
      {
        resourceType,
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
    <Link
      color="inherit"
      component="a"
      href={url}
      target="_blank"
      underline="hover"
    >
      {row.name}
    </Link>
  );
};
