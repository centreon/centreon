import {
  WidgetPropertyStoryFrame,
  widgetPropertyProps
} from '../WidgetPropertyStoryFrame';
import WidgetWarningField from './Warning';

interface Props {
  label?: string;
}

export const WithLabel = ({ label = 'Text' }: Props): JSX.Element => (
  <WidgetPropertyStoryFrame>
    <WidgetWarningField {...widgetPropertyProps} label={label} />
  </WidgetPropertyStoryFrame>
);
