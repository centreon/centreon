/* eslint-disable react/prop-types */

import { Ref } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Box, Chip, Grid, FormHelperText, Typography } from '@mui/material';
import IconCreate from '@mui/icons-material/Create';

import useHover from './useHover';

const maxChips = 5;

const useStyles = makeStyles()((theme) => ({
  chip: {
    backgroundColor: theme.palette.action.disabledBackground,
    marginTop: theme.spacing(1),
    width: '95%'
  },
  container: {
    borderRadius: theme.spacing(0.5),
    cursor: 'pointer',
    outline: 'none',
    padding: theme.spacing(1),
    width: '100%'
  },
  emptyChip: {
    borderColor: theme.palette.divider,
    borderStyle: 'dashed',
    borderWidth: 2,
    padding: theme.spacing(1),
    textAlign: 'center'
  },
  hidden: {
    visibility: 'hidden'
  },
  hovered: {
    backgroundColor: theme.palette.action.hover
  },
  icon: {
    color: theme.palette.action.active
  },
  labelChip: {
    color: theme.palette.text.secondary
  },
  text: {
    color: theme.palette.text.primary
  }
}));

const Entry = ({ label }): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Grid item xs={6}>
      <Chip
        className={classes.chip}
        label={<div className={classes.labelChip}>{label}</div>}
        size="small"
      />
    </Grid>
  );
};

const EmptyEntry = ({ label }): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.emptyChip}>
      <Caption>{label}</Caption>
    </div>
  );
};

const Caption = ({ children }): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Typography className={classes.text} variant="caption">
      {children}
    </Typography>
  );
};

interface Value {
  id: number;
  name: string;
}

interface Props {
  emptyLabel: string;
  error?;
  highlight?: boolean;
  label: string;
  onClick: () => void;
  values?: Array<Value>;
}

const MultiSelectEntries = ({
  label,
  emptyLabel,
  onClick,
  values = [],
  highlight = false,
  error = undefined
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const [hoverRef, isHovered] = useHover();

  const count = values.length;

  const caption = `${label} (${count})`;

  return (
    <div
      className={cx({
        [classes.hovered]: isHovered || highlight,
        [classes.container]: true
      })}
      ref={hoverRef as Ref<HTMLDivElement>}
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={onClick}
    >
      <Box display="flex">
        <Box flexGrow={1}>
          <Caption>{caption}</Caption>
        </Box>
        <Box>
          <IconCreate
            className={cx(
              { [classes.hidden]: !isHovered && !highlight },
              classes.icon
            )}
            fontSize="small"
          />
        </Box>
      </Box>
      <Grid container justifyContent="flex-start">
        {values.slice(0, maxChips).map(({ id, name }) => (
          <Entry key={id} label={name} />
        ))}
        {count > maxChips && <Entry label="..." />}
      </Grid>
      {count === 0 && <EmptyEntry label={emptyLabel} />}
      {error && <FormHelperText error>{error}</FormHelperText>}
    </div>
  );
};

export default MultiSelectEntries;
