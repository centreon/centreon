import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { Box, CardActionArea, Typography, useTheme } from '@mui/material';
import DvrIcon from '@mui/icons-material/Dvr';

import { EllipsisTypography } from '@centreon/ui';

import { Resource } from '../../models';

import { useTileStyles } from './StatusGrid.styles';
import { ResourceData } from './models';
import { labelSeeMore } from './translatedLabels';
import { getColor, getResourcesUrl, openResourceStatusPanel } from './utils';

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

  const navigate = router.useNavigate();

  const goToResourceStatus = (): void => {
    navigate(
      getResourcesUrl({
        resources,
        states,
        statuses,
        type
      })
    );
  };

  const goToResourceStatusAndOpenPanel = (): void => {
    goToResourceStatus();
    openResourceStatusPanel(data);
  };

  if (isNil(data)) {
    return (
      <CardActionArea
        className={classes.seeMoreContainer}
        onClick={goToResourceStatus}
      >
        <DvrIcon
          color="primary"
          fontSize={isSmallestSize ? 'medium' : 'large'}
        />
        {!isSmallestSize && <Typography>{t(labelSeeMore)}</Typography>}
      </CardActionArea>
    );
  }

  const displayStatusTile = data.is_acknowledged || data.is_in_downtime;

  if (isSmallestSize && !isNil(data)) {
    return (
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
    );
  }

  return (
    <Box
      className={classes.container}
      data-status={data.statusName}
      onClick={goToResourceStatusAndOpenPanel}
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
    </Box>
  );
};

export default Tile;
