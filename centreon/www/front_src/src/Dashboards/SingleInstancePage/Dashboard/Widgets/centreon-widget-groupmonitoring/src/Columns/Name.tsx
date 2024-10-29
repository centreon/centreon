import { Link } from 'react-router-dom';

import { EllipsisTypography } from '@centreon/ui';

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
          className={classes.link}
          color="inherit"
          rel="noopener noreferrer"
          target="_blank"
          to={url}
          onClick={goToUrl(url)}
        >
          {row.name}
        </Link>
      )}
    </EllipsisTypography>
  );
};
