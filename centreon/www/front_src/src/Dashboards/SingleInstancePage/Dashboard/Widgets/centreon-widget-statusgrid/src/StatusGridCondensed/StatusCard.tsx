import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';

import { Box, useTheme } from '@mui/material';

import {
  FluidTypography,
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
        <Box
          className={cx(classes.status, classes.statusCard)}
          sx={{ backgroundColor: getStatusColors({ severityCode, theme }) }}
        >
          <div className={classes.count}>
            <FluidTypography
              className={classes.countText}
              containerClassName={classes.countTextContainer}
              max="70px"
              min="40px"
              pref={5}
              text={formatMetricValue({ unit: '', value: count.total || 0 })}
            />
          </div>
          <div className={classes.label}>
            <FluidTypography
              className={classes.labelText}
              containerClassName={classes.labelTextContainer}
              max="20px"
              min="10px"
              text={t(label)}
            />
          </div>
        </Box>
      </Link>
    </Tooltip>
  );
};

export default StatusCard;
