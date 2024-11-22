import { useCallback } from 'react';

import { useFormikContext } from 'formik';
import {
  equals,
  gt,
  includes,
  isEmpty,
  pluck,
  reject,
  remove,
  update
} from 'ramda';

import { Checkbox, ListItem, Radio, Typography } from '@mui/material';

import { useDeepCompare } from '@centreon/ui';
import { CollapsibleItem } from '@centreon/ui/components';

import { FormMetric, ServiceMetric } from '../../../models';

import { useMetricsStyles } from './Metrics.styles';

interface ChangeExcludedMetricsProps {
  currentExcludedMetrics: Array<number>;
  excludedMetricIndex: number;
  metricId: number;
  shouldRemove?: boolean;
}

interface Props {
  getResourcesByMetricName: (
    metricName: string
  ) => Array<{ metricId?: number } & Omit<ServiceMetric, 'metrics'>>;
  propertyName: string;
  value: Array<FormMetric>;
}

interface UseRenderOptionsState {
  renderOptionsForMultipleMetricsAndResources: (
    _,
    option: FormMetric
  ) => JSX.Element;
  renderOptionsForSingleMetric: (_, option: FormMetric) => JSX.Element;
}

export const useRenderOptions = ({
  getResourcesByMetricName,
  value,
  propertyName
}: Props): UseRenderOptionsState => {
  const { classes, cx } = useMetricsStyles();

  const { setFieldValue, setFieldTouched } = useFormikContext();

  const getSelectedMetricByMetricName = useCallback(
    (metricName: string): FormMetric | undefined => {
      return value.find(({ name }) => equals(name, metricName));
    },
    useDeepCompare([value])
  );

  const selectMetric = useCallback(
    (newMetric: FormMetric) => () => {
      setFieldValue(`data.${propertyName}`, [
        {
          ...newMetric,
          excludedMetrics: [],
          includeAllMetrics: true
        }
      ]);
      setFieldTouched(`data.${propertyName}`, true, false);
    },
    [propertyName]
  );

  const changeExcludedMetrics = useCallback(
    ({
      currentExcludedMetrics,
      excludedMetricIndex,
      metricId,
      shouldRemove
    }: ChangeExcludedMetricsProps): Array<number> => {
      if (shouldRemove) {
        return remove(excludedMetricIndex, 1, currentExcludedMetrics);
      }

      return [...(currentExcludedMetrics || []), metricId];
    },
    []
  );

  const getAreAllMetricsExcluded = useCallback(
    ({ metric, newExcludedMetrics }): boolean => {
      return equals(
        pluck('metricId', getResourcesByMetricName(metric.name)).sort(),
        newExcludedMetrics.sort()
      );
    },
    [getResourcesByMetricName]
  );

  const selectMetricsWithAllResources = useCallback(
    (newMetric: FormMetric) => () => {
      const metricIndex = value.findIndex(({ name }) =>
        equals(name, newMetric.name)
      );

      if (gt(metricIndex, -1)) {
        setFieldValue(`data.${propertyName}`, remove(metricIndex, 1, value));
        setFieldTouched(`data.${propertyName}`, true, false);

        return;
      }

      setFieldValue(`data.${propertyName}`, [
        ...value,
        {
          ...newMetric,
          excludedMetrics: [],
          includeAllMetrics: true
        }
      ]);
      setFieldTouched(`data.${propertyName}`, true, false);
    },
    useDeepCompare([value, propertyName])
  );

  const resourceChange =
    ({ metric, metricId }) =>
    () => {
      const metricIndex = value.findIndex(({ name }) =>
        equals(name, metric.name)
      );
      const currentMetric = getSelectedMetricByMetricName(metric.name);

      if (!currentMetric) {
        const resourcesForTheMetric = pluck(
          'metricId',
          getResourcesByMetricName(metric.name)
        );

        setFieldValue(`data.${propertyName}`, [
          ...value,
          {
            ...metric,
            excludedMetrics: reject(
              (currentMetricId) => equals(currentMetricId, metricId),
              resourcesForTheMetric
            ),
            includeAllMetrics: false
          }
        ]);
        setFieldTouched(`data.${propertyName}`, true, false);

        return;
      }

      const excludedMetricIndex = currentMetric.excludedMetrics.findIndex(
        (excludedMetric) => equals(metricId, excludedMetric)
      );

      if (currentMetric.includeAllMetrics) {
        const newExcludedMetrics = changeExcludedMetrics({
          currentExcludedMetrics: currentMetric.excludedMetrics,
          excludedMetricIndex,
          metricId,
          shouldRemove: gt(excludedMetricIndex, -1)
        });

        setFieldValue(
          `data.${propertyName}`,
          getAreAllMetricsExcluded({ metric, newExcludedMetrics })
            ? remove(metricIndex, 1, value)
            : update(
                metricIndex,
                {
                  ...currentMetric,
                  excludedMetrics: newExcludedMetrics
                },
                value
              )
        );
        setFieldTouched(`data.${propertyName}`, true, false);

        return;
      }

      if (!currentMetric.includeAllMetrics && equals(excludedMetricIndex, -1)) {
        const newExcludedMetrics = [
          ...(currentMetric.excludedMetrics || []),
          metricId
        ].sort();

        setFieldValue(
          `data.${propertyName}`,
          getAreAllMetricsExcluded({
            metric,
            newExcludedMetrics
          })
            ? remove(metricIndex, 1, value)
            : update(
                metricIndex,
                {
                  ...currentMetric,
                  excludedMetrics: changeExcludedMetrics({
                    currentExcludedMetrics: currentMetric.excludedMetrics,
                    excludedMetricIndex,
                    metricId
                  }),
                  includeAllMetrics: false
                },
                value
              )
        );
        setFieldTouched(`data.${propertyName}`, true, false);

        return;
      }

      if (equals(excludedMetricIndex, -1)) {
        setFieldValue(
          `data.${propertyName}`,
          update(
            metricIndex,
            {
              ...currentMetric,
              excludedMetrics: changeExcludedMetrics({
                currentExcludedMetrics: currentMetric.excludedMetrics,
                excludedMetricIndex,
                metricId
              }),
              includeAllMetrics: false
            },
            value
          )
        );
        setFieldTouched(`data.${propertyName}`, true, false);

        return;
      }

      const newExcludedMetrics = remove(
        excludedMetricIndex,
        1,
        currentMetric.excludedMetrics
      );

      if (!isEmpty(newExcludedMetrics)) {
        setFieldValue(
          `data.${propertyName}`,
          update(
            metricIndex,
            {
              ...currentMetric,
              excludedMetrics: newExcludedMetrics,
              includeAllMetrics: false
            },
            value
          )
        );
        setFieldTouched(`data.${propertyName}`, true, false);

        return;
      }

      setFieldValue(
        `data.${propertyName}`,
        update(
          metricIndex,
          {
            ...currentMetric,
            excludedMetrics: [],
            includeAllMetrics: true
          },
          value
        )
      );
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  const isResourceExcluded = ({ metricName, metricId }): boolean => {
    const metric = getSelectedMetricByMetricName(metricName);

    return includes(metricId, metric?.excludedMetrics || []);
  };

  const renderOptionsForSingleMetric = (_, option): JSX.Element => {
    const resources = getResourcesByMetricName(option.name);

    return (
      <ListItem disableGutters>
        <CollapsibleItem
          compact
          dataTestId={option.name}
          title={
            <div className={classes.resourcesOptionRadioCheckbox}>
              <Radio
                checked={Boolean(getSelectedMetricByMetricName(option.name))}
                className={classes.radioCheckbox}
                data-checked={Boolean(
                  getSelectedMetricByMetricName(option.name)
                )}
                data-testid={option.name}
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

  const renderOptionsForMultipleMetricsAndResources = (
    props,
    option: FormMetric
  ): JSX.Element => {
    const resources = getResourcesByMetricName(option.name);

    const currentMetricValue = getSelectedMetricByMetricName(option.name);

    const isMetricSelected = Boolean(currentMetricValue);

    const isMetricChecked =
      Boolean(getSelectedMetricByMetricName(option.name)) &&
      isEmpty(currentMetricValue?.excludedMetrics);

    const isMetricIndeterminate =
      Boolean(getSelectedMetricByMetricName(option.name)) &&
      !isEmpty(currentMetricValue?.excludedMetrics);

    return (
      <ListItem disableGutters>
        <CollapsibleItem
          compact
          dataTestId={option.name}
          title={
            <div className={classes.resourcesOptionRadioCheckbox}>
              <Checkbox
                checked={isMetricChecked}
                className={classes.radioCheckbox}
                data-checked={isMetricChecked}
                data-testid={option.name}
                disabled={props['aria-disabled']}
                indeterminate={isMetricIndeterminate}
                size="small"
                onChange={selectMetricsWithAllResources(option)}
              />
              <Typography
                color={props['aria-disabled'] ? 'text.disabled' : 'inherit'}
              >{`${option.name} (${option.unit})`}</Typography>
            </div>
          }
        >
          <div className={classes.resourcesOptionContainer}>
            {resources.map(({ name, parentName, uuid, metricId }) => (
              <div
                className={cx(
                  classes.resourceOption,
                  classes.resourcesOptionRadioCheckbox
                )}
                key={`${parentName}_${name}_${uuid}`}
              >
                <Checkbox
                  checked={
                    isMetricSelected &&
                    !isResourceExcluded({
                      metricId,
                      metricName: option.name
                    })
                  }
                  className={classes.radioCheckbox}
                  data-checked={
                    isMetricSelected &&
                    !isResourceExcluded({
                      metricId,
                      metricName: option.name
                    })
                  }
                  data-testid={`${option.name}_${parentName}:${name}`}
                  disabled={props['aria-disabled']}
                  size="small"
                  onChange={resourceChange({ metric: option, metricId })}
                />
                <Typography
                  color={props['aria-disabled'] ? 'text.disabled' : 'inherit'}
                >
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
    renderOptionsForMultipleMetricsAndResources,
    renderOptionsForSingleMetric
  };
};
