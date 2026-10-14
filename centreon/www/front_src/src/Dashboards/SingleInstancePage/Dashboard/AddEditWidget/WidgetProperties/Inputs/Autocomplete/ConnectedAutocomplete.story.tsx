import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import ConnectedAutocomplete from './ConnectedAutocomplete';

// Options are fetched from ./api/latest/endpoint; specs mock it.
export const Default = createWidgetPropertyStory(ConnectedAutocomplete, {
  baseEndpoint: '/endpoint',
  label: 'Autocomplete',
  secondaryLabel: 'Label'
});
