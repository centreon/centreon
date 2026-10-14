import {
  WidgetPropertyStoryFrame,
  type WidgetPropertyStoryProps,
  widgetPropertyProps
} from '../../WidgetPropertyStoryFrame';
import WidgetTiles from './Tiles';

export const Default = (props: WidgetPropertyStoryProps): JSX.Element => (
  <WidgetPropertyStoryFrame {...props}>
    <WidgetTiles {...widgetPropertyProps} propertyName="tiles" />
  </WidgetPropertyStoryFrame>
);
