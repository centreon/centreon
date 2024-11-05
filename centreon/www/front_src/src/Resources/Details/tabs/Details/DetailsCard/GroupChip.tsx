import { useCallback, useState } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import FilterListIcon from '@mui/icons-material/FilterList';
import SettingsIcon from '@mui/icons-material/Settings';
import { Chip, Grid, Tooltip, Typography } from '@mui/material';
import IconButton from '@mui/material/IconButton';

import { CriteriaNames } from '../../../../Filter/Criterias/models';
import { setCriteriaAndNewFilterDerivedAtom } from '../../../../Filter/filterAtoms';
import { Category, Group } from '../../../models';

const useStyles = makeStyles()((theme) => ({
  chip: {
    alignSelf: 'center',
    display: 'flex',
    height: theme.spacing(4),
    borderRadius: theme.spacing(2)
  },
  chipHovered: {
    backgroundColor: theme.palette.primary.main,
    display: 'flex',
    gap: theme.spacing(0.25),
    gridArea: '1/1'
  },
  chipIcon: {
    color: theme.palette.common.white
  },
  chipLabelContainer: {
    display: 'grid',
    justifyItems: 'center',
    minWidth: theme.spacing(7),
    overflow: 'hidden'
  },
  chipLabelContent: {
    gridArea: '1/1',
    maxWidth: theme.spacing(14),
    minWidth: theme.spacing(8),
    overflow: 'hidden',
    textAlign: 'center'
  },
  chipLabelContentHovered: { color: 'transparent' }
}));

interface Props {
  group: Group | Category;
  type: CriteriaNames;
}

const GroupChip = ({ group, type }: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const { t } = useTranslation();
  const [isHovered, setIsHovered] = useState<boolean>(false);

  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const mouseEnter = (): void => {
    setIsHovered(true);
  };

  const mouseLeave = (): void => {
    setIsHovered(false);
  };

  const filterByGroup = useCallback((): void => {
    setCriteriaAndNewFilter({
      name: type,
      value: [group],
      apply: true
    });
  }, [group, type]);

  const configureGroup = useCallback((): void => {
    window.location.href = group.configuration_uri as string;
  }, [group]);

  const { name, id } = group;

  return (
    <Grid item key={id}>
      <Chip
        className={classes.chip}
        aria-label={`${name} Chip`}
        color="primary"
        label={
          <div className={classes.chipLabelContainer}>
            <Tooltip title={name}>
              <Typography
                className={cx(
                  classes.chipLabelContent,
                  isHovered ? classes.chipLabelContentHovered : ''
                )}
                variant="body2"
              >
                {name}
              </Typography>
            </Tooltip>
            {isHovered && (
              <Grid className={classes.chipHovered}>
                <IconButton
                  aria-label={`${name} Filter`}
                  className={classes.chipIcon}
                  size="small"
                  title={t(name)}
                  onClick={filterByGroup}
                >
                  <FilterListIcon fontSize="small" />
                </IconButton>
                <IconButton
                  aria-label={`${name} Configure`}
                  className={classes.chipIcon}
                  size="small"
                  title={t(name)}
                  onClick={configureGroup}
                >
                  <SettingsIcon fontSize="small" />
                </IconButton>
              </Grid>
            )}
          </div>
        }
        onMouseEnter={mouseEnter}
        onMouseLeave={mouseLeave}
      />
    </Grid>
  );
};

export default GroupChip;
