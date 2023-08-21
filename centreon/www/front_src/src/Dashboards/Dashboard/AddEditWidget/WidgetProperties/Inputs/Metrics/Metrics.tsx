/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { isEmpty, isNil } from 'ramda';

import { CircularProgress, FormHelperText, Typography } from '@mui/material';

import { Avatar, ItemComposition } from '@centreon/ui/components';
import { MultiAutocompleteField, SelectField } from '@centreon/ui';

import {
  labelAddMetric,
  labelAvailable,
  labelDelete,
  labelMetrics,
  labelPleaseSelectAResource,
  labelServiceName,
  labelYouCanSelectUpToTwoMetricUnits,
  labelYouHaveTooManyMetrics
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useAddWidgetStyles } from '../../../addWidget.styles';
import { useResourceStyles } from '../Inputs.styles';

import useMetrics from './useMetrics';

const Metrics = ({ propertyName }: WidgetPropertyProps): JSX.Element => {
  const { classes } = useResourceStyles();
  const { classes: avatarClasses } = useAddWidgetStyles();
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
    hasReachedTheLimitOfUnits,
    addButtonHidden
  } = useMetrics(propertyName);

  const addButtonDisabled =
    hasNoResources() || hasTooManyMetrics || !metricCount;

  const canDisplayMetricsSelection = !hasNoResources() && !hasTooManyMetrics;

  const title = metricCount
    ? `${t(labelMetrics)} (${metricCount} ${labelAvailable})`
    : t(labelMetrics);

  const header = (
    <div className={classes.resourcesHeader}>
      <Avatar compact className={avatarClasses.widgetAvatar}>
        3
      </Avatar>
      <Typography>{title}</Typography>
      {hasReachedTheLimitOfUnits && (
        <Typography
          component="span"
          sx={{ color: 'warning.main' }}
          variant="body2"
        >
          {' '}
          {t(labelYouCanSelectUpToTwoMetricUnits)}
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
      {canDisplayMetricsSelection && (
        <ItemComposition
          addButtonHidden={addButtonHidden}
          addbuttonDisabled={addButtonDisabled}
          labelAdd={t(labelAddMetric)}
          onAddItem={addMetric}
        >
          {value.map((service, index) => (
            <ItemComposition.Item
              className={classes.resourceCompositionItem}
              deleteButtonHidden={addButtonHidden}
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
      {hasTooManyMetrics && (
        <Typography sx={{ color: 'text.disabled' }}>
          {t(labelYouHaveTooManyMetrics)}
        </Typography>
      )}
      {error && <FormHelperText error>{t(error)}</FormHelperText>}
    </div>
  );
};

export default Metrics;
