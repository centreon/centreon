import { useState } from 'react';

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';
import { isNil, not } from 'ramda';

import { Tooltip, Typography } from '@mui/material';

import { SelectEntry } from '../..';
import { buildListingEndpoint } from '../../../..';
import { Listing } from '../../../../api/models';

import MultiDraggableConnectedAutocompleteField from './MultiConnected';
import MultiDraggableAutocompleteField from './Multi';

import { ItemActionProps } from '.';

export default { title: 'InputField/Autocomplete/Draggable' };

const buildEntities = (from): Array<SelectEntry> => {
  return Array(10)
    .fill(0)
    .map((_, index) => ({
      id: from + index,
      name: `Entity ${from + index}`,
    }));
};

const buildResult = (page): Listing<SelectEntry> => ({
  meta: {
    limit: 10,
    page,
    total: 40,
  },
  result: buildEntities((page - 1) * 10),
});

const baseEndpoint = 'endpoint';

const getEndpoint = ({ endpoint, parameters }): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters,
  });

const mockedAxios = new MockAdapter(axios, { delayResponse: 500 });

mockedAxios
  .onGet(
    /endpoint\?page=\d+(?:&search={"\$or":\[{"host\.name":{"\$rg":".*"}}]})?/,
  )
  .reply((config) => {
    return [
      200,
      buildResult(parseInt(config.url?.split('page=')[1][0] || '0', 10)),
    ];
  });

const options = [
  { id: `0`, name: 'First Entity' },
  { id: `1`, name: 'Second Entity' },
  { id: `2`, name: 'Third Entity' },
];

const MultiDraggable = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const containedDraggable = (): JSX.Element => (
  <div style={{ width: '400px' }}>
    <MultiDraggable />
  </div>
);

const MultiDraggableConnected = (): JSX.Element => (
  <MultiDraggableConnectedAutocompleteField
    field="host.name"
    getEndpoint={(parameters): string => {
      return getEndpoint({ endpoint: baseEndpoint, parameters });
    }}
    initialPage={1}
    label="Multi Draggable Connected Autocomplete"
    placeholder="Type here..."
  />
);

export const draggableConnected = (): JSX.Element => (
  <MultiDraggableConnected />
);

const MultiDraggableError = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    error="Error"
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const draggableWithError = (): JSX.Element => <MultiDraggableError />;

const MultiDraggableRequired = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    required
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const draggableWithRequired = (): JSX.Element => (
  <MultiDraggableRequired />
);

const MultiDraggableInitialValues = (): JSX.Element => (
  <MultiDraggableAutocompleteField
    initialValues={[options[0], options[1]]}
    label="Draggable Autocomplete"
    options={options}
    placeholder="Type here..."
  />
);

export const draggableWithInitialValues = (): JSX.Element => (
  <MultiDraggableInitialValues />
);

const MultiDraggableClickAndHoverItem = (): JSX.Element => {
  const [clickedItem, setClickedItem] = useState<ItemActionProps | null>(null);
  const [hoveredItem, setHoveredItem] = useState<ItemActionProps | null>(null);

  return (
    <div>
      <Tooltip
        PopperProps={{
          anchorEl: hoveredItem?.anchorElement,
        }}
        open={not(isNil(hoveredItem?.anchorElement))}
        title={hoveredItem?.item.name || ''}
      >
        <MultiDraggableAutocompleteField
          itemClick={setClickedItem}
          itemHover={setHoveredItem}
          label="Draggable Autocomplete"
          options={options}
          placeholder="Type here..."
        />
      </Tooltip>
      {not(isNil(clickedItem)) && (
        <Typography>You clicked on {clickedItem?.item.name}</Typography>
      )}
    </div>
  );
};

export const draggableWithClickAndHoverListenersOnItem = (): JSX.Element => (
  <MultiDraggableClickAndHoverItem />
);
