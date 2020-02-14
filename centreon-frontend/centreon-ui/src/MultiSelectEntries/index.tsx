/* eslint-disable react/prop-types */

import React, { Ref } from 'react';

import clsx from 'clsx';

import {
  Box,
  Chip,
  Grid,
  FormHelperText,
  Typography,
  makeStyles,
} from '@material-ui/core';
import { Create } from '@material-ui/icons';

import useHover from './useHover';

const maxChips = 5;

const useStyles = makeStyles((theme) => ({
  hidden: {
    visibility: 'hidden',
  },
  container: {
    borderRadius: theme.spacing(1) * 0.5,
    padding: theme.spacing(1),
    cursor: 'pointer',
    outline: 'none',
    width: '100%',
  },
  hovered: {
    backgroundColor: theme.palette.grey[400],
  },
  chip: {
    width: '95%',
    marginTop: theme.spacing(1),
  },
  emptyChip: {
    padding: theme.spacing(1),
    borderWidth: 2,
    borderColor: theme.palette.grey[600],
    borderStyle: 'dashed',
    textAlign: 'center',
  },
}));

const Entry = ({ label }): JSX.Element => {
  const classes = useStyles();

  return (
    <Grid item xs={6}>
      <Chip className={classes.chip} disabled label={label} size="small" />
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
  label: string;
  highlight?: boolean;
  emptyLabel: string;
  onClick: () => void;
  values?: Array<Value>;
  error?;
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
      ref={hoverRef as Ref<HTMLDivElement>}
      className={clsx({
        [classes.hovered]: isHovered || highlight,
        [classes.container]: true,
      })}
      onClick={onClick}
      onKeyDown={onClick}
      role="button"
      tabIndex={0}
    >
      <Box display="flex">
        <Box flexGrow={1}>
          <Caption>{caption}</Caption>
        </Box>
        <Box>
          <Create
            fontSize="small"
            className={clsx({ [classes.hidden]: !isHovered && !highlight })}
          />
        </Box>
      </Box>
      <Grid container justify="flex-start">
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
