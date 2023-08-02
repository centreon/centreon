/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { isEmpty, isNil } from 'ramda';
import pluralize from 'pluralize';

import {
  Avatar,
  CircularProgress,
  FormHelperText,
  Typography
} from '@mui/material';

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

  const title = metricCount
    ? `${t(labelMetrics)} (${metricCount} ${pluralize(
        labelMetric,
        metricCount
      )})`
    : t(labelMetrics);

  const header = (
    <div className={classes.resourcesHeader}>
      <Avatar className={classes.resourcesHeaderAvatar}>2</Avatar>
      <Typography>{title}</Typography>
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
      {isLoadingMetrics && <CircularProgress size={16} />}
    </div>
  );

  return (
    <div className={classes.resourcesContainer}>
      {header}
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
              className={classes.resourceCompositionItem}
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
                selectedOptionId={service.id}
                onChange={changeService(index)}
              />
              <MultiAutocompleteField
                className={classes.resources}
                disabled={
                  isNil(service.id) || isEmpty(service.id) || isLoadingMetrics
                }
                getOptionDisabled={getMetricOptionDisabled}
                getOptionLabel={getOptionLabel}
                getTagLabel={getOptionLabel}
                label={t(labelMetrics)}
                limitTags={1}
                options={getMetricsFromService(service.id)}
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
