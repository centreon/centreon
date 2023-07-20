import { useWidgetProperties } from './useWidgetProperties';

const WidgetProperties = (): JSX.Element => {
  const widgetProperties = useWidgetProperties();

  return (
    <>
      {widgetProperties.map(({ Component, key, props }) => (
        <Component key={key} {...props} />
      ))}
    </>
  );
};

export default WidgetProperties;
