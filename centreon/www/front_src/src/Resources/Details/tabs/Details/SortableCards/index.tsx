<<<<<<< HEAD
import { useState } from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import { useTranslation } from 'react-i18next';
import { rectIntersection } from '@dnd-kit/core';
import { rectSortingStrategy } from '@dnd-kit/sortable';
import {
  append,
  equals,
  filter,
  find,
  findIndex,
  isEmpty,
  map,
  pluck,
  propEq,
  remove,
  difference,
  uniq,
<<<<<<< HEAD
} from 'ramda';
import { useAtom } from 'jotai';

import { Box, Grid } from '@mui/material';
=======
  prop,
} from 'ramda';

import { Box, Grid } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import {
  SortableItems,
  useLocaleDateTimeFormat,
  RootComponentProps,
  useMemoComponent,
} from '@centreon/ui';

import getDetailCardLines, { DetailCardLine } from '../DetailsCard/cards';
import { ResourceDetails } from '../../../models';
<<<<<<< HEAD
import { detailsCardsAtom } from '../detailsCardsAtom';
=======
import {
  getStoredOrDefaultDetailsCards,
  storeDetailsCards,
} from '../storedDetailsCards';
>>>>>>> centreon/dev-21.10.x

import { CardsLayout, ChangeExpandedCardsProps, ExpandAction } from './models';
import Content from './Content';

interface Props {
  details: ResourceDetails;
  panelWidth: number;
}

interface MergeDefaultAndStoredCardsProps {
  defaultCards: Array<string>;
  storedCards: Array<string>;
}

const mergeDefaultAndStoredCards = ({
  defaultCards,
  storedCards,
}: MergeDefaultAndStoredCardsProps): Array<string> => {
  const differenceBetweenDefaultAndStoredCards = difference(
    defaultCards,
    storedCards,
  );

  return uniq([...storedCards, ...differenceBetweenDefaultAndStoredCards]);
};

const SortableCards = ({ panelWidth, details }: Props): JSX.Element => {
  const { toDateTime } = useLocaleDateTimeFormat();
  const { t } = useTranslation();
<<<<<<< HEAD
  const [expandedCards, setExpandedCards] = useState<Array<string>>([]);

  const [storedDetailsCards, storeDetailsCards] = useAtom(detailsCardsAtom);
=======
  const [expandedCards, setExpandedCards] = React.useState<Array<string>>([]);

  const storedDetailsCards = getStoredOrDefaultDetailsCards([]);
>>>>>>> centreon/dev-21.10.x

  const changeExpandedCards = ({
    action,
    card,
  }: ChangeExpandedCardsProps): void => {
    if (equals(action, ExpandAction.add)) {
      setExpandedCards(append(card, expandedCards));

      return;
    }

    const expandedCardIndex = findIndex(equals(card), expandedCards);
    setExpandedCards(remove(expandedCardIndex, 1, expandedCards));
  };

  const allDetailsCards = getDetailCardLines({
    changeExpandedCards,
    details,
    expandedCards,
    t,
    toDateTime,
  });

  const allDetailsCardsTitle = pluck('title', allDetailsCards);

  const defaultDetailsCardsLayout = isEmpty(storedDetailsCards)
    ? allDetailsCardsTitle
    : mergeDefaultAndStoredCards({
        defaultCards: allDetailsCardsTitle,
        storedCards: storedDetailsCards,
      });

  const cards = map<string, CardsLayout>(
    (title) => ({
      id: title,
      width: panelWidth,
      ...(find(propEq('title', title), allDetailsCards) as DetailCardLine),
    }),
    defaultDetailsCardsLayout,
  );

  const displayedCards = filter(
    ({ shouldBeDisplayed }) => shouldBeDisplayed,
    cards,
  );

  const RootComponent = ({ children }: RootComponentProps): JSX.Element => (
    <Grid container spacing={1} style={{ width: panelWidth }}>
      {children}
    </Grid>
  );

<<<<<<< HEAD
  const dragEnd = ({ items }): void => {
=======
  const dragEnd = (items: Array<string>): void => {
>>>>>>> centreon/dev-21.10.x
    storeDetailsCards(items);
  };

  return useMemoComponent({
    Component: (
      <Box>
        <SortableItems<CardsLayout>
<<<<<<< HEAD
          updateSortableItemsOnItemsChange
=======
>>>>>>> centreon/dev-21.10.x
          Content={Content}
          RootComponent={RootComponent}
          collisionDetection={rectIntersection}
          itemProps={[
            'shouldBeDisplayed',
            'line',
            'xs',
            'isCustomCard',
            'width',
            'title',
          ]}
          items={displayedCards}
          sortingStrategy={rectSortingStrategy}
          onDragEnd={dragEnd}
        />
      </Box>
    ),
<<<<<<< HEAD
    memoProps: [panelWidth, expandedCards, details],
=======
    memoProps: [
      defaultDetailsCardsLayout,
      panelWidth,
      expandedCards,
      details,
      displayedCards.map(prop('id')),
    ],
>>>>>>> centreon/dev-21.10.x
  });
};

export default SortableCards;
