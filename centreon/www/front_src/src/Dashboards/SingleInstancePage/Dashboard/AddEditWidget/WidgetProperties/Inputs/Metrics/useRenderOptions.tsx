import { ChangeEvent } from 'react';

import { equals } from 'ramda';
import { useFormikContext } from 'formik';

import { ListItem, Typography, Radio } from '@mui/material';

import { CollapsibleItem } from '@centreon/ui/components';

import { FormMetric, ServiceMetric } from '../../../models';

import { useMetricsStyles } from './Metrics.styles';

interface Props {
  getResourcesByMetricName: (
    metricName: string
  ) => Array<{ metricId?: number } & Omit<ServiceMetric, 'metrics'>>;
  propertyName: string;
  value: Array<FormMetric>;
}

export const useRenderOptions = ({
  getResourcesByMetricName,
  value,
  propertyName
}: Props) => {
  const { classes } = useMetricsStyles();

  const { setFieldValue, setFieldTouched } = useFormikContext();

  const getSelectedMetricByMetricName = (metricName: string): boolean => {
    return value.some(({ name }) => equals(name, metricName));
  };

  const selectMetric =
    (newMetric: FormMetric) => (event: ChangeEvent<HTMLInputElement>) => {
      setFieldValue(`data.${propertyName}`, [
        {
          ...newMetric,
          excludedMetrics: [],
          includeAllMetrics: true
        }
      ]);
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  const renderOptionsForSingleMetric = (_, option): JSX.Element => {
    const resources = getResourcesByMetricName(option.name);

    return (
      <ListItem disableGutters>
        <CollapsibleItem
          compact
          title={
            <div className={classes.resourcesOptionRadioCheckbox}>
              <Radio
                checked={getSelectedMetricByMetricName(option.name)}
                className={classes.radioCheckbox}
                size="small"
                onChange={selectMetric(option)}
              />
              <Typography>{`${option.name} (${option.unit})`}</Typography>
            </div>
          }
        >
          <div className={classes.resourcesOptionContainer}>
            {resources.map(({ name, parentName, uuid }) => (
              <div
                className={classes.resourceOption}
                key={`${parentName}_${name}_${uuid}`}
              >
                <Typography>
                  {parentName}:{name}
                </Typography>
              </div>
            ))}
          </div>
        </CollapsibleItem>
      </ListItem>
    );
  };

  return {
    renderOptionsForSingleMetric
  };
};
