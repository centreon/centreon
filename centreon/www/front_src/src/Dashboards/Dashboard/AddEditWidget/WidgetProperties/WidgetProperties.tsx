import { isEmpty } from 'ramda';

import { labelDescription, labelName } from '../../translatedLabels';

import { WidgetTextField } from './Inputs';
import { useWidgetProperties } from './useWidgetProperties';

const WidgetProperties = (): JSX.Element => {
  const widgetProperties = useWidgetProperties();

  const hasProperties = !isEmpty(widgetProperties);

  return (
    <>
      {hasProperties && (
        <>
          <WidgetTextField label={labelName} propertyName="name" />
          <WidgetTextField
            label={labelDescription}
            propertyName="description"
            text={{ multiline: true }}
          />
        </>
      )}
      {widgetProperties.map(({ Component, key, props }) => (
        <Component key={key} {...props} />
      ))}
    </>
  );
};

export default WidgetProperties;
