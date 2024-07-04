import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate, Link } from 'react-router-dom';

import { Box, CardActionArea, Typography } from '@mui/material';
import DvrIcon from '@mui/icons-material/Dvr';

import { EllipsisTypography } from '@centreon/ui';

import { Resource } from '../../../models';
import { getResourcesUrl } from '../../../utils';

import { useTileStyles } from './StatusGrid.styles';
import { ResourceData } from './models';
import { labelSeeMore } from './translatedLabels';
import State from './State';

interface Props {
  data: ResourceData | null;
  isSmallestSize: boolean;
  resources: Array<Resource>;
  statuses: Array<string>;
  type: string;
}

export const router = {
  useNavigate
};

const Tile = ({
  isSmallestSize,
  data,
  type,
  statuses,
  resources
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useTileStyles();

  const getLinkToResourceStatus = ({ isForOneResource }): string =>
    getResourcesUrl({
      allResources: resources,
      isForOneResource,
      resource: data,
      states: [],
      statuses,
      type
    });

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
            type={type}
          />
        )}
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
