import {
  WidgetPropertyStoryFrame,
  type WidgetPropertyStoryProps,
  widgetPropertyProps
} from '../../WidgetPropertyStoryFrame';
import Locale from './Locale';

export const Default = (props: WidgetPropertyStoryProps): JSX.Element => (
  <WidgetPropertyStoryFrame {...props}>
    <Locale {...widgetPropertyProps} propertyName="test" />
  </WidgetPropertyStoryFrame>
);
