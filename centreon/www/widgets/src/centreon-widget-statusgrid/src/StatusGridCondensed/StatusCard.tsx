import { useTranslation } from 'react-i18next';

import { Box, useTheme } from '@mui/material';

import {
  FluidTypography,
  SeverityCode,
  formatMetricValue,
  getStatusColors
} from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { StatusDetail } from '../../../models';

import { useStatusGridCondensedStyles } from './StatusGridCondensed.styles';

interface Props {
  count: StatusDetail;
  label: string;
  severityCode: SeverityCode;
}

const StatusCard = ({ count, label, severityCode }: Props): JSX.Element => {
  const { classes, cx } = useStatusGridCondensedStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  return (
    <Tooltip followCursor={false} position="bottom">
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
