import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import Title from '../../../../components/Title';
import { labelDatasetSelection } from '../../translatedLabels';

import { useWidgetInputs } from './useWidgetInputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';
import { getDataProperty } from './Inputs/utils';
import { FormikValues, useFormikContext } from 'formik';
import SubInputs from './SubInputs';

const WidgetData = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useWidgetPropertiesStyles();
  const { values } = useFormikContext<FormikValues>();

  const widgetData = useWidgetInputs('data');

  const isWidgetSelected = !isNil(widgetData);

  return (
    <div>
      {isWidgetSelected && <Title>{t(labelDatasetSelection)}</Title>}
      <div className={classes.widgetDataContent}>
        {(widgetData || []).map(({ Component, key, props }) => (
          <div className={classes.widgetDataItem} key={key}>
            <SubInputs
              subInputs={props.subInputs}
              value={getDataProperty({
                obj: values,
                propertyName: props.propertyName
              })}
            >
              <Component {...props} />
            </SubInputs>
          </div>
        ))}
      </div>
    </div>
  );
};

export default WidgetData;
