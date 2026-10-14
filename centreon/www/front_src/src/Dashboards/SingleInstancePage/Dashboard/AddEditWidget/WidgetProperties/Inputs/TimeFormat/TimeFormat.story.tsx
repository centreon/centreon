import {
  WidgetPropertyStoryFrame,
  type WidgetPropertyStoryProps,
  widgetPropertyProps
} from '../../WidgetPropertyStoryFrame';
import TimeFormat from './TimeFormat';

export const Default = (props: WidgetPropertyStoryProps): JSX.Element => (
  <WidgetPropertyStoryFrame {...props}>
    <TimeFormat {...widgetPropertyProps} propertyName="test" />
  </WidgetPropertyStoryFrame>
);
