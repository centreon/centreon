import {
  WidgetPropertyStoryFrame,
  type WidgetPropertyStoryProps,
  widgetPropertyProps
} from '../../WidgetPropertyStoryFrame';
import Timezone from './Timezone';

export const Default = (props: WidgetPropertyStoryProps): JSX.Element => (
  <WidgetPropertyStoryFrame {...props}>
    <Timezone {...widgetPropertyProps} propertyName="test" />
  </WidgetPropertyStoryFrame>
);
