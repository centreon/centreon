<<<<<<< HEAD
import { MouseEvent, useState } from 'react';

import { isNil, not, pluck, values } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import { FormControlLabel, FormGroup, Popover, Switch } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import SettingsIcon from '@mui/icons-material/Settings';
=======
import * as React from 'react';

import { isNil, not, pluck, values } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  FormControlLabel,
  FormGroup,
  makeStyles,
  Popover,
  Switch,
} from '@material-ui/core';
import SettingsIcon from '@material-ui/icons/Settings';
>>>>>>> centreon/dev-21.10.x

import { IconButton, useMemoComponent } from '@centreon/ui';

import { labelGraphOptions } from '../../../translatedLabels';
<<<<<<< HEAD
import { GraphOption, GraphOptions } from '../../../Details/models';
import {
  setGraphTabParametersDerivedAtom,
  tabParametersAtom,
} from '../../../Details/detailsAtoms';

import {
  changeGraphOptionsDerivedAtom,
  graphOptionsAtom,
} from './graphOptionsAtoms';
=======
import { GraphOption } from '../../../Details/models';

import { useGraphOptionsContext } from './useGraphOptions';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  optionLabel: {
    justifyContent: 'space-between',
    margin: 0,
  },
  popoverContent: {
    margin: theme.spacing(1, 2),
  },
}));

<<<<<<< HEAD
const Options = (): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();
  const [anchorEl, setAnchorEl] = useState<Element | null>(null);

  const graphOptions = useAtomValue(graphOptionsAtom);
  const tabParameters = useAtomValue(tabParametersAtom);
  const changeGraphOptions = useUpdateAtom(changeGraphOptionsDerivedAtom);
  const setGraphTabParameters = useUpdateAtom(setGraphTabParametersDerivedAtom);

  const openGraphOptions = (event: MouseEvent<HTMLButtonElement>): void => {
=======
const GraphOptions = (): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();
  const [anchorEl, setAnchorEl] = React.useState<Element | null>(null);
  const { graphOptions, changeGraphOptions } = useGraphOptionsContext();

  const openGraphOptions = (event: React.MouseEvent): void => {
>>>>>>> centreon/dev-21.10.x
    if (isNil(anchorEl)) {
      setAnchorEl(event.currentTarget);

      return;
    }
    setAnchorEl(null);
  };

  const closeGraphOptions = (): void => setAnchorEl(null);

  const graphOptionsConfiguration = values(graphOptions);

  const graphOptionsConfigurationValue = pluck<keyof GraphOption, GraphOption>(
    'value',
    graphOptionsConfiguration,
  );

<<<<<<< HEAD
  const changeTabGraphOptions = (options: GraphOptions): void => {
    setGraphTabParameters({
      ...tabParameters.graph,
      options,
    });
  };

=======
>>>>>>> centreon/dev-21.10.x
  return useMemoComponent({
    Component: (
      <>
        <IconButton
          ariaLabel={t(labelGraphOptions)}
          data-testid={labelGraphOptions}
          size="small"
          title={t(labelGraphOptions)}
          onClick={openGraphOptions}
        >
          <SettingsIcon style={{ fontSize: 18 }} />
        </IconButton>
        <Popover
          anchorEl={anchorEl}
          anchorOrigin={{
            horizontal: 'center',
            vertical: 'bottom',
          }}
          open={not(isNil(anchorEl))}
          onClose={closeGraphOptions}
        >
          <FormGroup className={classes.popoverContent}>
            {graphOptionsConfiguration.map(({ label, value, id }) => (
              <FormControlLabel
                className={classes.optionLabel}
                control={
                  <Switch
                    checked={value}
                    color="primary"
                    size="small"
<<<<<<< HEAD
                    onChange={(): void =>
                      changeGraphOptions({
                        changeTabGraphOptions,
                        graphOptionId: id,
                      })
                    }
=======
                    onChange={changeGraphOptions(id)}
>>>>>>> centreon/dev-21.10.x
                  />
                }
                data-testid={label}
                key={label}
<<<<<<< HEAD
                label={t(label) as string}
=======
                label={t(label)}
>>>>>>> centreon/dev-21.10.x
                labelPlacement="start"
              />
            ))}
          </FormGroup>
        </Popover>
      </>
    ),
    memoProps: [graphOptionsConfigurationValue, anchorEl],
  });
};

<<<<<<< HEAD
export default Options;
=======
export default GraphOptions;
>>>>>>> centreon/dev-21.10.x
