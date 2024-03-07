import { useTranslation } from 'react-i18next';

import { Box, useTheme } from '@mui/material';

import {
  FluidTypography,
  SeverityCode,
  formatMetricValue,
  getStatusColors
} from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { Resource, StatusDetail } from '../../../models';

import { useStatusGridCondensedStyles } from './StatusGridCondensed.styles';
import ResourcesTooltip from './Tooltip/ResourcesTooltip';

interface Props {
  count: StatusDetail;
  label: string;
  resourceType: string;
  resources: Array<Resource>;
  severityCode: SeverityCode;
  total?: number;
}

const StatusCard = ({
  count,
  label,
  severityCode,
  resourceType,
  resources,
  total
}: Props): JSX.Element => {
  const { classes, cx } = useStatusGridCondensedStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  return (
    <Tooltip
      hasArrow
      hasCaret
      classes={{
        tooltip: classes.tooltip
      }}
      followCursor={false}
      label={
        <ResourcesTooltip
          count={count.total}
          resourceType={resourceType}
          resources={resources}
          severityCode={severityCode}
          status={label}
          total={total}
        />
      }
      position="bottom"
    >
      <Box
        className={cx(classes.status, classes.statusCard)}
        data-count={count.total}
        data-label={label}
        sx={{ backgroundColor: getStatusColors({ severityCode, theme }) }}
      >
        <div className={classes.count}>
          <FluidTypography
            className={classes.countText}
            containerClassName={classes.countTextContainer}
            text={formatMetricValue({ unit: '', value: count.total || 0 })}
          />
        </div>
        <div className={classes.label}>
          <FluidTypography className={classes.labelText} text={t(label)} />
        </div>
      </Box>
    </Tooltip>
  );
};

export default StatusCard;
