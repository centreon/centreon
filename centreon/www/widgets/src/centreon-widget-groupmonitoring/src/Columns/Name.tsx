import { Link } from '@mui/material';

import { EllipsisTypography } from '@centreon/ui';

import { RowProps } from '../models';
import { getResourcesUrl } from '../../../utils';
import { goToUrl } from '../utils';

export const Name = ({
  row,
  groupType,
  isFromPreview
}: RowProps): JSX.Element => {
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
      {isFromPreview ? (
        row.name
      ) : (
        <Link
          color="inherit"
          component="a"
          href={url}
          rel="noopener noreferrer"
          target="_blank"
          underline="hover"
          onClick={goToUrl(url)}
        >
          {row.name}
        </Link>
      )}
    </EllipsisTypography>
  );
};
