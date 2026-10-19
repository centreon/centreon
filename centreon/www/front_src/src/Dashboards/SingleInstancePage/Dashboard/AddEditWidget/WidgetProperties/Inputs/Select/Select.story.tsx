import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import Select from './Select';

export const Default = createWidgetPropertyStory(Select, {
  label: 'Select',
  options: [
    { id: 'option1', name: 'Option 1' },
    { id: 'option2', name: 'Option 2' }
  ],
  propertyName: 'select',
  type: 'select'
});
