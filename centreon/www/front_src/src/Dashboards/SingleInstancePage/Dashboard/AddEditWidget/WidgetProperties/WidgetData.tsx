import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import Title from '../../../../components/Title';
import { labelDatasetSelection } from '../../translatedLabels';

import { FormikValues, useFormikContext } from 'formik';
import { getDataProperty } from './Inputs/utils';
import SubInputs from './SubInputs';
import { useWidgetInputs } from './useWidgetInputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';

const WidgetData = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useWidgetPropertiesStyles();
  const { values } = useFormikContext<FormikValues>();

  const widgetData = useWidgetInputs('data');

  console.log({widgetData})

  const isWidgetSelected = !isNil(widgetData);

  return (
    <div>
      {isWidgetSelected && <Title>{t(labelDatasetSelection)}</Title>}
      <div className={classes.widgetDataContent}>
        {(widgetData || []).map(({ Component, key, props }) => (
          <div className={classes.widgetDataItem} key={key}>
            <SubInputs
              subInputs={props.subInputs}
              subInputsDelimiter={props.subInputsDelimiter}
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
