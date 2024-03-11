import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate, Link } from 'react-router-dom';

import { Box, CardActionArea, Typography, useTheme } from '@mui/material';
import DvrIcon from '@mui/icons-material/Dvr';

import { EllipsisTypography } from '@centreon/ui';

import { Resource } from '../../models';
import { getResourcesUrl } from '../../utils';

import { useTileStyles } from './StatusGrid.styles';
import { ResourceData } from './models';
import { labelSeeMore } from './translatedLabels';
import { getColor } from './utils';

interface Props {
  data: ResourceData | null;
  isSmallestSize: boolean;
  resources: Array<Resource>;
  states: Array<string>;
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
  states,
  statuses,
  resources
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useTileStyles();
  const theme = useTheme();

  const getLinkToResourceStatus = ({ isForOneResource }): string =>
    getResourcesUrl({
      allResources: resources,
      isForOneResource,
      resource: data,
      states,
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
          {displayStatusTile ? (
            <Box
              className={classes.statusTile}
              data-mode="compact"
              sx={{
                backgroundColor: getColor({ severityCode: data.status, theme })
              }}
            />
          ) : null}
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
          <Box
            className={classes.statusTile}
            sx={{
              backgroundColor: getColor({ severityCode: data.status, theme })
            }}
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
