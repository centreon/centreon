/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { isEmpty, isNil } from 'ramda';
import pluralize from 'pluralize';

import { FormHelperText, Typography } from '@mui/material';

import { ItemComposition } from '@centreon/ui/components';

import {
  labelAdd,
  labelDelete,
  labelMetric,
  labelMetrics,
  labelPleaseSelectAResource,
  labelServiceName,
  labelTheLimiteOf2UnitsHasBeenReached,
  labelTooManyMetricsAddMoreFilterOnResources
} from '../../../translatedLabels';
import { WidgetPropertyProps } from '../../models';

import useMetrics from './useMetrics';
import { useResourceStyles } from './Inputs.styles';

import { MultiAutocompleteField, SelectField } from 'packages/ui/src';

const Metrics = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { classes } = useResourceStyles();
  const { t } = useTranslation();

  const {
    hasNoResources,
    addMetric,
    hasTooManyMetrics,
    deleteMetric,
    value,
    serviceOptions,
    changeService,
    getMetricsFromService,
    changeMetric,
    metricCount,
    isLoadingMetrics,
    error,
    getMetricOptionDisabled,
    getOptionLabel,
    hasReachedTheLimitOfUnits
  } = useMetrics(propertyName);

  const canDisplayMetricsSelection = !hasNoResources() && !hasTooManyMetrics;

  return (
    <div className={classes.resourcesContainer}>
      <Typography>
        {metricCount
          ? `${t(labelMetrics)} (${metricCount} ${pluralize(
              labelMetric,
              metricCount
            )})`
          : t(labelMetrics)}
        {hasReachedTheLimitOfUnits && (
          <Typography
            component="span"
            sx={{ color: 'warning.main' }}
            variant="body2"
          >
            {' '}
            {t(labelTheLimiteOf2UnitsHasBeenReached)}
          </Typography>
        )}
      </Typography>
      {hasNoResources() && (
        <Typography>{t(labelPleaseSelectAResource)}</Typography>
      )}
      {hasTooManyMetrics && (
        <Typography>
          {t(labelTooManyMetricsAddMoreFilterOnResources)}
        </Typography>
      )}
      {canDisplayMetricsSelection && (
        <ItemComposition labelAdd={t(labelAdd)} onAddItem={addMetric}>
          {value.map((service, index) => (
            <ItemComposition.Item
              key={`${index}`}
              labelDelete={t(labelDelete)}
              onDeleteItem={deleteMetric(index)}
            >
              <SelectField
                ariaLabel={t(labelServiceName) as string}
                className={classes.resourceType}
                dataTestId={labelServiceName}
                disabled={isLoadingMetrics}
                label={t(labelServiceName) as string}
                options={serviceOptions}
                selectedOptionId={service.serviceId}
                onChange={changeService(index)}
              />
              <MultiAutocompleteField
                className={classes.resources}
                disabled={
                  isNil(service.serviceId) ||
                  isEmpty(service.serviceId) ||
                  isLoadingMetrics
                }
                getOptionDisabled={getMetricOptionDisabled}
                getOptionLabel={getOptionLabel}
                getTagLabel={getOptionLabel}
                label={t(labelMetrics)}
                limitTags={1}
                options={getMetricsFromService(service.serviceId)}
                value={service.metrics || []}
                onChange={changeMetric(index)}
              />
            </ItemComposition.Item>
          ))}
        </ItemComposition>
      )}
      {error && <FormHelperText error>{t(error)}</FormHelperText>}
    </div>
  );
};

export default Metrics;
