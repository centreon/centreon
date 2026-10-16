import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import Resources from './Resources';

// The resources are read from extraValues.data, the selection mode from
// widgetProperties.
export const Default = createWidgetPropertyStory(Resources, {
  propertyName: 'resources'
});
