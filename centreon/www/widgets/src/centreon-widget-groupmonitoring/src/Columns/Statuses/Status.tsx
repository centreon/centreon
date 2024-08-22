import numeral from 'numeral';
import { Link } from 'react-router-dom';

import { Box, Typography, useTheme } from '@mui/material';

import { getResourcesUrl, getStatusColors } from '@centreon/ui';

import {
  formatStatusFilter,
  goToUrl,
  severityStatusBySeverityCode
} from '../../../../utils';
import { useStatusesColumnStyles } from '../Columns.styles';

interface Props {
  count: number;
  groupName: string;
  groupType: string;
  isFromPreview?: boolean;
  label: string;
  resourceType: string;
  severityCode: number;
}

const Status = ({
  severityCode,
  label,
  count,
  groupType,
  groupName,
  resourceType,
  isFromPreview
}: Props): JSX.Element => {
  const theme = useTheme();
  const { classes, cx } = useStatusesColumnStyles();

  const url = getResourcesUrl({
    allResources: [
      {
        resourceType: groupType,
        resources: [
          {
            id: groupName,
            name: groupName
          }
        ]
      }
    ],
    isForOneResource: false,
    states: [],
    statuses: formatStatusFilter(severityStatusBySeverityCode[severityCode]),
    type: resourceType
  });

  const formattedCount = numeral(count).format('0.[00]a');

  const content = (
    <>
      <Box
        className={classes.statusLabelContainer}
        sx={{
          backgroundColor: getStatusColors({ severityCode, theme })
        }}
      >
        <Typography className={classes.statusLabel} variant="body2">
          <strong>{label.slice(0, 1).toLocaleUpperCase()}</strong>
        </Typography>
      </Box>
      <Typography className={classes.count}>({formattedCount})</Typography>
    </>
  );

  return isFromPreview ? (
    <div
      className={classes.status}
      data-count={count}
      data-group={groupName}
      data-status={label}
    >
      {content}
    </div>
  ) : (
    <Link
      className={cx(classes.status, classes.link)}
      data-count={count}
      data-group={groupName}
      data-status={label}
      rel="noopener noreferrer"
      target="_blank"
      to={url}
      onClick={goToUrl(url)}
    >
      {content}
    </Link>
  );
};

export default Status;
