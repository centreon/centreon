import { T, always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';

import DvrIcon from '@mui/icons-material/Dvr';
import { Box, CardActionArea, Typography } from '@mui/material';

import { EllipsisTypography, HostIcon, ServiceIcon } from '@centreon/ui';

import { Resource } from '../../../models';
import { getResourcesUrl } from '../../../utils';

import {
  AnomalyDetectionIcon,
  BAIcon,
  BooleanRuleIcon,
  MetaServiceIcon
} from './Icons';
import State from './State';
import { useTileStyles } from './StatusGrid.styles';
import { IndicatorType, ResourceData } from './models';
import { labelSeeMore } from './translatedLabels';
import { getLink } from './utils';

interface Props {
  data: ResourceData | null;
  isBAResourceType: boolean;
  isSmallestSize: boolean;
  resources: Array<Resource>;
  statuses: Array<string>;
  type: string;
}

export const router = {
  useNavigate
};
const DefaultIcon = (): JSX.Element => <div />;

const Tile = ({
  isSmallestSize,
  data,
  type,
  statuses,
  resources,
  isBAResourceType
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useTileStyles();

  const Icon = cond([
    [equals(IndicatorType.BusinessActivity), always(BAIcon)],
    [equals(IndicatorType.BooleanRule), always(BooleanRuleIcon)],
    [equals(IndicatorType.AnomalyDetection), always(AnomalyDetectionIcon)],
    [equals(IndicatorType.MetaService), always(MetaServiceIcon)],
    [equals(IndicatorType.Service), always(ServiceIcon)],
    [equals(IndicatorType.Host), always(HostIcon)],
    [T, always(DefaultIcon)]
  ])(type);

  const getLinkToResourceStatus = ({ isForOneResource }): string => {
    if (isBAResourceType) {
      const url = getLink({
        hostId: data?.parentId,
        id: data?.resourceId || data?.id,
        name: data?.name,
        type
      });

      return url;
    }

    return getResourcesUrl({
      allResources: resources,
      isForOneResource,
      resource: data,
      states: [],
      statuses,
      type
    });
  };

  if (isNil(data)) {
    return (
      <Link
        aria-label={t(labelSeeMore)}
        className={classes.link}
        target="_blank"
        to={getLinkToResourceStatus({ isForOneResource: false })}
      >
        <CardActionArea
          className={classes.seeMoreContainer}
          onClick={() => undefined}
        >
          <DvrIcon
            color="primary"
            fontSize={isSmallestSize ? 'medium' : 'large'}
          />
          {!isSmallestSize && <Typography>{t(labelSeeMore)}</Typography>}
        </CardActionArea>
      </Link>
    );
  }

  const displayStatusTile = data.is_acknowledged || data.is_in_downtime;

  if (isSmallestSize && !isNil(data)) {
    return (
      <Link
        className={classes.link}
        data-testid={`link to ${data?.name}`}
        target="_blank"
        to={getLinkToResourceStatus({ isForOneResource: true })}
      >
        <Box className={classes.container}>
          {displayStatusTile && (
            <State
              isAcknowledged={data.is_acknowledged}
              isCompact={isSmallestSize}
              isInDowntime={data.is_in_downtime}
              type={type}
            />
          )}
        </Box>
      </Link>
    );
  }

  return (
    <Box className={classes.container} data-status={data.statusName}>
      <Link
        className={classes.link}
        data-testid={`link to ${data?.name}`}
        target="_blank"
        to={getLinkToResourceStatus({ isForOneResource: true })}
      >
        {displayStatusTile && (
          <State
            isAcknowledged={data.is_acknowledged}
            isCompact={isSmallestSize}
            isInDowntime={data.is_in_downtime}
          />
        )}
        <div className={classes.resourceTypeIcon}>
          <Icon className={classes.icon} />
        </div>
        <EllipsisTypography className={classes.resourceName} textAlign="center">
          {data.name}
        </EllipsisTypography>
        <EllipsisTypography textAlign="center" variant="body2">
          {data.parentName}
        </EllipsisTypography>
      </Link>
    </Box>
  );
};

export default Tile;
