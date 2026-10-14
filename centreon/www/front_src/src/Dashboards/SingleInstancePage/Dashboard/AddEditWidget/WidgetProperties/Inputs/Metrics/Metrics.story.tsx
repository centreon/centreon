import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import Metrics from './Metrics';

// The selected resources and metrics come from extraValues.data, the
// selection mode from widgetProperties.
export const Default = createWidgetPropertyStory(Metrics, {
  propertyName: 'metrics'
});
