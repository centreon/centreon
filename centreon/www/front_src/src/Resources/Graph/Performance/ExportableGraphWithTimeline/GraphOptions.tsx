import { useAtomValue, useSetAtom } from 'jotai';
import { pluck, values } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { FormControlLabel, FormGroup, Switch, Typography } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import {
  setGraphTabParametersDerivedAtom,
  tabParametersAtom
} from '../../../Details/detailsAtoms';
import type { GraphOption, GraphOptions } from '../../../Details/models';

import {
  changeGraphOptionsDerivedAtom,
  graphOptionsAtom
} from './graphOptionsAtoms';

const useStyles = makeStyles()(() => ({
  optionLabel: {
    justifyContent: 'space-between',
    margin: 0
  }
}));

const Options = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const graphOptions = useAtomValue(graphOptionsAtom);
  const tabParameters = useAtomValue(tabParametersAtom);
  const changeGraphOptions = useSetAtom(changeGraphOptionsDerivedAtom);
  const setGraphTabParameters = useSetAtom(setGraphTabParametersDerivedAtom);

  const graphOptionsConfiguration = values(graphOptions);

  const graphOptionsConfigurationValue = pluck<keyof GraphOption, GraphOption>(
    'value',
    graphOptionsConfiguration
  );

  console.log({ graphOptionsConfiguration });

  const changeTabGraphOptions = (options: GraphOptions): void => {
    console.log({ options });
    setGraphTabParameters({
      ...tabParameters.graph,
      options
    });
  };

  return useMemoComponent({
    Component: (
      <FormGroup>
        {graphOptionsConfiguration.map(({ label, value, id }) => (
          <FormControlLabel
            className={classes.optionLabel}
            control={
              <Switch
                checked={value}
                color="primary"
                size="small"
                onChange={(): void =>
                  changeGraphOptions({
                    changeTabGraphOptions,
                    graphOptionId: id
                  })
                }
              />
            }
            data-testid={label}
            key={label}
            label={<Typography variant="body2">{t(label)}</Typography>}
            labelPlacement="bottom"
          />
        ))}
      </FormGroup>
    ),
    memoProps: [graphOptionsConfigurationValue]
  });
};

export default Options;
