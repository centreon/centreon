import { EllipsisTypography } from '@centreon/ui';

import { Link } from 'react-router';

import { getResourcesUrl, goToUrl } from '../../../utils';
import { RowProps } from '../models';
import { useStatusesColumnStyles } from './Columns.styles';

export const Name = ({
  row,
  groupType,
  isFromPreview
}: RowProps): JSX.Element => {
  const { classes } = useStatusesColumnStyles();
  const url = getResourcesUrl({
    allResources: [
      {
        resources: [
          {
            id: row?.id,
            name: row?.name
          }
        ],
        resourceType: groupType
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
          className={classes.link}
          color="inherit"
          onClick={goToUrl(url)}
          rel="noopener noreferrer"
          target="_blank"
          to={url}
        >
          {row.name}
        </Link>
      )}
    </EllipsisTypography>
  );
};
