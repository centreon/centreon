import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import DatePicker from './DatePicker';

export const Default = createWidgetPropertyStory(DatePicker, {
  datePicker: { maxDays: 20 }
});
