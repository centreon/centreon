/* eslint-disable react/no-array-index-key */
import { useTranslation } from 'react-i18next';
import { isNil } from 'ramda';

import { Typography } from '@mui/material';

import { ItemComposition } from '@centreon/ui/components';

import {
  labelAdd,
  labelDelete,
  labelMetrics,
  labelPleaseSelectAResource,
  labelServiceName,
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
    metrics,
    hasNoResources,
    addMetric,
    hasTooManyMetrics,
    deleteMetric,
    value,
    serviceOptions,
    changeService,
    getMetricsFromService,
    changeMetric
  } = useMetrics(propertyName);

  const canDisplayMetricsSelection = !hasNoResources() && !hasTooManyMetrics;

  return (
    <div className={classes.resourcesContainer}>
      <Typography>{t(labelMetrics)}</Typography>
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
                label={t(labelServiceName) as string}
                options={serviceOptions}
                selectedOptionId={service.serviceId}
                onChange={changeService(index)}
              />
              <MultiAutocompleteField
                className={classes.resources}
                disabled={isNil(service.serviceId)}
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
    </div>
  );
};

export default Metrics;
