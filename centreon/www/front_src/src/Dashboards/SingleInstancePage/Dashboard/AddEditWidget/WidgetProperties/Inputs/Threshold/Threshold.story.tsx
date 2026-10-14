import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import Threshold from './Threshold';

// The selected metrics come from extraValues.data.metrics.
export const Default = createWidgetPropertyStory(Threshold, {
  propertyName: 'threshold'
});
