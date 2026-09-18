import { rectIntersection } from '@dnd-kit/core';
import { verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useTranslation } from 'react-i18next';

import type { SelectEntry } from '../../InputField/Select';
import type { GetEndpointParams } from '../../InputField/Select/Autocomplete/Connected';
import SortableItems from '../../SortableItems';
import { ItemComposition } from '../ItemComposition';
import type {
  SortableAutocompleteAction,
  SortableAutocompleteEntry
} from './models';
import { Row } from './Row';
import { useSortableSingleConnectedAutocompleteListStyles } from './SortableSingleConnectedAutocompleteList.styles';
import { labelAdd } from './translatedLabels';
import { useSortableAutocompleteEntries } from './useSortableAutocompleteEntries';

export interface Props {
  actions?: Array<SortableAutocompleteAction>;
  addLabel?: string;
  baseEndpoint?: string;
  field: string;
  getEndpoint: (params: GetEndpointParams) => string;
  getOptionDisabled?: (option: SelectEntry) => boolean;
  initialPage?: number;
  items: Array<SortableAutocompleteEntry>;
  onChange: (items: Array<SortableAutocompleteEntry>) => void;
  placeholder?: string;
  selectorLabel: string;
}

export const SortableSingleConnectedAutocompleteList = ({
  actions = [],
  addLabel,
  baseEndpoint,
  field,
  getEndpoint,
  getOptionDisabled,
  initialPage,
  items,
  onChange,
  placeholder,
  selectorLabel
}: Props): JSX.Element => {
  const { classes } = useSortableSingleConnectedAutocompleteListStyles();
  const { t } = useTranslation();
  const { addEntry, changeEntryValue, removeEntry, reorderEntries } =
    useSortableAutocompleteEntries({ items, onChange });

  return (
    <ItemComposition labelAdd={addLabel || t(labelAdd)} onAddItem={addEntry}>
      {[
        <SortableItems<SortableAutocompleteEntry>
          Content={Row({
            actions,
            baseEndpoint,
            classes,
            field,
            getEndpoint,
            getOptionDisabled,
            initialPage,
            onDelete: removeEntry,
            onValueChange: changeEntryValue,
            placeholder,
            selectorLabel,
            t
          })}
          collisionDetection={rectIntersection}
          itemProps={['id', 'value']}
          items={items}
          key="sortable-autocomplete-rows"
          onDragEnd={reorderEntries}
          sortingStrategy={verticalListSortingStrategy}
          updateSortableItemsOnItemsChange
        />
      ]}
    </ItemComposition>
  );
};
