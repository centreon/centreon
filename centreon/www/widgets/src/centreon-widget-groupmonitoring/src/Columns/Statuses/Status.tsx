import { Box, Link, Typography, useTheme } from '@mui/material';

import { SeverityCode, getStatusColors } from '@centreon/ui';

import { useStatusesColumnStyles } from '../Columns.styles';
import { getResourcesUrl } from '../../../../utils';
import { SeverityStatus } from '../../../../models';

interface Props {
  count: number;
  groupName: string;
  groupType: string;
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
  resourceType
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

  return (
    <Link
      className={classes.status}
      color="inherit"
      component="a"
      href={url}
      target="_blank"
      underline="none"
    >
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
      <Typography>({count})</Typography>
    </Link>
  );
};

export default Status;
