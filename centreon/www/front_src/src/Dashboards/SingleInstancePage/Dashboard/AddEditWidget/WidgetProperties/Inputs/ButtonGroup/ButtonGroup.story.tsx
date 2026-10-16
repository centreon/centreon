import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import ButtonGroup from './ButtonGroup';

export const Default = createWidgetPropertyStory(ButtonGroup, {
  label: 'Buttons',
  options: [
    { id: 'button1', name: 'Button 1' },
    { id: 'button2', name: 'Button 2' },
    { id: 'button3', name: 'Button 3' },
    { id: 'button4', name: 'Button 4' }
  ]
});
