import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Box, Typography, useTheme } from '@mui/material';

import {
  ParentSize,
  SeverityCode,
  formatMetricValue,
  getStatusColors
} from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';
import { Tooltip } from '@centreon/ui/components';

import { Resource, StatusDetail } from '../../../models';
import {
  getBAsURL,
  getResourcesUrl,
  indicatorsURL,
  severityStatusBySeverityCode
} from '../../../utils';

import { useStatusGridCondensedStyles } from './StatusGridCondensed.styles';
import ResourcesTooltip from './Tooltip/ResourcesTooltip';

interface Props {
  count: StatusDetail;
  isBAResourceType: boolean;
  isBVResourceType: boolean;
  label: string;
  resourceType: string;
  resources: Array<Resource>;
  severityCode: SeverityCode;
  total?: number;
}

const computeCountTextSize = ({ width, height }): number => {
  const isHeight = height < width;

  const sizeToBaseOn = Math.min(height, width);

  if (width <= 80) {
    const scaleFactor = isHeight ? 2.5 : 3.3;

    return sizeToBaseOn / scaleFactor;
  }

  return sizeToBaseOn / 4.2;
};

const StatusCard = ({
  count,
  label,
  severityCode,
  resourceType,
  resources,
  total,
  isBVResourceType,
  isBAResourceType
}: Props): JSX.Element => {
  const { classes, cx } = useStatusGridCondensedStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const getUrl = (): string => {
    if (isBVResourceType) {
      return getBAsURL(severityCode);
    }
    if (isBAResourceType) {
      return indicatorsURL;
    }

    return getResourcesUrl({
      allResources: resources,
      isForOneResource: false,
      states: [],
      statuses: [severityStatusBySeverityCode[severityCode]],
      type: resourceType
    });
  };

  return (
    <Tooltip
      hasArrow
      hasCaret
      classes={{
        tooltip: classes.tooltip
      }}
      disableFocusListener={isOnPublicPage}
      disableHoverListener={isOnPublicPage}
      disableTouchListener={isOnPublicPage}
      followCursor={false}
      label={
        <ResourcesTooltip
          count={count.total}
          isBAResourceType={isBAResourceType}
          isBVResourceType={isBVResourceType}
          resourceType={resourceType}
          resources={resources}
          severityCode={severityCode}
          status={label}
          total={total}
        />
      }
      position="bottom"
    >
      <Link
        className={classes.link}
        data-count={count}
        data-label={label}
        rel="noopener noreferrer"
        target="_blank"
        to={getUrl()}
      >
        <ParentSize>
          {({ width, height }) => (
            <Box
              className={cx(classes.status, classes.statusCard)}
              sx={{ backgroundColor: getStatusColors({ severityCode, theme }) }}
            >
              <div className={classes.countParentSize}>
                <Typography
                  className={classes.countText}
                  sx={{
                    fontSize: `${computeCountTextSize({ width, height })}px`
                  }}
                >
                  {formatMetricValue({ unit: '', value: count.total || 0 })}
                </Typography>
              </div>
              {width > 80 && (
                <div className={cx(classes.countParentSize, classes.label)}>
                  <Typography
                    className={classes.countText}
                    sx={{ fontSize: `${Math.min(height, width) / 6.2}px` }}
                  >
                    {t(label)}
                  </Typography>
                </div>
              )}
            </Box>
          )}
        </ParentSize>
      </Link>
    </Tooltip>
  );
};

export default StatusCard;
