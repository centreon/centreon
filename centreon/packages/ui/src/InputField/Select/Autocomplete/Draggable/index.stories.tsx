import { useState } from 'react';

import { isNil, not } from 'ramda';
import withMock from 'storybook-addon-mock';

import { Tooltip, Typography } from '@mui/material';

import { SelectEntry } from '../..';
import { buildListingEndpoint } from '../../../..';
import { Listing } from '../../../../api/models';

import MultiDraggableConnectedAutocompleteField from './MultiConnected';
import MultiDraggableAutocompleteField from './Multi';

import { ItemActionProps } from '.';

export default {
  decorators: [withMock],
  title: 'InputField/Autocomplete/Draggable'
};

const buildEntities = (from): Array<SelectEntry> => {
  return Array(10)
    .fill(0)
    .map((_, index) => ({
      id: from + index,
      name: `Entity ${from + index}`
    }));
};

const buildResult = (page): Listing<SelectEntry> => ({
  meta: {
    limit: 10,
    page,
    total: 40
  },
  result: buildEntities((page - 1) * 10)
});

const baseEndpoint = 'endpoint';

const getEndpoint = ({ endpoint, parameters }): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters
  });

const mockSearch = (page: number): object => ({
  delay: 1000,
  method: 'GET',
  response: (request): Listing<SelectEntry> => {
    const { searchParams } = request;

    return buildResult(parseInt(searchParams.page || '0', 10));
  },
  status: 200,
  url: `/endpoint?page=${page}&search=`
});

const getMockData = (): Array<object> => [
  {
    delay: 1000,
    method: 'GET',
    response: (request): Listing<SelectEntry> => {
      const { searchParams } = request;

      return buildResult(parseInt(searchParams.page || '0', 10));
    },
    status: 200,
    url: '/endpoint?page='
  },
  mockSearch(1),
  mockSearch(2),
  mockSearch(3),
  mockSearch(4)
];

const options = [
  { id: `0`, name: 'First Entity' },
  { id: `1`, name: 'Second Entity' },
  { id: `2`, name: 'Third Entity' }
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
draggableConnected.parameters = {
  mockData: getMockData()
};

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
          anchorEl: hoveredItem?.anchorElement
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
