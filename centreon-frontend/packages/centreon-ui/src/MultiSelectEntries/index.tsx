/* eslint-disable react/prop-types */

import React, { Ref } from 'react';

import clsx from 'clsx';

import { Box, Chip, Grid, FormHelperText, Typography } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import IconCreate from '@mui/icons-material/Create';

import useHover from './useHover';

const maxChips = 5;

const useStyles = makeStyles((theme) => ({
  chip: {
    marginTop: theme.spacing(1),
    width: '95%',
  },
  container: {
    borderRadius: theme.spacing(0.5),
    cursor: 'pointer',
    outline: 'none',
    padding: theme.spacing(1),
    width: '100%',
  },
  emptyChip: {
    borderColor: theme.palette.grey[600],
    borderStyle: 'dashed',
    borderWidth: 2,
    padding: theme.spacing(1),
    textAlign: 'center',
  },
  hidden: {
    visibility: 'hidden',
  },
  hovered: {
    backgroundColor: theme.palette.grey[400],
  },
}));

const Entry = ({ label }): JSX.Element => {
  const classes = useStyles();

  return (
    <Grid item xs={6}>
      <Chip disabled className={classes.chip} label={label} size="small" />
    </Grid>
  );
};

const EmptyEntry = ({ label }): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.emptyChip}>
      <Caption>{label}</Caption>
    </div>
  );
};

const Caption = ({ children }): JSX.Element => (
  <Typography variant="caption">{children}</Typography>
);

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
  error = undefined,
}: Props): JSX.Element => {
  const classes = useStyles();

  const [hoverRef, isHovered] = useHover();

  const count = values.length;

  const caption = `${label} (${count})`;

  return (
    <div
      className={clsx({
        [classes.hovered]: isHovered || highlight,
        [classes.container]: true,
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
            className={clsx({ [classes.hidden]: !isHovered && !highlight })}
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
