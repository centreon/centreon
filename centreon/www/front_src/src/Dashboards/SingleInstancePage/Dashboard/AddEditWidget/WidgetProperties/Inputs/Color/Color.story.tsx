import { labelBackgroundColor } from '../../../../translatedLabels';
import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import ColorSelector from './Color';

export const Default = createWidgetPropertyStory(ColorSelector, {
  label: labelBackgroundColor
});
