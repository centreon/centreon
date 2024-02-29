import numeral from 'numeral';
import { Link } from 'react-router-dom';

import { Box, Typography, useTheme } from '@mui/material';

import { SeverityCode, getStatusColors } from '@centreon/ui';

import { useStatusesColumnStyles } from '../Columns.styles';
import { getResourcesUrl, goToUrl } from '../../../../utils';
import { SeverityStatus } from '../../../../models';

interface Props {
  count: number;
  groupName: string;
  groupType: string;
  isFromPreview?: boolean;
  label: string;
  resourceType: string;
  severityCode: number;
}

const severityStatus = {
  [SeverityCode.High]: SeverityStatus.Problem,
  [SeverityCode.Medium]: SeverityStatus.Warning,
  [SeverityCode.OK]: SeverityStatus.Success,
  [SeverityCode.None]: SeverityStatus.Undefined,
  [SeverityCode.Pending]: SeverityStatus.Pending
};

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
  const { classes } = useStatusesColumnStyles();

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
    statuses: [severityStatus[severityCode]],
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
      className={classes.status}
      color="inherit"
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
