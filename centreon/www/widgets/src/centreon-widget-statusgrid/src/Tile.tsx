import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { Box, CardActionArea, Typography } from '@mui/material';
import DvrIcon from '@mui/icons-material/Dvr';

import { EllipsisTypography } from '@centreon/ui';

import { Resource } from '../../models';

import { useTileStyles } from './StatusGrid.styles';
import { ResourceData } from './models';
import { labelSeeMore } from './translatedLabels';
import { getResourcesUrl } from './utils';

interface Props {
  data: ResourceData | null;
  isSmallestSize: boolean;
  resources: Array<Resource>;
  states: Array<string>;
  statuses: Array<string>;
  type: string;
}

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

  const navigate = useNavigate();

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

  if (isSmallestSize && !isNil(data)) {
    return null;
  }

  return (
    <Box className={classes.container} data-status={data.statusName}>
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
