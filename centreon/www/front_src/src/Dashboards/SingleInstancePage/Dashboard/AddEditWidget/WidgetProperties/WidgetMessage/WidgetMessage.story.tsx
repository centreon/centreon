import {
  WidgetPropertyStoryFrame,
  type WidgetPropertyStoryProps
} from '../WidgetPropertyStoryFrame';
import WidgetMessage from './WidgetMessage';

export const Default = (props: WidgetPropertyStoryProps): JSX.Element => (
  <WidgetPropertyStoryFrame {...props}>
    <WidgetMessage />
  </WidgetPropertyStoryFrame>
);
